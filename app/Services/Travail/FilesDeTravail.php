<?php

namespace App\Services\Travail;

use App\Domain\Referentiels\Referentiels;
use App\Models\App\Convention;
use App\Models\App\ImportBulletin;
use App\Models\App\User;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Interpretation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * E18 — files de travail du technicien et du vétérinaire conseil (EF-M2-08), dans le périmètre de l'utilisateur
 * (les requêtes passent par les scopes de cloisonnement) et selon ses droits.
 *
 * Lot L2 : dépôts à rapprocher, bulletins en anomalie, bulletins à interpréter, brouillons, interprétations à viser,
 * interprétations à publier, conventions arrivant à échéance (EF-M0-06). Les contrôles échus (lot L3) et les
 * élevages dont l'autonomie passe sous le seuil d'alerte (lot L6) s'ajouteront avec leurs lots.
 */
class FilesDeTravail
{
    private const LIMITE = 50;

    public function __construct(private readonly Referentiels $referentiels) {}

    /**
     * @return list<array{cle: string, titre: string, total: int, lignes: list<array{libelle: string, detail: string, date: ?string, route: string, parametres: array<string, int>, etat: string}>}>
     */
    public function pour(User $user): array
    {
        $files = [];

        if ($user->can('bulletin.importer')) {
            $files[] = $this->aRapprocher();
        }

        if ($user->can('bulletin.controler')) {
            $files[] = $this->anomalies();
        }

        if ($user->can('interpretation.rediger')) {
            $files[] = $this->aInterpreter();
            $files[] = $this->brouillons();
        }

        if ($user->can('interpretation.viser')) {
            $files[] = $this->aViser();
        }

        if ($user->can('interpretation.rediger')) {
            $files[] = $this->aPublier();
        }

        if ($user->can('convention.ecrire') && $user->can('interpretation.rediger')) {
            $files[] = $this->conventionsAEcheance();
        }

        return $files;
    }

    /**
     * @return array{cle: string, titre: string, total: int, lignes: list<array<string, mixed>>}
     */
    private function aRapprocher(): array
    {
        $requete = ImportBulletin::query()->where('statut', 'a_rapprocher');

        return $this->file('a_rapprocher', 'Bulletins à rapprocher d\'un élevage', (clone $requete)->count(),
            $requete->orderBy('cree_le')->limit(self::LIMITE)->get()->map(fn (ImportBulletin $i) => [
                'libelle' => (string) ($i->donnees_extraites['entete']['num_echantillon'] ?? $i->nom_fichier),
                'detail' => 'GDS '.$i->gds_id.' — déposé le '.$i->cree_le->format('d/m/Y'),
                'date' => $i->cree_le->toDateString(),
                'route' => 'import.bulletins', 'parametres' => [], 'etat' => 'vigilance',
            ])->all());
    }

    /**
     * @return array{cle: string, titre: string, total: int, lignes: list<array<string, mixed>>}
     */
    private function anomalies(): array
    {
        $requete = Analyse::query()->where('statut', Analyse::STATUT_ANOMALIE);

        return $this->file('anomalies', 'Bulletins en anomalie (contrôle de plausibilité)', (clone $requete)->count(),
            $this->lignesAnalyses($requete->orderBy('importe_le')->limit(self::LIMITE)->get(), 'import.bulletins', 'critique'));
    }

    /**
     * @return array{cle: string, titre: string, total: int, lignes: list<array<string, mixed>>}
     */
    private function aInterpreter(): array
    {
        $requete = Analyse::query()
            ->where('statut', Analyse::STATUT_CONTROLE)
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('fourrage.interpretation as i')
                ->whereColumn('i.analyse_id', 'fourrage.analyse.id')
                ->where('i.statut', '<>', Interpretation::STATUT_ARCHIVE));

