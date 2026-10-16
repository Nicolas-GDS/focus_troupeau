<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Elevage;
use App\Services\Cloisonnement\Perimetre;
use App\Services\Cloisonnement\RegleStatut;

/**
 * Base des policies du dossier (§ 2.3, § 6.3, ENF-SEC-04) : périmètre de l'élevage, permission du rôle, règle de
 * statut (un compte externe ne lit qu'un objet publié). Une visite publiée ou archivée n'accepte plus de saisie :
 * une correction passe par une nouvelle version de l'interprétation de visite (lot L3, étape 4).
 */
abstract class PolitiqueDossier
{
    public function __construct(
        protected readonly Perimetre $perimetre,
        protected readonly RegleStatut $regleStatut,
    ) {}

    protected function couvre(User $user, Elevage|string $elevage): bool
    {
        return $this->perimetre->couvreElevage($user, $elevage);
    }

    protected function agent(User $user): bool
    {
        return $this->regleStatut->voitTousLesStatuts($user);
    }

    /** Lecture : permission, périmètre, et pour un compte externe, objet publié. */
    protected function lisible(User $user, string $permission, string $elevageId, bool $publie): bool
    {
        return $user->can($permission) && $this->couvre($user, $elevageId) && ($publie || $this->agent($user));
    }

    /** La visite accepte encore des saisies (EF-M1-09). */
    protected function visiteOuverte(?int $visiteId): bool
    {
        if ($visiteId === null) {
            return true;
        }

        $statut = Visite::query()->withoutGlobalScopes()->whereKey($visiteId)->value('statut');

        return in_array($statut, [Visite::STATUT_EN_COURS, Visite::STATUT_INTERPRETEE], true);
    }
}
