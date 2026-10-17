<?php

namespace App\Domain\Autonomie;

use DateTimeImmutable;

/**
 * Mouvement daté d'un lot (RC-11) : achat, vente, ajustement (quantité signée, t MS) ou re-comptage (valeur absolue du
 * stock à cette date).
 */
final readonly class MouvementProjection
{
    public const RECOMPTAGE = 'recomptage';

    public function __construct(
        public string $lotId,
        public DateTimeImmutable $date,
        public string $type,
        public float $quantite,
    ) {}
}
