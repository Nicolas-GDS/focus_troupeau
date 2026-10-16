<?php

namespace App\Services\Dossier;

use App\Domain\Dossier\LecteurEau;
use App\Domain\Interpretation\ValeurAnalysee;
use App\Domain\Referentiels\Referentiels;
use App\Models\Dossier\AnalyseEau;
use App\Models\Dossier\ResultatEau;
use App\Models\Dossier\Visite;
use App\Services\Interpretation\InterpretationService;
use Illuminate\Support\Facades\DB;

/**
 * Analyses d'eau saisies manuellement (EF-M1-04, EF-M9-02) : la saisie passe l'analyse en « controle » (aucune plage
 * de plausibilité n'est définie pour l'eau, RC-2 ne visant que les fourrages) ; le technicien la valide ; la lecture
 * classe chaque paramètre par la grille eau (seuils de nature « eau »), avec son repère et sa source.
 */
class EauService
{
    public function __construct(private readonly Referentiels $referentiels) {}

    /**
     * @param  array<string, array{valeur: float, censure?: string|null}>  $resultats  par code de paramètre EAU_*
     */
    public function creer(Visite $visite, string $pointPrelevement, string $datePrelevement, ?int $laboratoireId, array $resultats): AnalyseEau
    {
        if ($resultats === []) {
            throw new SaisieRefusee('Saisissez au moins un résultat.');
        }

        return DB::transaction(function () use ($visite, $pointPrelevement, $datePrelevement, $laboratoireId, $resultats) {
            $analyse = AnalyseEau::query()->create([
                'elevage_id' => $visite->elevage_id,
                'visite_id' => $visite->id,
                'point_prelevement' => trim($pointPrelevement),
                'date_prelevement' => $datePrelevement,
                'laboratoire_id' => $laboratoireId,
                'statut' => AnalyseEau::STATUT_CONTROLE,
            ]);
            $unites = DB::table('ref.parametre')->where('famille_code', 'EAU')->pluck('unite', 'code');

            foreach ($resultats as $code => $resultat) {
                if (! isset($unites[$code])) {
                    throw new SaisieRefusee("Paramètre d'eau inconnu : {$code}.");
                }

                ResultatEau::query()->create([
                    'analyse_eau_id' => $analyse->id,
                    'parametre_code' => $code,
                    'valeur' => $resultat['valeur'],
                    'censure' => $resultat['censure'] ?? null,
                    'unite' => $unites[$code],
                ]);
            }

            return $analyse;
        });
    }

    public function valider(AnalyseEau $analyse): AnalyseEau
    {
        $analyse->update(['statut' => AnalyseEau::STATUT_VALIDE]);

        return $analyse;
    }

    public function supprimer(AnalyseEau $analyse): void
    {
        $analyse->delete();
    }

    /**
     * Résultats classés par la grille eau, avec repère, source et mention « repère à valider ».
     *
     * @return list<array{code: string, libelle: string, valeur: float|null, censure: string|null, unite: string, classe: string|null, gravite: int|null, repere: string|null, source: string|null, statut: string|null, a_valider: bool}>
     */
    public function lignes(AnalyseEau $analyse): array
    {
        $resultats = DB::table('dossier.resultat_eau as r')
            ->join('ref.parametre as p', 'p.code', '=', 'r.parametre_code')
            ->where('r.analyse_eau_id', $analyse->id)
            ->orderBy('p.ordre')
            ->get(['r.parametre_code', 'r.valeur', 'r.censure', 'r.unite', 'p.libelle']);

        $valeurs = $resultats->filter(fn ($r) => $r->valeur !== null)
            ->map(fn ($r) => new ValeurAnalysee((string) $r->parametre_code, (float) $r->valeur))->values()->all();
        $classements = [];
        foreach ((new LecteurEau)->classer($valeurs, $this->referentiels->seuilsActifs()) as $c) {
            $classements[$c->valeur->parametreCode] = $c;
        }
        $sources = Affichage::sources(array_map(fn ($c) => $c->seuil->referenceId, $classements));

        return $resultats->map(function ($r) use ($classements, $sources) {
            $c = $classements[$r->parametre_code] ?? null;

            return [
                'code' => (string) $r->parametre_code,
                'libelle' => (string) $r->libelle,
                'valeur' => $r->valeur === null ? null : (float) $r->valeur,
                'censure' => $r->censure,
                'unite' => (string) $r->unite,
                'classe' => $c?->seuil->classe,
                'gravite' => $c?->seuil->gravite,
                'repere' => $c === null ? null : InterpretationService::repere($c->seuil->borneMin, $c->seuil->borneMax, (string) $r->unite),
                'source' => $c?->seuil->referenceId === null ? null : ($sources[$c->seuil->referenceId] ?? null),
                'statut' => $c === null ? null : $c->seuil->statutEpistemique->libelle().' ['.$c->seuil->statutEpistemique->codeSpecifications().']',
                'a_valider' => $c !== null && ! $c->seuil->valide,
            ];
        })->values()->all();
    }
}
