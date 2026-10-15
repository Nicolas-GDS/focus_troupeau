<?php

namespace App\Policies;

use App\Models\App\Consentement;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Cloisonnement\Perimetre;

/**
 * Consentements « références » et « vétérinaire » (§ 2.5, EF-M0-07, ENF-RGPD-03) : l'éleveur gère ceux de ses
 * élevages ; l'administrateur les saisit pour son GDS ; le technicien les lit ; les vétérinaires n'y ont pas accès.
 */
class ConsentementPolicy
{
    public function __construct(private readonly Perimetre $perimetre) {}

    public function viewAny(User $user): bool
    {
        return $user->can('consentement.lire');
    }

    public function view(User $user, Consentement $consentement): bool
    {
        return $user->can('consentement.lire') && $this->perimetre->couvreElevage($user, $consentement->elevage_id);
    }

    /**
     * Accorder un consentement pour un élevage donné.
     */
    public function create(User $user, Elevage $elevage): bool
    {
        return $this->gere($user, $elevage->getKey());
    }

    /**
     * Révoquer : horodaté, jamais supprimé (§ 2.5).
     */
    public function revoquer(User $user, Consentement $consentement): bool
    {
        return $consentement->estActif() && $this->gere($user, $consentement->elevage_id);
    }

    public function delete(User $user, Consentement $consentement): bool
    {
        return false;
    }

    private function gere(User $user, string $elevageId): bool
    {
        if ($user->can('consentement.ecrire')) {
            return $this->perimetre->couvreElevage($user, $elevageId);
        }

        return $user->can('consentement.gerer_propre') && $this->perimetre->estEleveurDe($user, $elevageId);
    }
}
