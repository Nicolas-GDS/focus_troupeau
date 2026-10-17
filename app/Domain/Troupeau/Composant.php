<?php

namespace App\Domain\Troupeau;

/**
 * Composant d'une ration pour le calcul des apports (RC-9) : kg MS par animal et par jour et teneurs par kg MS. Des
 * teneurs nulles signifient « inconnues » (lot de stock non analysé, décision du 17/10/2026) : elles n'équivalent pas à
 * zéro, la couverture n'est alors pas calculée ; un élément absent d'un tableau de teneurs connues vaut zéro.
 */
final readonly class Composant
{
    public const ORIGINE_ANALYSE = 'analyse';

    public const ORIGINE_TABLE = 'table';

    public const ORIGINE_ETIQUETTE = 'etiquette';

    public const ORIGINE_INCONNUE = 'inconnue';

    /**
     * @param  array<string, float>|null  $teneurs  élément → teneur par kg MS (UFL, g ou mg) ; null si inconnues
     */
    public function __construct(
        public string $libelle,
        public float $kgMs,
        public ?array $teneurs,
        public string $origine = self::ORIGINE_TABLE,
    ) {}

    public function teneursConnues(): bool
    {
        return $this->teneurs !== null;
    }
}
