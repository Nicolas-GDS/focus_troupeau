<?php

namespace App\Domain\Dossier;

/**
 * Lecture d'une mesure terrain par rapport à son repère (RC-1.5).
 */
enum LectureMesure: string
{
    case Bas = 'bas';
    case Normal = 'normal';
    case Haut = 'haut';

    public function libelle(): string
    {
        return match ($this) {
            self::Bas => 'sous le repère',
            self::Normal => 'dans le repère',
            self::Haut => 'au-dessus du repère',
        };
    }
}
