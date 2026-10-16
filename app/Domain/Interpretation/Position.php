<?php

namespace App\Domain\Interpretation;

/**
 * Position d'une valeur dans une population de référence (RC-3) : faible (< Q1), normal (Q1 à Q3 inclus),
 * élevé (> Q3), ou référence insuffisante (n < references.n_min).
 */
enum Position: string
{
    case Faible = 'faible';
    case Normal = 'normal';
    case Eleve = 'eleve';
    case Insuffisante = 'insuffisante';

    public function libelle(): string
    {
        return match ($this) {
            self::Faible => 'Faible',
            self::Normal => 'Normal',
            self::Eleve => 'Élevé',
            self::Insuffisante => 'Référence insuffisante',
        };
    }
}
