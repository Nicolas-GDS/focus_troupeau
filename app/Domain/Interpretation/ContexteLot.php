<?php

namespace App\Domain\Interpretation;

/**
 * Le lot analysé, vu par la grille : type et famille de fourrage, espèce et catégorie animale cible (facultatives).
 */
final readonly class ContexteLot
{
    public function __construct(
        public string $typeFourrageCode,
        public string $familleFourrageCode,
        public ?string $especeCode = null,
        public ?string $categorieCode = null,
    ) {}

    /**
     * Contexte d'une analyse sans fourrage (eau d'abreuvement, lot L3) : seuls les seuils qui ne visent aucun type
     * ni aucune famille de fourrage, aucune espèce ni catégorie s'appliquent.
     */
    public static function horsFourrage(): self
    {
        return new self('', '');
    }
}
