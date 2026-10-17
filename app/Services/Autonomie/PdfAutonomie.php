<?php

namespace App\Services\Autonomie;

use App\Domain\Referentiels\Referentiels;
use App\Domain\Referentiels\StatutEpistemique;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\Scenario;
use App\Models\Stock\Inventaire;
use App\Services\ElevageCourant;
use App\Services\Notifications\TextesTypes;
use App\Services\Pdf\RenduPdf;
use App\Services\Pdf\RessourcesCharte;
use Illuminate\Support\Facades\DB;

/**
 * PDF du bilan d'autonomie et d'un scénario d'approvisionnement (EF-M6-06, EF-GEN-08, T-26) : charte bi-entité,
 * tableaux, graphique, hypothèses et valeurs par défaut employées avec leurs sources, mentions de limites, version du
 * référentiel. Produits à la demande (non archivés : le bilan suit les saisies).
 */
class PdfAutonomie
{
    public function __construct(
        private readonly EcranBilan $ecran,
        private readonly ApprovisionnementService $approvisionnement,
        private readonly BilanService $bilans,
        private readonly Referentiels $referentiels,
        private readonly TextesTypes $textes,
        private readonly RenduPdf $rendu,
    ) {}

    public function bilanHtml(Inventaire $inventaire): string
    {
        return view('pdf.bilan-autonomie', [...$this->commun($inventaire), 'ecran' => $this->ecran->donnees($inventaire)])->render();
    }

    public function scenarioHtml(Scenario $scenario, bool $publiesSeulement = false): string
    {
        $inventaire = Inventaire::query()->withoutGlobalScopes()->findOrFail($scenario->inventaire_id);

        return view('pdf.scenario', [
            ...$this->commun($inventaire),
            'scenario' => $scenario,
            'evaluation' => $this->approvisionnement->evaluer($scenario, $publiesSeulement),
            'sansScenario' => $this->bilans->calculer($inventaire)['centrale'],
            'libelles' => ApprovisionnementService::LIBELLES,
            'sources' => $this->sourcesLeviers($inventaire),
        ])->render();
    }

    public function bilan(Inventaire $inventaire): string
    {
        return $this->rendu->pdf($this->bilanHtml($inventaire), $this->piedDePage('bilan d\'autonomie du '.now()->format('d/m/Y')));
    }

    public function scenario(Scenario $scenario, bool $publiesSeulement = false): string
    {
        return $this->rendu->pdf($this->scenarioHtml($scenario, $publiesSeulement), $this->piedDePage('scénario « '.$scenario->libelle.' » du '.now()->format('d/m/Y')));
    }

    public function piedDePage(string $objet): string
    {
        $texte = e(config('brand.nom').' · Membre du réseau GDS France · '.$objet.' · version '.config('app.version'));

        return '<div style="width:100%;font-family:sans-serif;font-size:7pt;color:#4A5568;padding:0 12mm;display:flex;justify-content:space-between">'
            .'<span>'.$texte.'</span><span>Page <span class="pageNumber"></span> / <span class="totalPages"></span></span></div>';
    }

    /**
     * Valeurs par défaut des leviers (RC-13.3) avec leur source et leur statut ; valeur de l'élevage si elle a été
     * modifiée (E16).
     *
     * @return list<array{libelle: string, valeur: float, unite: string, source: string|null, statut: string|null, propre: bool}>
     */
    private function sourcesLeviers(Inventaire $inventaire): array
    {
        $gds = DB::table('fourrage.elevage')->where('id', $inventaire->elevage_id)->value('gds_id');
        $cles = [...array_values(ApprovisionnementService::PRIX), ...array_values(ApprovisionnementService::SUBSTITUTION),
            'decapitalisation.perte_eur_ugb', 'genisse.t_ms_par_tete', 'ugb.kg_ms_par_ugb_jour'];
        $propres = DB::table('app.parametre_elevage')->where('elevage_id', $inventaire->elevage_id)->whereIn('cle', $cles)->pluck('valeur', 'cle');
        $lignes = [];
        foreach ($cles as $cle) {
            $r = DB::table('app.parametre_defaut')->where('cle', $cle)->where(fn ($q) => $q->whereNull('gds_id')->orWhere('gds_id', $gds))
                ->whereNull('valide_au')->orderByRaw('gds_id IS NULL')->orderByDesc('valide_du')->first();
            if ($r === null) {
                continue;
            }
            $statut = StatutEpistemique::tryFrom((string) $r->statut_epistemique);
            $lignes[] = [
                'libelle' => (string) $r->libelle, 'valeur' => (float) ($propres[$cle] ?? $r->valeur), 'unite' => (string) $r->unite, 'source' => $r->source,
                'statut' => $statut === null ? null : $statut->libelle().' ['.$statut->codeSpecifications().']', 'propre' => isset($propres[$cle]),
            ];
        }

        return $lignes;
    }

    /** @return array<string, mixed> */
    private function commun(Inventaire $inventaire): array
    {
        $elevage = Elevage::query()->withoutGlobalScopes()->findOrFail($inventaire->elevage_id);
        $elevage->setAttribute('raison_sociale', DB::table('prive.elevage_identite')->where('elevage_id', $elevage->id)->value('raison_sociale'));

        return [
            'elevage' => $elevage, 'libelleElevage' => ElevageCourant::libelle($elevage), 'inventaire' => $inventaire,
            'gds' => DB::table('app.gds')->where('id', $elevage->gds_id)->first(), 'ressources' => RessourcesCharte::toutes(),
            'version' => $this->referentiels->versionGrilleCourante(), 'limites' => $this->textes->texte('autonomie.limites'),
            'familles' => DB::table('ref.famille_fourrage')->pluck('libelle', 'code'),
        ];
    }
}
