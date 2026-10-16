<?php

namespace App\Domain\Dossier;

/**
 * Situation d'un indicateur du bilan sanitaire par rapport aux seuils S1 / S2 (EF-M1-07).
 */
enum ClasseBilan: string
{
    case Favorable = 'favorable';
    case Degradee = 'degradee';
    case TresDegradee = 'tres_degradee';

    public function libelle(): string
    {
        return match ($this) {
            self::Favorable => 'Favorable',
            self::Degradee => 'Dégradée',
            self::TresDegradee => 'Très dégradée',
        };
    }

    public function estDegradee(): bool
    {
        return $this !== self::Favorable;
    }
}
