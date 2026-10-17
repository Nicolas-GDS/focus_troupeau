<?php

namespace App\Domain\Troupeau;

use App\Domain\Dossier\Repere;

/**
 * Ligne de ref.besoin_journalier retenue pour une catégorie et un stade (RC-9) : par jour (base « animal », ou « par
 * kg de lait » multipliée par la production) ou par kg MS (intervalle) ; avec sa source.
 */
final readonly class LigneBesoin
{
    public const BASE_ANIMAL = 'animal';

    public const BASE_PAR_KG_LAIT = 'par_kg_lait';

    public function __construct(
        public string $parametre,
        public string $mode,
        public string $base,
        public ?float $valeur,
        public ?float $min,
        public ?float $max,
        public ?Repere $repere = null,
    ) {}
}
