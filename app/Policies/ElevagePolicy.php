<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Cloisonnement\Perimetre;
use App\Services\Cloisonnement\RegleConvention;

/**
 * Dossier élevage (§ 2.3) et droit d'écriture dans les modules stocks et plan (D12, EF-M0-06, T-32).
 */
class ElevagePolicy
{
    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly RegleConvention $convention,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('elevage.lire');
    }

    public function view(User $user, Elevage $elevage): bool
    {
        return $user->can('elevage.lire') && $this->perimetre->couvreElevage($user, $elevage);
    }

    public function create(User $user): bool
    {
        return $user->can('elevage.ecrire');
    }

    public function update(User $user, Elevage $elevage): bool
    {
        return $user->can('elevage.ecrire') && $this->perimetre->couvreElevage($user, $elevage);
    }

    /** Rien n'est supprimé physiquement (principe 5 du § 1.6). */
    public function delete(User $user, Elevage $elevage): bool
    {
        return false;
    }

    /**
     * Écriture dans stock.* (inventaires, lots de stock, mouvements) : convention active obligatoire (D12, T-32).
     */
    public function ecrireStocks(User $user, Elevage $elevage): bool
    {
        return $user->can('stock.ecrire')
            && $this->perimetre->couvreElevage($user, $elevage)
            && $this->convention->estActive($elevage);
    }

    /**
     * Écriture dans plan.* (lots d'animaux, rations, scénarios) : convention active obligatoire (D12, T-32).
     */
    public function ecrirePlan(User $user, Elevage $elevage): bool
    {
        return $user->can('plan.ecrire')
            && $this->perimetre->couvreElevage($user, $elevage)
            && $this->convention->estActive($elevage);
    }
}
