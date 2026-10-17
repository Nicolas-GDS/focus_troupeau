<?php

namespace App\Domain\Autonomie;

use DateTimeImmutable;

/**
 * Lot de stock pour la projection (RC-11) : famille, stock utile à l'inventaire, ordre de consommation (priorité de
 * l'utilisateur, puis date de récolte croissante, puis lots non analysés avant analysés), incertitude relative (RC-14).
 */
final readonly class LotProjection
{
    public function __construct(
        public string $id,
        public string $famille,
        public float $stockUtile,
        public ?DateTimeImmutable $dateRecolte = null,
        public int $priorite = 0,
        public bool $analyse = false,
        public float $incertitude = 0.0,
    ) {}

    public function avecStock(float $stock): self
    {
        return new self($this->id, $this->famille, $stock, $this->dateRecolte, $this->priorite, $this->analyse, $this->incertitude);
    }
}
