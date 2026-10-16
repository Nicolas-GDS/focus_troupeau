<?php

namespace App\Services\Dossier;

use App\Domain\Referentiels\Referentiels;
use App\Models\App\User;
use App\Models\Dossier\Action;
use App\Models\Dossier\AnalyseEau;
use App\Models\Dossier\Controle;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Dossier\IndicateurSanitaire;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\Message;
use App\Models\Dossier\MesureTerrain;
use App\Models\Dossier\PlanAction;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\AnalyseAnimale;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\EvenementSanitaire;
use App\Models\Fourrage\Interpretation;
use App\Models\Fourrage\Prelevement;
use App\Services\Cloisonnement\RegleStatut;
use App\Services\Interpretation\InterpretationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Lecture du dossier élevage (EF-M1-01 à EF-M1-09, écrans E02 à E11) : ce que le lecteur peut voir, à travers les
 * scopes de cloisonnement et les policies (§ 6.3). L'éleveur et le vétérinaire d'élevage ne lisent que les objets
 * publiés ; une visite se lit dans sa dernière version publiée, même quand une version suivante est en préparation.
 * Les agents GDS lisent aussi les objets non publiés : les écrans les signalent comme tels.
 *
 * Restent aux lots suivants : position dans la campagne (RC-3, L4), courbes pluriannuelles (RC-4, L4), variante
 * « ration » de la matrice et comparaison des apports au besoin (RC-9, L5), autonomie fourragère (L6).
 */
class LectureDossierService
{
    /** Regroupement des lots et visites dont la campagne n'est pas déterminée. */
    public const SANS_CAMPAGNE = 'non déterminée';

    public function __construct(
        private readonly InterpretationService $interpretations,
        private readonly InterpretationVisiteService $visites,
        private readonly FicheVisiteService $fiches,
        private readonly EauService $eau,
        private readonly MesuresService $mesures,
        private readonly ProfilsService $profils,
        private readonly BilanSanitaireService $bilan,
        private readonly EtiquettesService $etiquettes,
        private readonly RegleStatut $regleStatut,
        private readonly Referentiels $referentiels,
    ) {}

    public function agent(User $lecteur): bool
    {
        return $this->regleStatut->voitTousLesStatuts($lecteur);
    }

    /** @return Collection<int, Visite> visites lisibles, la plus récente d'abord */
    public function visitesLisibles(Elevage $elevage, User $lecteur): Collection
    {
        return Visite::query()->where('elevage_id', $elevage->id)->orderByDesc('date_visite')->orderByDesc('id')->get()
            ->filter(fn (Visite $v) => Gate::forUser($lecteur)->allows('view', $v))->values();
    }

    /** Version montrée d'une visite : la dernière publiée ; à défaut, pour un agent, la version en cours. */
    public function interpretationMontree(Visite $visite, User $lecteur): ?InterpretationVisite
    {
        $publiee = $this->visites->publiee($visite);

        if ($publiee !== null) {
            return $publiee;
        }

        $courante = $this->visites->courante($visite);

        return $courante !== null && Gate::forUser($lecteur)->allows('view', $courante) ? $courante : null;
    }

    /** Interprétation montrée d'un bulletin : la dernière publiée ; à défaut, pour un agent, la version en cours. */
    public function interpretationLot(Analyse $analyse, User $lecteur): ?Interpretation
    {
        return $this->interpretations->publiee($analyse) ?? ($this->agent($lecteur) ? $this->interpretations->courante($analyse) : null);
    }

    /**
     * Accueil de l'éleveur (EF-M1-01) : dernière visite publiée, ses messages classés par gravité, les actions des
     * plans publiés qui incombent au lecteur et les contrôles à venir.
     *
     * @return array{visite: Visite|null, interpretation: InterpretationVisite|null, technicien: string|null, messages: Collection<int, Message>, actions: Collection<int, Action>, controles: Collection<int, Controle>}
     */
    public function accueil(Elevage $elevage, User $lecteur): array
    {
        $publiees = $this->interpretationsPubliees($elevage, $lecteur);
        $derniere = $publiees->first();
        $plans = PlanAction::query()->whereIn('interpretation_visite_id', $publiees->pluck('interpretation.id'))->pluck('id');
        $responsable = $lecteur->hasRole('veterinaire') ? 'veterinaire' : 'eleveur';

        return [
            'visite' => $derniere['visite'] ?? null,
            'interpretation' => $derniere['interpretation'] ?? null,
            'technicien' => $derniere === null ? null : $this->nomUtilisateur($derniere['visite']->technicien_id),
            'messages' => $derniere === null ? collect() : Message::query()->where('interpretation_visite_id', $derniere['interpretation']->id)
                ->orderByDesc('gravite')->orderBy('ordre')->get(),
            'actions' => Action::query()->whereIn('plan_action_id', $plans)->where('responsable', $responsable)
                ->where('statut', Action::STATUT_A_FAIRE)->orderByRaw('echeance is null')->orderBy('echeance')->orderBy('priorite')->orderBy('ordre')->get(),
            'controles' => Controle::query()->whereIn('action_id', Action::query()->whereIn('plan_action_id', $plans)->select('id'))
                ->where('statut', '!=', Controle::STATUT_REALISE)->orderBy('echeance')->get(),
        ];
    }

