<?php

namespace App\Domain\Dossier;

/**
 * Lecture d'un élément d'un profil sanguin (RC-1.5).
 */
enum LectureProfil: string
{
    case CarenceSevere = 'carence_severe';
    case Carence = 'carence';
    case NormalBas = 'normal_bas';
    case Normal = 'normal';
    case Exces = 'exces';

    public function libelle(): string
    {
        return match ($this) {
            self::CarenceSevere => 'Carence sévère',
            self::Carence => 'Carence',
            self::NormalBas => 'Normal, partie basse',
            self::Normal => 'Normal',
            self::Exces => 'Excès',
        };
    }
}
