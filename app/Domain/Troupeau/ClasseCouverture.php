<?php

namespace App\Domain\Troupeau;

/**
 * Classes de couverture (RC-9 [CP]) : déficit, limite, couvert, excès, maximum réglementaire dépassé.
 */
enum ClasseCouverture: string
{
    case Deficit = 'deficit';
    case Limite = 'limite';
    case Couvert = 'couvert';
    case Exces = 'exces';
    case MaximumDepasse = 'maximum_depasse';

    public function libelle(): string
    {
        return match ($this) {
            self::Deficit => 'Déficit',
            self::Limite => 'Limite',
            self::Couvert => 'Couvert',
            self::Exces => 'Excès',
            self::MaximumDepasse => 'Maximum dépassé',
        };
    }
}