    /**
     * Synthèse d'une visite (E03, EF-M1-09) : les données de la fiche pour la version montrée, réduites aux bulletins
     * lisibles par le lecteur.
     *
     * @return array<string, mixed>|null
     */
    public function syntheseVisite(Visite $visite, User $lecteur): ?array
    {
        $interpretation = $this->interpretationMontree($visite, $lecteur);

        if ($interpretation === null) {
            return null;
        }

        return [
            ...$this->fiches->donnees($interpretation, fn (Analyse $a) => Gate::forUser($lecteur)->allows('view', $a)),
            'prelevements' => Prelevement::query()->with('typeFourrage')->where('visite_id', $visite->id)->orderBy('date_prelevement')->get(),
        ];
    }

    /**
     * Lots analysés lisibles (volet Fourrages E04, EF-M1-02), par campagne, la plus récente d'abord.
     *
     * @return Collection<int|string, array<int, array{prelevement: Prelevement, analyse: Analyse, interpretation: Interpretation|null}>>
     */
    public function fourrages(Elevage $elevage, User $lecteur): Collection
    {
        return collect($this->lotsLisibles($elevage, $lecteur))
            ->sortByDesc(fn (array $l) => ($l['prelevement']->campagne_code ?? '').$l['prelevement']->date_prelevement->format('Ymd'))
            ->groupBy(fn (array $l) => $l['prelevement']->campagne_code ?? self::SANS_CAMPAGNE)
            ->map(fn (Collection $lots) => $lots->values()->all());
    }

    /**
     * Détail d'un lot (E04) : pour chaque bulletin lisible, l'interprétation montrée, les résultats classés avec leur
     * repère et leur source, les alertes retenues.
     *
     * @return list<array{analyse: Analyse, interpretation: Interpretation|null, lignes: list<array<string, mixed>>, alertes: list<array<string, mixed>>, deconseillee: list<string>}>
     */
    public function fourrage(Prelevement $prelevement, User $lecteur): array
    {
        $lots = [];

        foreach ($this->analysesLisibles(collect([$prelevement->id]), $lecteur) as $analyse) {
            $interpretation = $this->interpretationLot($analyse, $lecteur);
            $lots[] = [
                'analyse' => $analyse,
                'interpretation' => $interpretation,
                'lignes' => $this->interpretations->lignesResultats($analyse, $interpretation?->categorie_cible_code),
                'alertes' => $interpretation === null ? [] : array_values(array_filter($this->interpretations->lignesAlertes($interpretation),
                    fn (array $l) => $l['alerte']->retenue === true)),
                'deconseillee' => $interpretation === null ? [] : $this->interpretations->utilisationDeconseillee($interpretation),
            ];
        }

        return $lots;
    }

    /**
     * Matrice de vigilance minérale (E05, EF-M1-03) : lots lisibles × éléments, pour une campagne.
     *
     * @return array{campagnes: list<string>, campagne: string|null, lots: list<array{prelevement: Prelevement, analyse: Analyse, valeurs: array<string, array<string, mixed>>}>}
     */
    public function matrice(Elevage $elevage, User $lecteur, ?string $campagne): array
    {
        $tous = collect($this->lotsLisibles($elevage, $lecteur));
        $campagnes = $tous->map(fn (array $l) => $l['prelevement']->campagne_code ?? self::SANS_CAMPAGNE)->unique()->sortDesc()->values()->all();
        $campagne = in_array($campagne, $campagnes, true) ? $campagne : ($campagnes[0] ?? null);
        $lots = [];

        foreach ($tous->filter(fn (array $l) => ($l['prelevement']->campagne_code ?? self::SANS_CAMPAGNE) === $campagne) as $lot) {
            $valeurs = [];
            foreach ($this->interpretations->lignesResultats($lot['analyse'], $lot['interpretation']?->categorie_cible_code) as $ligne) {
                if (in_array($ligne['code'], FicheVisiteService::PARAMETRES_MATRICE, true)) {
                    $valeurs[$ligne['code']] = $ligne;
                }
            }
            $lots[] = ['prelevement' => $lot['prelevement'], 'analyse' => $lot['analyse'], 'valeurs' => $valeurs];
        }

        return ['campagnes' => $campagnes, 'campagne' => $campagne, 'lots' => $lots];
    }

