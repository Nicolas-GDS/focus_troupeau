<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\Visite;
use App\Services\Dossier\InterpretationVisiteService;

/**
 * Interprétation de visite (§ 2.3 « Interprétations et alertes », EF-M2-04 à EF-M2-07) : lecture dans le périmètre,
 * les comptes externes ne lisant que la version publiée (une version archivée reste consultable par les agents) ;
 * rédaction, validation et publication par le technicien ; visa du niveau retenu 3 par le vétérinaire conseil.
 */
class InterpretationVisitePolicy extends PolitiqueDossier
{
    public function view(User $user, InterpretationVisite $interpretation): bool
    {
        return $this->lisible($user, 'interpretation.lire', $this->elevage($interpretation),
            $interpretation->statut === InterpretationVisite::STATUT_PUBLIE);
    }

    public function create(User $user, Visite $visite): bool
    {
        return $user->can('interpretation.rediger') && $this->couvre($user, $visite->elevage_id)
            && $visite->statut !== Visite::STATUT_ARCHIVEE;
    }

    public function update(User $user, InterpretationVisite $interpretation): bool
    {
        return $user->can('interpretation.rediger') && $interpretation->statut === InterpretationVisite::STATUT_BROUILLON
            && $this->couvre($user, $this->elevage($interpretation));
    }

    public function valider(User $user, InterpretationVisite $interpretation): bool
    {
        return $this->update($user, $interpretation);
    }

    public function viser(User $user, InterpretationVisite $interpretation): bool
    {
        return $user->can('interpretation.viser') && $interpretation->statut === InterpretationVisite::STATUT_VALIDE
            && $interpretation->niveau_global === 3 && $this->couvre($user, $this->elevage($interpretation));
    }

    public function refuserVisa(User $user, InterpretationVisite $interpretation): bool
    {
        return $this->viser($user, $interpretation);
    }

    public function publier(User $user, InterpretationVisite $interpretation): bool
    {
        return $user->can('interpretation.rediger') && InterpretationVisiteService::publiable($interpretation)
            && $this->couvre($user, $this->elevage($interpretation));
    }

    /** Nouvelle version depuis la version publiée (EF-M2-07). */
    public function nouvelleVersion(User $user, InterpretationVisite $interpretation): bool
    {
        return $user->can('interpretation.rediger') && $interpretation->statut === InterpretationVisite::STATUT_PUBLIE
            && $this->couvre($user, $this->elevage($interpretation));
    }

    /** Rien n'est supprimé physiquement (EF-GEN-06). */
    public function delete(User $user, InterpretationVisite $interpretation): bool
    {
        return false;
    }

    private function elevage(InterpretationVisite $interpretation): string
    {
        return (string) Visite::query()->withoutGlobalScopes()->whereKey($interpretation->visite_id)->value('elevage_id');
    }
}
