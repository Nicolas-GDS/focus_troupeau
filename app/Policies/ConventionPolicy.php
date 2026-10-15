<?php

namespace App\Policies;

use App\Models\App\Convention;
use App\Models\App\User;
use App\Services\Cloisonnement\Perimetre;

/**
 * Conventions (§ 2.3, § 2.4, EF-M0-02, EF-M0-06) : création et prolongation par le technicien ou l'administrateur.
 */
class ConventionPolicy
{
    public function __construct(private readonly Perimetre $perimetre) {}

    public function viewAny(User $user): bool
    {
        return $user->can('convention.lire');
    }

    public function view(User $user, Convention $convention): bool
    {
        return $user->can('convention.lire') && $this->perimetre->couvreElevage($user, $convention->elevage_id);
    }

    public function create(User $user): bool
    {
        return $user->can('convention.ecrire');
    }

    public function update(User $user, Convention $convention): bool
    {
        return $user->can('convention.ecrire') && $this->perimetre->couvreElevage($user, $convention->elevage_id);
    }

    /** Une convention n'est jamais supprimée : elle est résiliée ou échue (§ 2.4). */
    public function delete(User $user, Convention $convention): bool
    {
        return false;
    }
}