        return $this->file('a_interpreter', 'Bulletins contrôlés à interpréter', (clone $requete)->count(),
            $this->lignesAnalyses($requete->orderBy('importe_le')->limit(self::LIMITE)->get(), 'analyses.interpretation', 'information'));
    }

    /**
     * @return array{cle: string, titre: string, total: int, lignes: list<array<string, mixed>>}
     */
    private function brouillons(): array
    {
        $requete = Interpretation::query()->where('statut', Interpretation::STATUT_BROUILLON);

        return $this->file('brouillons', 'Interprétations en brouillon', (clone $requete)->count(),
            $this->lignesInterpretations($requete->orderBy('cree_le')->limit(self::LIMITE)->get(),
                fn (Interpretation $i) => $i->refus_visa_motif !== null ? 'Visa refusé : '.$i->refus_visa_motif : 'Brouillon',
                fn (Interpretation $i) => $i->refus_visa_motif !== null ? 'alerte' : 'information'));
    }

    /**
     * @return array{cle: string, titre: string, total: int, lignes: list<array<string, mixed>>}
     */
    private function aViser(): array
    {
        $requete = Interpretation::query()->where('statut', Interpretation::STATUT_VALIDE)->where('niveau_global', 3);

        return $this->file('a_viser', 'Interprétations de niveau 3 à viser', (clone $requete)->count(),
            $this->lignesInterpretations($requete->orderBy('maj_le')->limit(self::LIMITE)->get(),
                fn () => 'Niveau 3 — visa du vétérinaire conseil attendu', fn () => 'critique'));
    }

    /**
     * @return array{cle: string, titre: string, total: int, lignes: list<array<string, mixed>>}
     */
    private function aPublier(): array
    {
        $requete = Interpretation::query()->where(fn ($q) => $q
            ->where(fn ($v) => $v->where('statut', Interpretation::STATUT_VALIDE)->where('niveau_global', '<=', 2))
            ->orWhere('statut', Interpretation::STATUT_VISEE));

        return $this->file('a_publier', 'Interprétations prêtes à publier', (clone $requete)->count(),
            $this->lignesInterpretations($requete->orderBy('maj_le')->limit(self::LIMITE)->get(),
                fn (Interpretation $i) => $i->statut === Interpretation::STATUT_VISEE ? 'Visée — niveau 3' : 'Validée — niveau '.$i->niveau_global,
                fn () => 'couvert'));
    }

    /**
     * @return array{cle: string, titre: string, total: int, lignes: list<array<string, mixed>>}
     */
    private function conventionsAEcheance(): array
    {
        $jours = (int) $this->referentiels->parametre('convention.rappel_jours');
        $requete = Convention::query()
            ->where('statut', Convention::STATUT_ACTIVE)
            ->whereDate('date_fin', '>=', today())
            ->whereDate('date_fin', '<=', today()->addDays($jours));
        $conventions = $requete->clone()->orderBy('date_fin')->limit(self::LIMITE)->get();
        $libelles = $this->libellesElevages($conventions->pluck('elevage_id'));

        return $this->file('conventions', "Conventions arrivant à échéance dans les {$jours} jours", $requete->count(),
            $conventions->map(fn (Convention $c) => [
                'libelle' => $libelles[$c->elevage_id] ?? $c->elevage_id,
                'detail' => 'Échéance le '.$c->date_fin->format('d/m/Y'),
                'date' => $c->date_fin->toDateString(),
                // Pas d'écran de gestion des conventions pour le technicien avant le lot L7 (E23 est réservé à l'administrateur).
                'route' => '', 'parametres' => [], 'etat' => 'vigilance',
            ])->all());
    }

    /**
     * @param  Collection<int, Analyse>  $analyses
     * @return list<array<string, mixed>>
     */
    private function lignesAnalyses(Collection $analyses, string $route, string $etat): array
    {
        $elevages = DB::table('fourrage.prelevement')->whereIn('id', $analyses->pluck('prelevement_id'))->pluck('elevage_id', 'id');
        $libelles = $this->libellesElevages($elevages->values());

        return $analyses->map(fn (Analyse $a) => [
            'libelle' => $a->num_echantillon_labo.' — '.($libelles[$elevages[$a->prelevement_id] ?? ''] ?? ''),
            'detail' => $a->statut === Analyse::STATUT_ANOMALIE ? (string) $a->anomalie_detail : 'Importé le '.$a->importe_le->format('d/m/Y'),
            'date' => $a->importe_le->toDateString(),
            'route' => $route, 'parametres' => $route === 'analyses.interpretation' ? ['analyse' => $a->id] : [], 'etat' => $etat,
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Interpretation>  $interpretations
     * @param  callable(Interpretation): string  $detail
     * @param  callable(Interpretation): string  $etat
     * @return list<array<string, mixed>>
     */
    private function lignesInterpretations(Collection $interpretations, callable $detail, callable $etat): array
    {
        $analyses = DB::table('fourrage.analyse as a')
            ->join('fourrage.prelevement as p', 'p.id', '=', 'a.prelevement_id')
            ->whereIn('a.id', $interpretations->pluck('analyse_id'))
            ->get(['a.id', 'a.num_echantillon_labo', 'p.elevage_id'])
            ->keyBy('id');
        $libelles = $this->libellesElevages($analyses->pluck('elevage_id'));

        return $interpretations->map(function (Interpretation $i) use ($analyses, $libelles, $detail, $etat) {
            $analyse = $analyses[$i->analyse_id] ?? null;

            return [
                'libelle' => ($analyse->num_echantillon_labo ?? '—').' — '.($libelles[$analyse->elevage_id ?? ''] ?? ''),
                'detail' => $detail($i),
                'date' => $i->date_interpretation->toDateString(),
                'route' => 'analyses.interpretation', 'parametres' => ['analyse' => $i->analyse_id], 'etat' => $etat($i),
            ];
        })->values()->all();
    }

    /**
     * Libellé lisible des élevages : raison sociale si connue, sinon code pseudonyme.
     *
     * @param  Collection<int, mixed>  $ids
     * @return array<string, string>
     */
    private function libellesElevages(Collection $ids): array
    {
        return DB::table('fourrage.elevage as e')
            ->leftJoin('prive.elevage_identite as i', 'i.elevage_id', '=', 'e.id')
            ->whereIn('e.id', $ids->filter()->unique()->values())
            ->selectRaw('e.id, coalesce(i.raison_sociale, e.code_pseudo) as libelle')
            ->pluck('libelle', 'id')
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $lignes
     * @return array{cle: string, titre: string, total: int, lignes: list<array<string, mixed>>}
     */
    private function file(string $cle, string $titre, int $total, array $lignes): array
    {
        return ['cle' => $cle, 'titre' => $titre, 'total' => $total, 'lignes' => array_values($lignes)];
    }
}
