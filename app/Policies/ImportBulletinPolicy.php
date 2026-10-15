<?php

namespace App\Policies;

use App\Models\App\ImportBulletin;
use App\Models\App\User;
use App\Services\Cloisonnement\Perimetre;

/**
 * Dépôts de bulletins (E19, EF-M9-01) : réservés au technicien, dans les GDS de son périmètre.
 */
class ImportBulletinPolicy
{
    public function __construct(private readonly Perimetre $perimetre) {}

    public function viewAny(User $user): bool
    {
        return $user->can('bulletin.importer');
    }

    public function view(User $user, ImportBulletin $import): bool
    {
        return $user->can('bulletin.importer') && $this->couvreGds($user, $import->gds_id);
    }

    /**
     * Déposer pour un GDS donné.
     */
    public function create(User $user, int $gdsId): bool
    {
        return $user->can('bulletin.importer') && $this->couvreGds($user, $gdsId);
    }

    public function rapprocher(User $user, ImportBulletin $import): bool
    {
        return $this->view($user, $import) && $import->statut === 'a_rapprocher';
    }

    private function couvreGds(User $user, int $gdsId): bool
    {
        return $this->perimetre->sansRestriction($user) || $user->gds()->whereKey($gdsId)->exists();
    }
}
