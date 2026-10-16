<?php

namespace App\Services\Interpretation;

use App\Domain\Interpretation\Historique;
use App\Domain\Interpretation\PointHistorique;
use App\Domain\Interpretation\Position;
use App\Domain\Interpretation\Positionnement;
use App\Domain\Interpretation\ReferenceQuartiles;
use App\Domain\Interpretation\Variation;
use App\Domain\Referentiels\Referentiels;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Prelevement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Positionnement d'un lot dans les populations de référence (RC-3, EF-M3-01 à EF-M3-04) et historique pluriannuel
 * d'un élevage (RC-4, EF-M3-05). Les populations sont lues dans les vues valo du lot L4 (bulletins « valide »,
 * consentement « references » actif, hors exclusions et anomalies) ; le classement et le seuil n_min relèvent du
 * domaine. Aucune donnée d'identité ne sort de ces vues : seules des valeurs anonymes servent aux graphiques.
 */
class PositionnementService
{
    /** Paramètres dont la position est résumée dans les tableaux de lots (document 04, figure 3 : UFL et MAT). */
    public const PARAMETRES_RESUMES = ['UFL_2018', 'MAT'];

    /** Paramètres des graphiques de la fiche de visite (document 04, figure 4 : MAT, UFL, cuivre). */
    public const PARAMETRES_FICHE = ['MAT', 'UFL_2018', 'CU'];

    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly Positionnement $positionnement = new Positionnement,
        private readonly Historique $historique = new Historique,
    ) {}

    public function nMin(?int $gdsId = null): int
    {
        $valeur = $this->referentiels->parametre('references.n_min', $gdsId);

        if ($valeur === null) {
            throw new RuntimeException('Paramètre references.n_min absent de app.parametre_defaut.');
        }

        return (int) $valeur;
    }

    /**
     * Les trois populations d'un prélèvement pour un paramètre (null si vide).
     *
     * @return array{departementale: ReferenceQuartiles|null, lithologique: ReferenceQuartiles|null, bigds: ReferenceQuartiles|null}
     */
    public function references(Prelevement $prelevement, string $parametre): array
    {
        $vide = ['departementale' => null, 'lithologique' => null, 'bigds' => null];

        if ($prelevement->campagne_code === null) {
            return $vide;
        }

        $gdsId = $this->gdsDe($prelevement);
        $commun = ['campagne_code' => $prelevement->campagne_code, 'type_fourrage_code' => $prelevement->type_fourrage_code, 'parametre_code' => $parametre];
        $litho = DB::table('valo.mv_prelevement_contexte')->where('prelevement_id', $prelevement->id)->value('classe_litho_gds');

        return [
            'departementale' => $this->quartiles(ReferenceQuartiles::DEPARTEMENTALE, DB::table('valo.v_reference_gds')->where($commun)->where('gds_id', $gdsId)->first()),
            'lithologique' => $litho === null ? null : $this->quartiles(ReferenceQuartiles::LITHOLOGIQUE,
                DB::table('valo.v_reference_litho')->where($commun)->where('gds_id', $gdsId)->where('classe_litho_gds', $litho)->first()),
            'bigds' => $this->quartiles(ReferenceQuartiles::BIGDS, DB::table('valo.v_reference_bigds')->where($commun)->first()),
        ];
    }

    /**
     * Positionnement d'un bulletin sur les paramètres suivis et dosés, populations dans l'ordre d'affichage de RC-3.
     *
     * @return list<array{code: string, valeur: float, populations: list<array{reference: ReferenceQuartiles, position: Position}>}>
     */
    public function positionner(Analyse $analyse): array
    {
        $prelevement = Prelevement::query()->withoutGlobalScopes()->findOrFail($analyse->prelevement_id);
        $nMin = $this->nMin($this->gdsDe($prelevement));
        $valeurs = DB::table('fourrage.resultat')->where('analyse_id', $analyse->id)->whereNotNull('valeur')
            ->whereIn('parametre_code', $this->referentiels->parametresSuivis())->pluck('valeur', 'parametre_code');
        $lignes = [];

        foreach ($this->referentiels->parametresSuivis() as $code) {
            if (! isset($valeurs[$code])) {
                continue;
            }

            $valeur = (float) $valeurs[$code];
            $refs = $this->references($prelevement, $code);
            $lignes[] = [
                'code' => $code,
                'valeur' => $valeur,
                'populations' => array_map(fn (ReferenceQuartiles $r) => ['reference' => $r, 'position' => $this->positionnement->classer($valeur, $r, $nMin)],
                    $this->positionnement->populationsAffichees($refs['departementale'], $refs['lithologique'], $refs['bigds'], $nMin)),
            ];
        }

        return $lignes;
    }

    /**
     * Position d'un bulletin dans sa première population affichée, pour les paramètres résumés de la liste des lots et
     * du tableau de la fiche (UFL et MAT, document 04 figure 3).
     *
     * @return array<string, Position>
     */
    public function positionsPrincipales(Analyse $analyse): array
    {
        $positions = [];

        foreach ($this->positionner($analyse) as $ligne) {
            if (in_array($ligne['code'], self::PARAMETRES_RESUMES, true) && $ligne['populations'] !== []) {
                $positions[$ligne['code']] = $ligne['populations'][0]['position'];
            }
        }

        return array_filter(array_replace(array_fill_keys(self::PARAMETRES_RESUMES, null), $positions));
    }

    /**
     * Valeurs anonymes d'une population (points du graphique de positionnement, EF-M3-03), triées.
     *
     * @return list<float>
     */
    public function valeursPopulation(Prelevement $prelevement, string $parametre, string $population = ReferenceQuartiles::DEPARTEMENTALE): array
    {
        if ($prelevement->campagne_code === null) {
            return [];
        }

        $requete = DB::table('valo.v_reference_population')->where([
            'campagne_code' => $prelevement->campagne_code, 'type_fourrage_code' => $prelevement->type_fourrage_code, 'parametre_code' => $parametre,
        ]);

        if ($population !== ReferenceQuartiles::BIGDS) {
            $requete->where('gds_id', $this->gdsDe($prelevement));
        }

        if ($population === ReferenceQuartiles::LITHOLOGIQUE) {
            $requete->where('classe_litho_gds', DB::table('valo.mv_prelevement_contexte')->where('prelevement_id', $prelevement->id)->value('classe_litho_gds'));
        }

        return $requete->orderBy('valeur')->pluck('valeur')->map(fn ($v) => (float) $v)->values()->all();
    }

    /**
     * Campagnes précédentes de l'élevage pour le même type et le même paramètre (rappel du graphique, EF-M3-03).
     *
     * @return list<PointHistorique>
     */
    public function rappels(Prelevement $prelevement, string $parametre, bool $publiesSeulement = false): array
    {
        if ($prelevement->campagne_code === null) {
            return [];
        }

        return array_values(array_filter($this->historique($prelevement->elevage_id, $prelevement->type_fourrage_code, $parametre, $publiesSeulement)['points'],
            fn (PointHistorique $p) => strcmp($p->campagne, (string) $prelevement->campagne_code) < 0));
    }

    /**
     * Graphiques de positionnement d'un bulletin (E04, EF-M3-01 à EF-M3-03) : pour chaque paramètre suivi et dosé, ses
     * populations dans l'ordre de RC-3, chacune avec sa position, ses points et son libellé ; rappel des campagnes
     * précédentes limité aux lots publiés pour un lecteur externe.
     *
     * @return list<array{code: string, libelle: string, unite: string, decimales: int, valeur: float, rappels: list<PointHistorique>, populations: list<array{reference: ReferenceQuartiles, position: Position, valeurs: list<float>, libelle: string}>}>
     */
    public function graphiques(Analyse $analyse, bool $publiesSeulement = false): array
    {
        $prelevement = Prelevement::query()->withoutGlobalScopes()->findOrFail($analyse->prelevement_id);
        $parametres = DB::table('ref.parametre')->whereIn('code', $this->referentiels->parametresSuivis())->get()->keyBy('code');

        return array_map(fn (array $ligne) => [
            'code' => $ligne['code'],
            'libelle' => (string) $parametres[$ligne['code']]->libelle,
            'unite' => (string) $parametres[$ligne['code']]->unite,
            'decimales' => (int) $parametres[$ligne['code']]->decimales,
            'valeur' => $ligne['valeur'],
            'rappels' => $this->rappels($prelevement, $ligne['code'], $publiesSeulement),
            'populations' => array_map(fn (array $p) => [
                ...$p,
                'valeurs' => $this->valeursPopulation($prelevement, $ligne['code'], $p['reference']->population),
                'libelle' => $this->libellePopulation($p['reference'], $prelevement),
            ], $ligne['populations']),
        ], $this->positionner($analyse));
    }

    /**
     * Graphiques de la fiche de visite (document 04, figure 4) : par type de fourrage des lots de la visite, et pour les
     * paramètres PARAMETRES_FICHE, la première population affichée quand elle est suffisante, avec tous les lots de
     * l'élevage de ce type mis en évidence.
     *
     * @param  list<array{analyse: Analyse, prelevement: Prelevement, libelle: string}>  $lots
     * @return list<array{type: string, graphiques: list<array{libelle: string, unite: string, decimales: int, reference: ReferenceQuartiles, population: string, valeurs: list<float>, lots: list<array{libelle: string, valeur: float, position: Position}>}>}>
     */
    public function graphiquesVisite(array $lots): array
    {
        $parametres = DB::table('ref.parametre')->whereIn('code', self::PARAMETRES_FICHE)->get()->keyBy('code');
        $types = DB::table('ref.type_fourrage')->pluck('libelle', 'code');
        $sections = [];

        foreach (collect($lots)->groupBy(fn (array $l) => $l['prelevement']->type_fourrage_code) as $type => $lotsDuType) {
            $premier = $lotsDuType->first()['prelevement'];
            $nMin = $this->nMin($this->gdsDe($premier));
            $graphiques = [];

            foreach (self::PARAMETRES_FICHE as $code) {
                $refs = $this->references($premier, $code);
                $reference = $this->positionnement->populationsAffichees($refs['departementale'], $refs['lithologique'], $refs['bigds'], $nMin)[0] ?? null;
                $valeurs = DB::table('fourrage.resultat')->whereIn('analyse_id', $lotsDuType->map(fn (array $l) => $l['analyse']->id))
                    ->where('parametre_code', $code)->whereNotNull('valeur')->pluck('valeur', 'analyse_id');

                if ($reference === null || ! $reference->suffisante($nMin) || $valeurs->isEmpty()) {
                    continue;
                }

                $graphiques[] = [
                    'libelle' => (string) $parametres[$code]->libelle,
                    'unite' => (string) $parametres[$code]->unite,
                    'decimales' => (int) $parametres[$code]->decimales,
                    'reference' => $reference,
                    'population' => $this->libellePopulation($reference, $premier),
                    'valeurs' => $this->valeursPopulation($premier, $code, $reference->population),
                    'lots' => $lotsDuType->filter(fn (array $l) => isset($valeurs[$l['analyse']->id]))->map(fn (array $l) => [
                        'libelle' => $l['libelle'],
                        'valeur' => (float) $valeurs[$l['analyse']->id],
                        'position' => $this->positionnement->classer((float) $valeurs[$l['analyse']->id], $reference, $nMin),
                    ])->values()->all(),
                ];
            }

            if ($graphiques !== []) {
                $sections[] = ['type' => (string) ($types[$type] ?? $type), 'graphiques' => $graphiques];
            }
        }

        return $sections;
    }

    /** Libellé d'une population (« 17 lots de foin de prairie permanente du GDS 24, campagne 2025-2026 »). */
    public function libellePopulation(ReferenceQuartiles $reference, Prelevement $prelevement): string
    {
        $type = mb_strtolower((string) DB::table('ref.type_fourrage')->where('code', $prelevement->type_fourrage_code)->value('libelle'));
        $lots = $reference->n.' lot'.($reference->n > 1 ? 's' : '').' de '.$type;

        return match ($reference->population) {
            ReferenceQuartiles::LITHOLOGIQUE => $lots.' du GDS '.$this->gdsDe($prelevement).' sur la même classe de sol, campagne '.$prelevement->campagne_code,
            ReferenceQuartiles::BIGDS => $lots.' des GDS '.DB::table('app.gds')->orderBy('id')->pluck('id')->implode(' et ').', campagne '.$prelevement->campagne_code,
            default => $lots.' du GDS '.$this->gdsDe($prelevement).', campagne '.$prelevement->campagne_code,
        };
    }

    /**
     * Série pluriannuelle d'un élevage pour un type de fourrage et un paramètre, et variation entre les deux dernières
     * campagnes (RC-4). Pour un lecteur externe (éleveur, vétérinaire d'élevage), seuls les lots publiés comptent.
     *
     * @return array{points: list<PointHistorique>, variation: Variation|null}
     */
    public function historique(string $elevageId, string $typeFourrage, string $parametre, bool $publiesSeulement = false): array
    {
        $valeurs = DB::table('valo.v_historique_elevage')
            ->where(['elevage_id' => $elevageId, 'type_fourrage_code' => $typeFourrage, 'parametre_code' => $parametre])
            ->when($publiesSeulement, fn ($q) => $q->where('publie', true))
            ->get()->groupBy('campagne_code')
            ->map(fn ($lignes) => $lignes->map(fn ($l) => (float) $l->valeur)->values()->all())->all();
        $points = $this->historique->points($valeurs);

        return ['points' => $points, 'variation' => $this->historique->variation($points)];
    }

    private function quartiles(string $population, ?object $ligne): ?ReferenceQuartiles
    {
        return $ligne === null ? null : new ReferenceQuartiles($population, (int) $ligne->n, (float) $ligne->q1, (float) $ligne->mediane,
            (float) $ligne->q3, (float) $ligne->minimum, (float) $ligne->maximum);
    }

    private function gdsDe(Prelevement $prelevement): int
    {
        return (int) DB::table('fourrage.elevage')->where('id', $prelevement->elevage_id)->value('gds_id');
    }
}
