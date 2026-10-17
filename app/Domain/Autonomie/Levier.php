<?php

namespace App\Domain\Autonomie;

use InvalidArgumentException;

/**
 * Catalogue des leviers (RC-13.3) : gain en t MS de fourrage et coût. Codes du § 5.5 (plan.levier_ligne.levier_code).
 * Implémentation de référence : calculs/, levier() (T-15).
 */
final class Levier
{
    public const ACHAT = 'achat_fourrage';

    public const CONCENTRE = 'concentre_substitution';

    public const DECAPITALISATION = 'decapitalisation';

    public const GENISSES = 'genisses';

    public const PATURAGE = 'paturage';

    public const RATELIER = 'ratelier';

    public const REAFFECTATION = 'reaffectation';

    public const AUTRE = 'autre';

    public const CODES = [self::ACHAT, self::CONCENTRE, self::DECAPITALISATION, self::GENISSES, self::PATURAGE, self::RATELIER, self::REAFFECTATION, self::AUTRE];

    public function __construct(private readonly ParametresLeviers $parametres) {}

    /**
     * @param  string|null  $famille  famille achetée (achat) ou famille de base de la ration (concentré)
     * @param  array{jours: int, besoin_jour_t_ms: float, besoin_ration_t_ms: float, pertes_distribution: float}  $contexte
     * @return array{gain: float, cout: float}
     */
    public function evaluer(string $code, float $quantite, float $parametre, ?string $famille, array $contexte): array
    {
        [$gain, $cout] = match ($code) {
            self::ACHAT, self::REAFFECTATION => [$quantite, $code === self::ACHAT ? $quantite * $parametre : 0.0],
            self::CONCENTRE => [$quantite * ($this->parametres->substitution[$famille ?? ''] ?? throw new InvalidArgumentException("Famille de base inconnue pour un concentré : {$famille}.")), $quantite * $parametre],
            self::DECAPITALISATION => [$quantite * $this->parametres->kgMsParUgbJour * $contexte['jours'] / 1000.0, $quantite * $parametre],
            self::GENISSES => [$quantite * $parametre, 0.0],
            self::PATURAGE => [$quantite * $contexte['besoin_jour_t_ms'], $parametre],
            self::RATELIER => [max(0.0, $contexte['besoin_ration_t_ms'] * (1 / (1 - $contexte['pertes_distribution']) - 1 / (1 - $parametre))), 0.0],
            self::AUTRE => [$quantite, $parametre],
            default => throw new InvalidArgumentException("Levier inconnu : {$code}."),
        };

        return ['gain' => $gain, 'cout' => $cout];
    }
}
