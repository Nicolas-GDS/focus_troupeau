<?php

namespace App\Domain\Autonomie;

use DateTimeImmutable;

/**
 * Résultat de la projection (RC-11, RC-12) : besoin prélevé, stock utile, sécurité, bilan, couverture, autonomie,
 * ruptures (globale, par famille, par lot), épuisements, entame de la sécurité, agrégation mensuelle par famille,
 * écarts de recalage.
 */
final readonly class ResultatProjection
{
    /**
     * @param  array<string, float>  $besoinParFamille
     * @param  array<string, DateTimeImmutable|null>  $ruptureParLot
     * @param  array<string, DateTimeImmutable|null>  $epuisementParLot
     * @param  array<string, DateTimeImmutable|null>  $ruptureParFamille
     * @param  list<array{mois: string, stock_debut: float, conso: float, mvts: float, stock_fin: float, familles: array<string, array{stock_debut: float, conso: float, mvts: float, stock_fin: float}>}>  $mensuel
     * @param  list<array{lot: string, date: DateTimeImmutable, projete: float, recompte: float, ecart: float}>  $ecartsRecalage
     */
    public function __construct(
        public float $besoinStock,
        public float $stockUtile,
        public float $securite,
        public float $bilan,
        public float $couverture,
        public int $autonomieJours,
        public int $jours,
        public ?DateTimeImmutable $dateRupture,
        public ?DateTimeImmutable $dateEntameSecurite,
        public array $besoinParFamille,
        public array $ruptureParLot,
        public array $epuisementParLot,
        public array $ruptureParFamille,
        public array $mensuel,
        public array $ecartsRecalage,
    ) {}

    /**
     * Stock corrigé des apports de la période (RC-13.4 : S' = S + Σ gains ; EF-M7-05 : un achat saisi met le bilan à
     * jour) : bilan, couverture et autonomie recalculés sur S', avec les formules de la projection ; ruptures inchangées
     * (la projection journalière tient déjà compte des mouvements datés).
     */
    public function avecApports(float $apports): self
    {
        $stock = $this->stockUtile + $apports;
        $besoinJour = $this->jours > 0 ? $this->besoinStock / $this->jours : 0.0;

        return new self(
            $this->besoinStock, $stock, $this->securite, $stock - $this->besoinStock - $this->securite,
            $this->besoinStock > 0 ? $stock / $this->besoinStock : 0.0, $besoinJour > 0 ? (int) floor($stock / $besoinJour) : 0, $this->jours,
            $this->dateRupture, $this->dateEntameSecurite, $this->besoinParFamille, $this->ruptureParLot, $this->epuisementParLot,
            $this->ruptureParFamille, $this->mensuel, $this->ecartsRecalage,
        );
    }
}
