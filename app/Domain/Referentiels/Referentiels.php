<?php

namespace App\Domain\Referentiels;

use App\Domain\Interpretation\LienCompatibilite;
use App\Domain\Interpretation\PlagePlausibilite;
use App\Domain\Interpretation\Seuil;

/**
 * Accès en lecture aux référentiels (chapitre 6.2) : grille de seuils, plages de plausibilité, paramètres par
 * défaut, correspondances. Toute constante métier vient d'ici (CLAUDE.md : aucune constante codée en dur).
 */
interface Referentiels
{
    /**
     * Seuils actifs de la grille courante (RC-1).
     *
     * @return list<Seuil>
     */
    public function seuilsActifs(): array;

    /**
     * Plages de plausibilité actives (RC-2).
     *
     * @return list<PlagePlausibilite>
     */
    public function plagesPlausibiliteActives(): array;

    /** Numéro de la version courante de la grille (ref.version_referentiel). */
    public function versionGrilleCourante(): string;

    /**
     * Valeur par défaut en vigueur (app.parametre_defaut) : celle du GDS si elle existe, sinon celle de l'instance.
     */
    public function parametre(string $cle, ?int $gdsId = null): ?float;

    /** Famille d'un type de fourrage (ref.type_fourrage). */
    public function familleDuType(string $typeFourrageCode): string;

    /** Espèce d'une catégorie animale (ref.categorie_animale). */
    public function especeDeLaCategorie(string $categorieCode): ?string;

    /**
     * Liens de compatibilité paramètre × trouble en vigueur (RC-1.4, chapitre 11).
     *
     * @return list<LienCompatibilite>
     */
    public function liensCompatibilite(): array;

    /** Efface le cache après une modification des référentiels (E24). */
    public function oublier(): void;
}
