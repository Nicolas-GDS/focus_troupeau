<?php

namespace App\Services\Pilotage;

use App\Domain\Referentiels\Referentiels;
use App\Models\App\User;
use App\Services\Cloisonnement\Perimetre;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Indicateurs de pilotage du service (E25, EF-M10-04, § 3.13 « Précisions du lot L7 » ; document 03, § 6.5 non versé),
 * par GDS et par campagne, calculés à la volée : activité, délai médian prélèvement → fiche publiée, part des bulletins
 * passés en anomalie, part des alertes automatiques retenues, orientations vétérinaires, couverture des références
 * (types de fourrage avec n ≥ references.n_min). Export CSV des indicateurs et des références agrégées (quartiles, sans
 * identité). L'utilisateur ne voit que ses GDS (agent) ; le super-administrateur voit tous les GDS.
 */
class PilotageService
{
    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly Referentiels $referentiels,
        private readonly JournalService $journal,
    ) {}

    /** @return array<int, string> GDS visibles (identifiant → nom) */
    public function gds(User $user): array
    {
        return DB::table('app.gds')
            ->when(! $this->perimetre->sansRestriction($user), fn ($q) => $q->whereIn('id', $this->perimetre->gdsAccessibles($user)))
            ->orderBy('id')->pluck('nom', 'id')->all();
    }

    /** @return list<string> campagnes, la plus récente d'abord */
    public function campagnes(): array
    {
        return DB::table('fourrage.campagne')->orderByDesc('date_debut')->pluck('code')->all();
    }

    /**
     * @return array{elevages: int, visites: int, bulletins: int, fiches_publiees: int, delai_median_jours: float|null, part_anomalies: float|null,
     *     part_alertes_retenues: float|null, alertes: int, notes_liaison: int, interpretations_niveau3: int, types_couverts: int, types_total: int,
     *     n_min: int, couverture: list<array{type: string, n: int, couvert: bool}>}
     */
    public function indicateurs(int $gdsId, string $campagne, User $user): array
    {
        $this->exigerGds($gdsId, $user);
        $elevages = DB::table('fourrage.elevage')->where('gds_id', $gdsId)->select('id');
        $prelevements = DB::table('fourrage.prelevement')->whereIn('elevage_id', $elevages)->where('campagne_code', $campagne);
        $analyses = DB::table('fourrage.analyse')->whereIn('prelevement_id', (clone $prelevements)->select('id'))->where('statut', '!=', 'annule');
        $visites = DB::table('dossier.visite')->whereIn('elevage_id', $elevages)->where('campagne_code', $campagne);
        $alertes = DB::table('fourrage.alerte as al')->join('fourrage.interpretation as it', 'it.id', '=', 'al.interpretation_id')
            ->whereIn('it.analyse_id', (clone $analyses)->select('id'))->where('al.origine', 'automatique');

        $nbBulletins = (clone $analyses)->count();
        $nbAnomalies = (clone $analyses)->where(fn ($q) => $q->where('statut', 'anomalie')->orWhereNotNull('anomalie_levee_le'))->count();
        $nbAlertes = (clone $alertes)->count();
        $delai = DB::table('dossier.interpretation_visite as iv')->join('dossier.visite as v', 'v.id', '=', 'iv.visite_id')
            ->whereIn('v.elevage_id', $elevages)->where('v.campagne_code', $campagne)->whereNotNull('iv.publie_le')->where('iv.version', 1)
            ->selectRaw('percentile_cont(0.5) within group (order by extract(epoch from (iv.publie_le - (select min(p.date_prelevement) from fourrage.prelevement p where p.visite_id = v.id))) / 86400) as d')
            ->value('d');

        $nMin = (int) $this->referentiels->parametre('references.n_min', $gdsId);
        $comptes = DB::table('fourrage.prelevement as p')->join('fourrage.analyse as a', 'a.prelevement_id', '=', 'p.id')
            ->join('fourrage.elevage as e', 'e.id', '=', 'p.elevage_id')->leftJoin('ref.type_fourrage as t', 't.code', '=', 'p.type_fourrage_code')
            ->where('e.gds_id', $gdsId)->where('p.campagne_code', $campagne)->where('a.statut', 'valide')->where('a.exclu_references', false)
            ->whereExists(fn ($q) => $q->from('app.consentement as c')->whereColumn('c.elevage_id', 'e.id')->where('c.type', 'references')->whereNull('c.revoque_le'))
            ->groupBy('p.type_fourrage_code', 't.libelle')->orderBy('t.libelle')->selectRaw('coalesce(t.libelle, p.type_fourrage_code) as type, count(distinct a.id) as n')->get();
        $couverture = $comptes->map(fn ($c) => ['type' => (string) $c->type, 'n' => (int) $c->n, 'couvert' => (int) $c->n >= $nMin])->values()->all();

        return [
            'elevages' => (clone $prelevements)->distinct()->count('elevage_id'),
            'visites' => (clone $visites)->count(),
            'bulletins' => $nbBulletins,
            'fiches_publiees' => DB::table('dossier.interpretation_visite')->whereIn('visite_id', (clone $visites)->select('id'))->where('version', 1)->whereNotNull('publie_le')->count(),
            'delai_median_jours' => $delai === null ? null : (float) $delai,
            'part_anomalies' => $nbBulletins > 0 ? $nbAnomalies / $nbBulletins : null,
            'part_alertes_retenues' => $nbAlertes > 0 ? (clone $alertes)->where('al.retenue', true)->count() / $nbAlertes : null,
            'alertes' => $nbAlertes,
            'notes_liaison' => DB::table('dossier.note_liaison')->whereIn('elevage_id', $elevages)->whereNotNull('envoyee_le')
                ->whereBetween('envoyee_le', $this->bornesCampagne($campagne))->count(),
            'interpretations_niveau3' => DB::table('fourrage.interpretation')->whereIn('analyse_id', (clone $analyses)->select('id'))->where('niveau_global', 3)
                ->where('statut', '!=', 'archive')->count(),
            'types_couverts' => count(array_filter($couverture, fn ($c) => $c['couvert'])),
            'types_total' => count($couverture),
            'n_min' => $nMin,
            'couverture' => $couverture,
        ];
    }

    /** CSV des indicateurs de tous les GDS visibles pour une campagne. */
    public function exportIndicateurs(string $campagne, User $user): string
    {
        $lignes = [['gds', 'campagne', 'elevages', 'visites', 'bulletins', 'fiches_publiees', 'delai_median_jours', 'part_anomalies', 'part_alertes_retenues',
            'notes_liaison', 'interpretations_niveau3', 'types_couverts', 'types_total', 'n_min']];
        foreach (array_keys($this->gds($user)) as $gds) {
            $i = $this->indicateurs((int) $gds, $campagne, $user);
            $nombre = fn (?float $v, int $d) => $v === null ? '' : number_format($v, $d, ',', '');
            $lignes[] = [(string) $gds, $campagne, (string) $i['elevages'], (string) $i['visites'], (string) $i['bulletins'], (string) $i['fiches_publiees'],
                $nombre($i['delai_median_jours'], 1), $nombre($i['part_anomalies'], 3), $nombre($i['part_alertes_retenues'], 3), (string) $i['notes_liaison'],
                (string) $i['interpretations_niveau3'], (string) $i['types_couverts'], (string) $i['types_total'], (string) $i['n_min']];
        }
        $this->journal->ecrire('export_pilotage', $user->id, 'pilotage:'.$campagne);

        return $this->csv($lignes);
    }

    /** CSV des références agrégées du GDS (valo.v_reference_gds, n ≥ n_min, sans identité). */
    public function exportReferences(int $gdsId, string $campagne, User $user): string
    {
        $this->exigerGds($gdsId, $user);
        $nMin = (int) $this->referentiels->parametre('references.n_min', $gdsId);
        $lignes = [['gds', 'campagne', 'type_fourrage', 'parametre', 'unite', 'n', 'q1', 'mediane', 'q3', 'minimum', 'maximum']];
        foreach (DB::table('valo.v_reference_gds')->where('gds_id', $gdsId)->where('campagne_code', $campagne)->where('n', '>=', $nMin)
            ->orderBy('type_fourrage_code')->orderBy('parametre_code')->get() as $r) {
            $lignes[] = [(string) $gdsId, $campagne, (string) $r->type_fourrage_code, (string) $r->parametre_code, (string) $r->unite, (string) $r->n,
                ...array_map(fn ($v) => $v === null ? '' : number_format((float) $v, 2, ',', ''), [$r->q1, $r->mediane, $r->q3, $r->minimum, $r->maximum])];
        }
        $this->journal->ecrire('export_references', $user->id, 'references:'.$gdsId.':'.$campagne);

        return $this->csv($lignes);
    }

    /** @return array{0: string, 1: string} */
    private function bornesCampagne(string $campagne): array
    {
        $c = DB::table('fourrage.campagne')->where('code', $campagne)->first(['date_debut', 'date_fin']);

        return [(string) ($c->date_debut ?? '1900-01-01'), ($c->date_fin ?? '2999-12-31').' 23:59:59'];
    }

    private function exigerGds(int $gdsId, User $user): void
    {
        abort_unless(array_key_exists($gdsId, $this->gds($user)), 404);
    }

    /** @param  list<list<string>>  $lignes */
    private function csv(array $lignes): string
    {
        $flux = fopen('php://temp', 'r+');
        if ($flux === false) {
            throw new RuntimeException('Flux impossible.');
        }
        foreach ($lignes as $l) {
            fputcsv($flux, $l, ';', '"', '');
        }
        rewind($flux);

        return "\xEF\xBB\xBF".stream_get_contents($flux);
    }
}
