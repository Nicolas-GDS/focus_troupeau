<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Fourrage\AnalyseAnimale;
use App\Models\Fourrage\Elevage;

/**
 * Profils sanguins (§ 2.3 : éleveur L propre publiés ; technicien L/E/V ; vétérinaire conseil et vétérinaire
 * d'élevage consenti L/E ; administrateur L), EF-M1-05. La validation reste au technicien.
 */
class AnalyseAnimalePolicy extends PolitiqueDossier
{
    public function view(User $user, AnalyseAnimale $profil): bool
    {
        return $user->can('profil_sanguin.lire') && $this->couvre($user, $profil->elevage_id)
            && ($profil->statut === AnalyseAnimale::STATUT_PUBLIEE || $this->agent($user) || $user->can('profil_sanguin.rediger'));
    }

    public function create(User $user, Elevage $elevage, ?int $visiteId = null): bool
    {
        return $user->can('profil_sanguin.rediger') && $this->couvre($user, $elevage) && $this->visiteOuverte($visiteId);
    }

    public function update(User $user, AnalyseAnimale $profil): bool
    {
        return $user->can('profil_sanguin.rediger') && $this->couvre($user, $profil->elevage_id)
            && $profil->statut === AnalyseAnimale::STATUT_SAISIE && $this->visiteOuverte($profil->visite_id);
    }

    public function valider(User $user, AnalyseAnimale $profil): bool
    {
        return $user->can('profil_sanguin.valider') && $this->update($user, $profil);
    }

    public function delete(User $user, AnalyseAnimale $profil): bool
    {
        return $this->update($user, $profil);
    }
}
