<?php

namespace App\Services\Cloisonnement;

use App\Models\App\User;

/**
 * Règle de statut (§ 2.3, § 6.3) : un éleveur ne voit un objet (analyse, interprétation, mesure…) que lorsqu'il
 * est publié ; brouillons et objets validés non publiés lui restent invisibles. Les agents GDS voient tous les statuts.
 *
 * Le vétérinaire d'élevage suit la même règle que l'éleveur (choix du lot L1, à confirmer : la matrice du § 2.3
 * lui donne « L » sans préciser le statut).
 */
class RegleStatut
{
    public const PUBLIE = 'publie';

    public function __construct(private readonly Perimetre $perimetre) {}

    public function peutVoir(User $user, string $statut): bool
    {
        return $this->voitTousLesStatuts($user) || $statut === self::PUBLIE;
    }

    public function voitTousLesStatuts(User $user): bool
    {
        return $user->hasRole(Perimetre::ROLE_GLOBAL) || $this->perimetre->estAgent($user);
    }
}
