<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Dossier\MesureTerrain;
use App\Models\Fourrage\Elevage;

/**
 * Mesures terrain (§ 2.3 : éleveur L propre publiées ; technicien L/E/V ; autres rôles L), EF-M1-05.
 * Saisie, correction et suppression tant que la mesure est « saisie » ; validation par le technicien.
 */
class MesureTerrainPolicy extends PolitiqueDossier
{
    public function view(User $user, MesureTerrain $mesure): bool
    {
        return $this->lisible($user, 'mesure_terrain.lire', $mesure->elevage_id, $mesure->statut === MesureTerrain::STATUT_PUBLIEE);
    }

    public function create(User $user, Elevage $elevage, ?int $visiteId = null): bool
    {
        return $user->can('mesure_terrain.rediger') && $this->couvre($user, $elevage) && $this->visiteOuverte($visiteId);
    }

    public function update(User $user, MesureTerrain $mesure): bool
    {
        return $user->can('mesure_terrain.rediger') && $this->couvre($user, $mesure->elevage_id)
            && $mesure->statut === MesureTerrain::STATUT_SAISIE && $this->visiteOuverte($mesure->visite_id);
    }

    public function valider(User $user, MesureTerrain $mesure): bool
    {
        return $this->update($user, $mesure);
    }

    public function delete(User $user, MesureTerrain $mesure): bool
    {
        return $this->update($user, $mesure);
    }
}
