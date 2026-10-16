<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Dossier\Action;
use App\Models\Dossier\Controle;
use App\Models\Dossier\InterpretationVisite;
use Illuminate\Support\Facades\DB;

/**
 * Plan d'action et contrôles (§ 2.3 : éleveur L propre, E sur le statut « fait » des actions qui lui incombent ;
 * technicien L/E/V ; vétérinaire conseil L/V des actions vétérinaires ; autres rôles L), EF-M2-06.
 * La rédaction suit l'interprétation de visite (brouillon) ; le suivi (fait, résultat des contrôles) se fait sur le
 * plan publié. Sert aussi aux contrôles (Gate::policy de Controle).
 */
class ActionPolicy extends PolitiqueDossier
{
    public function view(User $user, Action|Controle $objet): bool
    {
        [$elevage, $statut] = $this->contexte($objet);

        return $this->lisible($user, 'plan_action.lire', $elevage, $statut === InterpretationVisite::STATUT_PUBLIE);
    }

    public function update(User $user, Action|Controle $objet): bool
    {
        [$elevage, $statut] = $this->contexte($objet);

        return $user->can('plan_action.rediger') && $statut === InterpretationVisite::STATUT_BROUILLON && $this->couvre($user, $elevage);
    }

    public function delete(User $user, Action|Controle $objet): bool
    {
        return $this->update($user, $objet);
    }

    public function validerVeterinaire(User $user, Action $action): bool
    {
        [$elevage, $statut] = $this->contexte($action);

        return $user->can('plan_action.valider_veterinaire') && $action->responsable === 'veterinaire'
            && in_array($statut, [InterpretationVisite::STATUT_BROUILLON, InterpretationVisite::STATUT_VALIDE, InterpretationVisite::STATUT_VISEE], true)
            && $this->couvre($user, $elevage);
    }

    public function marquerFaite(User $user, Action $action): bool
    {
        [$elevage, $statut] = $this->contexte($action);

        return $user->can('plan_action.marquer_fait') && $action->responsable === 'eleveur' && $action->statut === Action::STATUT_A_FAIRE
            && $statut === InterpretationVisite::STATUT_PUBLIE && $this->perimetre->estEleveurDe($user, $elevage);
    }

    public function saisirResultat(User $user, Controle $controle): bool
    {
        [$elevage, $statut] = $this->contexte($controle);

        return $user->can('plan_action.rediger') && $statut === InterpretationVisite::STATUT_PUBLIE && $this->couvre($user, $elevage);
    }

    /**
     * Élevage et statut de l'interprétation de visite de l'action ou du contrôle.
     *
     * @return array{0: string, 1: string}
     */
    private function contexte(Action|Controle $objet): array
    {
        $actionId = $objet instanceof Action ? $objet->id : $objet->action_id;
        $ligne = DB::table('dossier.action as a')
            ->join('dossier.plan_action as p', 'p.id', '=', 'a.plan_action_id')
            ->join('dossier.interpretation_visite as i', 'i.id', '=', 'p.interpretation_visite_id')
            ->join('dossier.visite as v', 'v.id', '=', 'i.visite_id')
            ->where('a.id', $actionId)
            ->first(['v.elevage_id', 'i.statut']);

        return [(string) ($ligne->elevage_id ?? ''), (string) ($ligne->statut ?? '')];
    }
}
