<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Interpretation;
use App\Services\Cloisonnement\Perimetre;
use App\Services\Cloisonnement\RegleStatut;
use App\Services\Interpretation\InterpretationService;
use Illuminate\Support\Facades\DB;

/**
 * Interprétations et alertes (§ 2.3 : éleveur L propre au statut publie ; technicien L/E/V niveaux 0-2 ;
 * vétérinaire conseil L/V niveau 3 ; vétérinaire d'élevage L ; administrateur L), ENF-SEC-04, EF-M2-04.
 *
 * Lecture dans le périmètre (§ 6.3) ; les comptes externes (éleveur, vétérinaire d'élevage) ne lisent qu'une
 * interprétation publiée (règle de statut). Rédaction, validation et publication des niveaux 0 à 2 : technicien ;
 * publication d'un niveau 3 : technicien, après le visa du vétérinaire conseil.
 */
class InterpretationPolicy
{
    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly RegleStatut $regleStatut,
    ) {}

    /**
     * Ouvrir l'écran d'interprétation d'un bulletin (E20) : un compte externe n'y accède qu'une fois l'interprétation publiée.
     */
    public function viewAny(User $user, Analyse $analyse): bool
    {
        return $user->can('interpretation.lire')
            && $this->couvre($user, $analyse->prelevement_id)
            && ($this->regleStatut->voitTousLesStatuts($user) || DB::table('fourrage.interpretation')
                ->where('analyse_id', $analyse->id)
                ->where('statut', RegleStatut::PUBLIE)
                ->exists());
    }

    public function view(User $user, Interpretation $interpretation): bool
    {
        return $user->can('interpretation.lire')
            && $this->couvreInterpretation($user, $interpretation)
            && $this->regleStatut->peutVoir($user, $interpretation->statut);
    }

    /** Créer le brouillon d'un bulletin contrôlé (EF-M2-02). */
    public function create(User $user, Analyse $analyse): bool
    {
        return $user->can('interpretation.rediger')
            && $analyse->statut === Analyse::STATUT_CONTROLE
            && $this->couvre($user, $analyse->prelevement_id);
    }

    /** Modifier (alertes, lectures, catégorie cible, niveau retenu) : brouillon seulement. */
    public function update(User $user, Interpretation $interpretation): bool
    {
        return $user->can('interpretation.rediger')
            && $interpretation->statut === Interpretation::STATUT_BROUILLON
            && $this->couvreInterpretation($user, $interpretation);
    }

    public function valider(User $user, Interpretation $interpretation): bool
    {
        return $this->update($user, $interpretation);
    }

    /** Visa du vétérinaire conseil : interprétation validée de niveau 3 (EF-M2-04). */
    public function viser(User $user, Interpretation $interpretation): bool
    {
        return $user->can('interpretation.viser')
            && $interpretation->statut === Interpretation::STATUT_VALIDE
            && $interpretation->niveau_global === 3
            && $this->couvreInterpretation($user, $interpretation);
    }

    /** Refus de visa : mêmes conditions que le visa (décision du 15/10/2026). */
    public function refuserVisa(User $user, Interpretation $interpretation): bool
    {
        return $this->viser($user, $interpretation);
    }

    /** Publication (EF-M2-04, T-05) : niveaux 0-2 depuis « valide », niveau 3 depuis « visee ». */
    public function publier(User $user, Interpretation $interpretation): bool
    {
        return $user->can('interpretation.rediger')
            && InterpretationService::publiable($interpretation)
            && $this->couvreInterpretation($user, $interpretation);
    }

    private function couvreInterpretation(User $user, Interpretation $interpretation): bool
    {
        $prelevementId = DB::table('fourrage.analyse')->where('id', $interpretation->analyse_id)->value('prelevement_id');

        return $prelevementId !== null && $this->couvre($user, (int) $prelevementId);
    }

    private function couvre(User $user, int $prelevementId): bool
    {
        $elevageId = DB::table('fourrage.prelevement')->where('id', $prelevementId)->value('elevage_id');

        return $elevageId !== null && $this->perimetre->couvreElevage($user, (string) $elevageId);
    }
}
