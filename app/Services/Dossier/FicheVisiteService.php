<?php

namespace App\Services\Dossier;

use App\Domain\Interpretation\Position;
use App\Models\App\Gds;
use App\Models\Dossier\Action;
use App\Models\Dossier\AnalyseEau;
use App\Models\Dossier\CauseCommune;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\Message;
use App\Models\Dossier\PlanAction;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Interpretation;
use App\Models\Fourrage\Prelevement;
use App\Services\ElevageCourant;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\PositionnementService;
use App\Services\Pdf\RenduPdf;
use App\Services\Pdf\RessourcesCharte;
use Closure;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Fiche de visite figée (EF-M2-07, EF-GEN-08, § 8.4 ; structure du prototype du document 04 : l'essentiel, les lots,
 * la matrice minérale, l'eau, les animaux, le bilan sanitaire, les causes communes, le plan d'action, les limites).
 *
 * À la publication, la fiche est rendue en HTML autonome (polices, logos et pictogrammes intégrés) et archivée ; le PDF
 * est produit depuis ce HTML figé (Chromium) et archivé à côté. Les deux fichiers sont sur le disque privé
 * (fiches/{gds}/{élevage}/visite-{id}-v{version}.html|pdf) ; les colonnes fiche_html et fiche_pdf portent leurs
 * chemins. Le positionnement dans la campagne (RC-3) est figé avec la fiche, à la date de publication (lot L4).
 */
class FicheVisiteService
{
    /** Valeurs du tableau des lots (EF-M1-02, document 04 : MS, UFL, PDI, BPR, MAT). */
    public const PARAMETRES_LOTS = ['MS', 'UFL_2018', 'PDI', 'BPR', 'MAT'];

    /** Colonnes de la matrice de vigilance minérale (EF-M1-03). */
    public const PARAMETRES_MATRICE = ['CU', 'ZN', 'MN', 'NA', 'MG', 'K', 'FE', 'MM', 'BACA', 'SE', 'I', 'CO'];

    /** Éléments dont l'absence de dosage est rappelée dans les limites (document 04, § 3.1). */
    public const NON_DOSES_SIGNALES = ['SE', 'I', 'CO'];

    public function __construct(
        private readonly InterpretationService $interpretations,
        private readonly EauService $eau,
        private readonly MesuresService $mesures,
        private readonly ProfilsService $profils,
        private readonly BilanSanitaireService $bilan,
        private readonly EtiquettesService $etiquettes,
        private readonly RenduPdf $rendu,
        private readonly PositionnementService $positionnement,
    ) {}

    /** Rend et archive le HTML figé de la version ; renvoie son chemin (appelé à la publication). */
    public function figerHtml(InterpretationVisite $interpretation): string
    {
        $chemin = $this->chemin($interpretation, 'html');
        Storage::disk($this->disque())->put($chemin, $this->html($interpretation));
        $interpretation->update(['fiche_html' => $chemin]);

        return $chemin;
    }

    /** Produit et archive le PDF depuis le HTML figé ; renvoie son chemin. */
    public function genererPdf(InterpretationVisite $interpretation): string
    {
        $disque = Storage::disk($this->disque());
        $html = $interpretation->fiche_html === null ? null : $disque->get($interpretation->fiche_html);

        if ($html === null) {
            throw new RuntimeException("Fiche HTML figée introuvable pour l'interprétation de visite {$interpretation->id}.");
        }

        $chemin = $this->chemin($interpretation, 'pdf');
        $disque->put($chemin, $this->rendu->pdf($html, $this->piedDePage($interpretation)));
        $interpretation->update(['fiche_pdf' => $chemin]);

        return $chemin;
    }

    public function html(InterpretationVisite $interpretation): string
    {
        return view('pdf.fiche-visite', [...$this->donnees($interpretation), 'ressources' => RessourcesCharte::toutes()])->render();
    }

