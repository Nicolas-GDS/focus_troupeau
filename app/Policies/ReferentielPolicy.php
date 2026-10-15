<?php

namespace App\Policies;

use App\Models\App\User;
use App\Services\Cloisonnement\Perimetre;

/**
 * Référentiels versionnés (E24, § 2.3 : Référentiels — Admin L/E versionné) : grille de seuils, plages de
 * plausibilité, versions. Les référentiels sont communs à l'instance (aucun GDS de rattachement) : tout
 * administrateur les consulte et, avec la permission referentiel.ecrire, les modifie.
 * Déclarée pour VersionReferentiel, SeuilInterpretation et PlagePlausibilite.
 */
class ReferentielPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->estAdministrateur($user) && $user->can('referentiel.lire');
    }

    /** Créer, modifier ou retirer une ligne, ce qui crée une version (ENF-SEC-04 : vérifié dans chaque action). */
    public function ecrire(User $user): bool
    {
        return $this->estAdministrateur($user) && $user->can('referentiel.ecrire');
    }

    private function estAdministrateur(User $user): bool
    {
        return $user->hasAnyRole(['admin', Perimetre::ROLE_GLOBAL]);
    }
}
