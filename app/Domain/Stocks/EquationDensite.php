<?php

namespace App\Domain\Stocks;

/**
 * Équations de densité des silos (RC-5, Idele, classeur bilan fourrager 2020 [T]).
 */
enum EquationDensite: string
{
    case MaisCouloir = 'mais_couloir';
    case MaisTaupiniere = 'mais_taupe';
    case SorghoCouloir = 'sorgho_couloir';
    case SorghoTaupiniere = 'sorgho_taupe';
    case HerbeBrinsCourts = 'herbe_courts';
    case HerbeBrinsLongs = 'herbe_longs';
    case HerbeCouloir = 'herbe_couloir';
    case HerbeTaupiniere = 'herbe_taupe';

    public function herbe(): bool
    {
        return in_array($this, [self::HerbeBrinsCourts, self::HerbeBrinsLongs, self::HerbeCouloir, self::HerbeTaupiniere], true);
    }

    public function sorgho(): bool
    {
        return $this === self::SorghoCouloir || $this === self::SorghoTaupiniere;
    }

    public function libelle(): string
    {
        return match ($this) {
            self::MaisCouloir => 'Maïs, silo couloir',
            self::MaisTaupiniere => 'Maïs, taupinière',
            self::SorghoCouloir => 'Sorgho plante entière, silo couloir',
            self::SorghoTaupiniere => 'Sorgho plante entière, taupinière',
            self::HerbeBrinsCourts => 'Herbe, brins courts',
            self::HerbeBrinsLongs => 'Herbe, brins longs',
            self::HerbeCouloir => 'Herbe, silo couloir (à défaut)',
            self::HerbeTaupiniere => 'Herbe, taupinière (à défaut)',
        };
    }
}