    /**
     * Analyses d'eau lisibles (E06, EF-M1-04), la plus récente d'abord.
     *
     * @return list<array{analyse: AnalyseEau, lignes: list<array<string, mixed>>}>
     */
    public function eau(Elevage $elevage, User $lecteur): array
    {
        return AnalyseEau::query()->where('elevage_id', $elevage->id)->orderByDesc('date_prelevement')->orderByDesc('id')->get()
            ->filter(fn (AnalyseEau $a) => Gate::forUser($lecteur)->allows('view', $a))
            ->map(fn (AnalyseEau $a) => ['analyse' => $a, 'lignes' => $this->eau->lignes($a)])->values()->all();
    }

    /**
     * Mesures terrain et profils sanguins lisibles (E07, EF-M1-05), par date, la plus récente d'abord.
     *
     * @return list<array{date: string, mesures: list<array<string, mixed>>, profils: list<array<string, mixed>>}>
     */
    public function animaux(Elevage $elevage, User $lecteur): array
    {
        $mesures = MesureTerrain::query()->where('elevage_id', $elevage->id)->orderBy('id')->get()
            ->filter(fn (MesureTerrain $m) => Gate::forUser($lecteur)->allows('view', $m))
            ->groupBy(fn (MesureTerrain $m) => $m->date_mesure->toDateString());
        $profils = AnalyseAnimale::query()->where('elevage_id', $elevage->id)->orderBy('parametre')->get()
            ->filter(fn (AnalyseAnimale $p) => Gate::forUser($lecteur)->allows('view', $p))
            ->groupBy(fn (AnalyseAnimale $p) => $p->date_prelevement->toDateString());
        $dates = $mesures->keys()->merge($profils->keys())->unique()->sortDesc()->values();

        return $dates->map(fn (string $date) => [
            'date' => $date,
            'mesures' => $this->mesures->groupesDe($mesures->get($date, collect())->values()),
            'profils' => $this->profils->lignesDe($profils->get($date, collect())->values()),
        ])->all();
    }

    /**
     * Étiquettes lisibles (E08, EF-M1-06) et apport journalier par élément, à la date du jour.
     *
     * @return list<array{etiquette: EtiquetteAliment, lecture: array<string, mixed>}>
     */
    public function aliments(Elevage $elevage, User $lecteur): array
    {
        return EtiquetteAliment::query()->where('elevage_id', $elevage->id)->orderByDesc('id')->get()
            ->filter(fn (EtiquetteAliment $e) => Gate::forUser($lecteur)->allows('view', $e))
            ->map(fn (EtiquetteAliment $e) => ['etiquette' => $e, 'lecture' => $this->etiquettes->lecture($e)])->values()->all();
    }

    /**
     * Bilan sanitaire d'une campagne et événements déclarés (E09, EF-M1-07).
     *
     * @return array{campagnes: list<string>, campagne: string|null, lignes: list<array<string, mixed>>, evenements: Collection<int, EvenementSanitaire>}
     */
    public function sanitaire(Elevage $elevage, ?string $campagne): array
    {
        $campagnes = IndicateurSanitaire::query()->where('elevage_id', $elevage->id)->distinct()->pluck('campagne_code')
            ->merge(array_filter([VisiteService::campagneDe(today()->toDateString())]))
            ->unique()->sortDesc()->values()->all();
        $campagne = in_array($campagne, $campagnes, true) ? $campagne : ($campagnes[0] ?? null);

        return [
            'campagnes' => $campagnes,
            'campagne' => $campagne,
            'lignes' => $campagne === null ? [] : $this->bilan->lignes($elevage, $campagne),
            'evenements' => EvenementSanitaire::query()->where('elevage_id', $elevage->id)->orderByDesc('date_evenement')->get(),
        ];
    }

