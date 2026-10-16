<?php

namespace App\Domain\Referentiels;

use App\Domain\Dossier\LienTroubleCause;
use App\Domain\Dossier\RegleLecture;
use App\Domain\Dossier\RepereMesureTerrain;
use App\Domain\Dossier\RepereProfilSanguin;
use App\Domain\Dossier\SeuilBilanSanitaire;
use App\Domain\Dossier\TroubleCroisement;
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

    /**
     * Repères des mesures terrain en vigueur (RC-1.5).
     *
     * @return list<RepereMesureTerrain>
     */
    public function reperesMesuresTerrain(): array;

    /**
     * Règles de lecture combinée des mesures terrain en vigueur (RC-1.5).
     *
     * @return list<RegleLecture>
     */
    public function reglesLectureMesures(): array;

    /**
     * Repères des profils sanguins en vigueur (RC-1.5).
     *
     * @return list<RepereProfilSanguin>
     */
    public function reperesProfilsSanguins(): array;

    /**
     * Seuils S1 / S2 du bilan sanitaire en vigueur (EF-M1-07).
     *
     * @return list<SeuilBilanSanitaire>
     */
    public function seuilsBilanSanitaire(): array;

    /**
     * Troubles de la grille de croisement et leurs correspondances (EF-M2-05).
     *
     * @return list<TroubleCroisement>
     */
    public function troublesCroisement(): array;

    /**
     * Grille trouble × famille de cause en vigueur (EF-M2-05).
     *
     * @return list<LienTroubleCause>
     */
    public function liensTroubleCause(): array;

    /** Efface le cache après une modification des référentiels (E24). */
    /**
     * Codes des paramètres positionnés dans les références (EF-M3-01), dans l'ordre du référentiel.
     *
     * @return list<string>
     */
    public function parametresSuivis(): array;

    public function oublier(): void;
}
