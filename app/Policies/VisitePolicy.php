<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Elevage;

/**
 * Visites d'approche globale (§ 2.3 « Dossier élevage », EF-M1-09) : lecture dans le périmètre, les comptes externes
 * ne voyant que les visites publiées (ou archivées après une nouvelle version) ; création et saisie par le technicien
 * tant que la visite n'est pas publiée. Rien n'est supprimé physiquement (EF-GEN-06).
 */
class VisitePolicy extends PolitiqueDossier
{
    public function viewAny(User $user): bool
    {
        return $user->can('visite.lire');
    }

    public function view(User $user, Visite $visite): bool
    {
        return $this->lisible($user, 'visite.lire', $visite->elevage_id,
            in_array($visite->statut, [Visite::STATUT_PUBLIEE, Visite::STATUT_ARCHIVEE], true));
    }

    public function create(User $user, Elevage $elevage): bool
    {
        return $user->can('visite.rediger') && $this->couvre($user, $elevage);
    }

    public function update(User $user, Visite $visite): bool
    {
        return $user->can('visite.rediger') && $this->couvre($user, $visite->elevage_id) && $this->visiteOuverte($visite->id);
    }

    public function delete(User $user, Visite $visite): bool
    {
        return false;
    }
}