    /**
     * Historique (E10, EF-M1-08) : par campagne, les visites lisibles (niveau, messages, décisions du plan) et les lots
     * analysés.
     *
     * @return Collection<string, mixed> par campagne : « visites » (visite, interprétation montrée, messages, actions) et « lots »
     */
    public function historique(Elevage $elevage, User $lecteur): Collection
    {
        $visites = $this->visitesLisibles($elevage, $lecteur)->map(function (Visite $visite) use ($lecteur) {
            $interpretation = $this->interpretationMontree($visite, $lecteur);
            $plan = $interpretation === null ? null : PlanAction::query()->where('interpretation_visite_id', $interpretation->id)->value('id');

            return [
                'visite' => $visite,
                'interpretation' => $interpretation,
                'messages' => $interpretation === null ? collect() : Message::query()->where('interpretation_visite_id', $interpretation->id)->orderBy('ordre')->get(),
                'actions' => $plan === null ? collect() : Action::query()->where('plan_action_id', $plan)->orderBy('priorite')->orderBy('ordre')->get(),
            ];
        });
        $lots = collect($this->lotsLisibles($elevage, $lecteur));
        $campagnes = $visites->map(fn (array $v) => $v['visite']->campagne_code ?? self::SANS_CAMPAGNE)
            ->merge($lots->map(fn (array $l) => $l['prelevement']->campagne_code ?? self::SANS_CAMPAGNE))
            ->unique()->sortDesc()->values();

        return $campagnes->mapWithKeys(fn (string $c) => [$c => [
            'visites' => $visites->filter(fn (array $v) => ($v['visite']->campagne_code ?? self::SANS_CAMPAGNE) === $c)->values()->all(),
            'lots' => $lots->filter(fn (array $l) => ($l['prelevement']->campagne_code ?? self::SANS_CAMPAGNE) === $c)
                ->sortBy(fn (array $l) => $l['prelevement']->date_prelevement->format('Ymd'))->values()->all(),
        ]]);
    }

    /**
     * Visites dont le plan d'action publié est lisible (E11, EF-M2-06), la plus récente d'abord.
     *
     * @return Collection<int, array{visite: Visite, interpretation: InterpretationVisite}>
     */
    public function interpretationsPubliees(Elevage $elevage, User $lecteur): Collection
    {
        return $this->visitesLisibles($elevage, $lecteur)
            ->map(fn (Visite $v) => ['visite' => $v, 'interpretation' => $this->visites->publiee($v)])
            ->filter(fn (array $v) => $v['interpretation'] !== null && Gate::forUser($lecteur)->allows('view', $v['interpretation']))
            ->values();
    }

    /**
     * Mentions de limites des pages de résultat (§ 8.3, x-limites) : textes types en vigueur et version de la grille.
     *
     * @return array{textes: Collection<string, string>, version_grille: string}
     */
    public function limites(): array
    {
        return [
            'textes' => DB::table('ref.texte_type')->whereNull('version_fin')->where('code', 'like', 'fiche.limites.%')->pluck('texte', 'code'),
            'version_grille' => $this->referentiels->versionGrilleCourante(),
        ];
    }

    public function nomUtilisateur(?int $id): ?string
    {
        return $id === null ? null : DB::table('app.user')->where('id', $id)->selectRaw("concat_ws(' ', prenom, nom) as nom")->value('nom');
    }

    /**
     * @return list<array{prelevement: Prelevement, analyse: Analyse, interpretation: Interpretation|null}>
     */
    private function lotsLisibles(Elevage $elevage, User $lecteur): array
    {
        $prelevements = Prelevement::query()->with('typeFourrage')->where('elevage_id', $elevage->id)->get()->keyBy('id');
        $lots = [];

        foreach ($this->analysesLisibles($prelevements->keys(), $lecteur) as $analyse) {
            $lots[] = ['prelevement' => $prelevements[$analyse->prelevement_id], 'analyse' => $analyse, 'interpretation' => $this->interpretationLot($analyse, $lecteur)];
        }

        return $lots;
    }

    /**
     * @param  Collection<int, int>  $prelevementIds
     * @return Collection<int, Analyse>
     */
    private function analysesLisibles(Collection $prelevementIds, User $lecteur): Collection
    {
        return Analyse::query()->whereIn('prelevement_id', $prelevementIds)->where('statut', '!=', Analyse::STATUT_ANNULE)
            ->orderBy('num_echantillon_labo')->get()
            ->filter(fn (Analyse $a) => Gate::forUser($lecteur)->allows('view', $a))->values();
    }
}