    /**
     * Données de la fiche, dans l'ordre de lecture du prototype. Le filtre retient les bulletins lisibles par le
     * lecteur (synthèse de visite E03) ; la fiche figée les prend tous, publiés avec la visite.
     *
     * @param  (Closure(Analyse): bool)|null  $analyseLisible
     * @return array<string, mixed>
     */
    public function donnees(InterpretationVisite $interpretation, ?Closure $analyseLisible = null): array
    {
        $visite = Visite::query()->withoutGlobalScopes()->with('elevage')->findOrFail($interpretation->visite_id);
        $elevage = $visite->elevage;
        $gds = Gds::query()->withoutGlobalScopes()->find($elevage->gds_id);
        $lots = $this->lots($visite, $analyseLisible);
        $doses = array_unique(array_merge(...array_map(fn (array $l) => array_keys(array_filter($l['valeurs'], fn ($v) => $v['valeur'] !== null)), $lots) ?: [[]]));
        $plan = PlanAction::query()->withoutGlobalScopes()->where('interpretation_visite_id', $interpretation->id)->first();

        return [
            'interpretation' => $interpretation,
            'visite' => $visite,
            'elevage' => $elevage,
            'libelleElevage' => ElevageCourant::libelle($elevage->setAttribute('raison_sociale',
                DB::table('prive.elevage_identite')->where('elevage_id', $elevage->id)->value('raison_sociale'))),
            'gds' => $gds,
            'technicien' => $visite->technicien_id === null ? null : DB::table('app.user')->where('id', $visite->technicien_id)
                ->selectRaw("concat_ws(' ', prenom, nom) as nom")->value('nom'),
            'messages' => Message::query()->withoutGlobalScopes()->where('interpretation_visite_id', $interpretation->id)->orderBy('ordre')->get(),
            'lots' => $lots,
            // Positionnement dans la campagne (RC-3, document 04 figures 3 et 4)
            'positionnement' => $this->positionnement->graphiquesVisite(array_map(fn (array $l) => ['analyse' => $l['analyse'], 'prelevement' => $l['prelevement'],
                'libelle' => (string) ($l['prelevement']->nom_lot ?: $l['analyse']->num_echantillon_labo)], $lots)),
            'libellesParametres' => DB::table('ref.parametre')->whereIn('code', [...self::PARAMETRES_LOTS, ...self::PARAMETRES_MATRICE])->pluck('libelle', 'code'),
            'unitesParametres' => DB::table('ref.parametre')->whereIn('code', [...self::PARAMETRES_LOTS, ...self::PARAMETRES_MATRICE])->pluck('unite', 'code'),
            'eau' => AnalyseEau::query()->withoutGlobalScopes()->where('visite_id', $visite->id)->orderBy('date_prelevement')->get()
                ->map(fn (AnalyseEau $a) => ['analyse' => $a, 'lignes' => $this->eau->lignes($a)])->values()->all(),
            'mesures' => $this->mesures->groupes($visite),
            'profils' => $this->profils->lignes($visite),
            'bilan' => $visite->campagne_code === null ? [] : array_values(array_filter($this->bilan->lignes($elevage, $visite->campagne_code), fn ($l) => $l['valeur'] !== null)),
            'etiquettes' => EtiquetteAliment::query()->withoutGlobalScopes()->where('visite_id', $visite->id)->orderBy('id')->get()
                ->map(fn (EtiquetteAliment $e) => ['etiquette' => $e, 'lecture' => $this->etiquettes->lecture($e, $visite->date_visite)])->values()->all(),
            'causes' => CauseCommune::query()->withoutGlobalScopes()->where('interpretation_visite_id', $interpretation->id)->orderBy('id')->get(),
            'actions' => $plan === null ? collect() : Action::query()->withoutGlobalScopes()->where('plan_action_id', $plan->id)
                ->with(['controles' => fn ($q) => $q->withoutGlobalScopes()])->orderBy('priorite')->orderBy('ordre')->get()->groupBy('priorite'),
            'nonDoses' => array_values(array_diff(self::NON_DOSES_SIGNALES, $doses)),
            'textes' => DB::table('ref.texte_type')->whereNull('version_fin')->pluck('texte', 'code'),
            'genereLe' => now(),
        ];
    }

    /** Pied de page Chromium : réseau, version de l'application et de la grille, date de génération, pagination (§ 8.4). */
    public function piedDePage(InterpretationVisite $interpretation): string
    {
        $texte = e(config('brand.nom').' · Membre du réseau GDS France · version '.config('app.version')
            .' · grille v'.$interpretation->version_grille.' · fiche v'.$interpretation->version.' générée le '.now()->format('d/m/Y'));

        return '<div style="width:100%;font-family:sans-serif;font-size:7pt;color:#4A5568;padding:0 12mm;display:flex;justify-content:space-between">'
            .'<span>'.$texte.'</span><span>Page <span class="pageNumber"></span> / <span class="totalPages"></span></span></div>';
    }

    /**
     * Lots rattachés à la visite : interprétation publiée (à défaut, la version en cours, lue par les seuls agents),
     * valeurs du tableau des lots et de la matrice.
     *
     * @param  (Closure(Analyse): bool)|null  $analyseLisible
     * @return list<array{analyse: Analyse, prelevement: Prelevement, interpretation: Interpretation|null, valeurs: array<string, array{valeur: float|null, censure: string|null, classe: string|null, gravite: int|null, decimales: int, unite: string, repere: string|null, source: array{source: string|null, statut: string, a_valider: bool}|null}>, positions: array<string, Position>}>
     */
    private function lots(Visite $visite, ?Closure $analyseLisible = null): array
    {
        $prelevements = Prelevement::query()->withoutGlobalScopes()->with('typeFourrage')->where('visite_id', $visite->id)->get()->keyBy('id');
        $analyses = Analyse::query()->withoutGlobalScopes()->whereIn('prelevement_id', $prelevements->keys())
            ->where('statut', '!=', Analyse::STATUT_ANNULE)->orderBy('num_echantillon_labo')->get();
        $lots = [];

        foreach ($analyses as $analyse) {
            if ($analyseLisible !== null && ! $analyseLisible($analyse)) {
                continue;
            }

            $interpretation = $this->interpretations->publiee($analyse) ?? $this->interpretations->courante($analyse);
            $valeurs = [];
            foreach ($this->interpretations->lignesResultats($analyse, $interpretation?->categorie_cible_code) as $ligne) {
                $valeurs[$ligne['code']] = ['valeur' => $ligne['valeur'], 'censure' => $ligne['censure'], 'classe' => $ligne['classe'],
                    'gravite' => $ligne['gravite'], 'decimales' => $ligne['decimales'], 'unite' => $ligne['unite'], 'repere' => $ligne['repere'],
                    'source' => Affichage::sourceSeuil($ligne['source'], $ligne['statut'], $ligne['a_valider'])];
            }

            $lots[] = ['analyse' => $analyse, 'prelevement' => $prelevements[$analyse->prelevement_id], 'interpretation' => $interpretation, 'valeurs' => $valeurs,
                'positions' => $this->positionnement->positionsPrincipales($analyse)];
        }

        return $lots;
    }

    private function chemin(InterpretationVisite $interpretation, string $extension): string
    {
        $visite = Visite::query()->withoutGlobalScopes()->with('elevage')->findOrFail($interpretation->visite_id);

        return config('fiches.dossier').'/'.$visite->elevage->gds_id.'/'.$visite->elevage_id
            .'/visite-'.$visite->id.'-v'.$interpretation->version.'.'.$extension;
    }

    private function disque(): string
    {
        return (string) config('fiches.disque');
    }
}
