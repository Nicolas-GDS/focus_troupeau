<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Fourrage\Elevage;

/**
 * Étiquettes de minéraux et d'aliments (§ 2.3 : éleveur L/E propre ; technicien L/E/V ; autres rôles L), EF-M1-06.
 * L'éleveur lit ses étiquettes publiées et celles qu'il a saisies, modifie les siennes tant qu'elles ne sont pas
 * validées ; le technicien saisit et valide.
 */
class EtiquetteAlimentPolicy extends PolitiqueDossier
{
    public function view(User $user, EtiquetteAliment $etiquette): bool
    {
        return $user->can('etiquette.lire') && $this->couvre($user, $etiquette->elevage_id)
            && ($etiquette->statut === EtiquetteAliment::STATUT_PUBLIEE || $this->agent($user) || $etiquette->cree_par === $user->id);
    }

    public function create(User $user, Elevage $elevage, ?int $visiteId = null): bool
    {
        return $user->can('etiquette.rediger') && $this->couvre($user, $elevage) && $this->visiteOuverte($visiteId);
    }

    public function update(User $user, EtiquetteAliment $etiquette): bool
    {
        return $user->can('etiquette.rediger') && $this->couvre($user, $etiquette->elevage_id)
            && $etiquette->statut === EtiquetteAliment::STATUT_SAISIE
            && ($this->agent($user) || $etiquette->cree_par === $user->id)
            && $this->visiteOuverte($etiquette->visite_id);
    }

    public function valider(User $user, EtiquetteAliment $etiquette): bool
    {
        return $user->can('etiquette.valider') && $this->update($user, $etiquette);
    }

    public function delete(User $user, EtiquetteAliment $etiquette): bool
    {
        return $this->update($user, $etiquette);
    }
}
