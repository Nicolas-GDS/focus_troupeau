<?php

namespace App\Domain\Troupeau;

/**
 * Bornes des classes de couverture (RC-9), lues dans app.parametre_defaut (couverture.deficit, couverture.limite,
 * couverture.exces).
 */
final readonly class SeuilsCouverture
{
    public function __construct(
        public float $deficit,
        public float $limite,
        public float $exces,
    ) {}
}
