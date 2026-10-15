<?php

namespace App\Domain\Referentiels\Edition;

/**
 * Lecture des valeurs saisies : chaîne vide = absente ; nombres à la virgule ou au point.
 */
final class Valeurs
{
    public static function texte(mixed $valeur): ?string
    {
        if (! is_scalar($valeur)) {
            return null;
        }

        $texte = trim((string) $valeur);

        return $texte === '' ? null : $texte;
    }

    public static function nombre(mixed $valeur): ?float
    {
        $texte = self::texte($valeur);

        if ($texte === null) {
            return null;
        }

        $texte = str_replace([' ', "\u{a0}", "\u{202f}", ','], ['', '', '', '.'], $texte);

        return is_numeric($texte) ? (float) $texte : null;
    }

    public static function entier(mixed $valeur): ?int
    {
        $texte = self::texte($valeur);

        return $texte !== null && preg_match('/^-?\d+$/', $texte) === 1 ? (int) $texte : null;
    }

    /** Une valeur a été saisie mais n'est pas un nombre lisible. */
    public static function nombreIllisible(mixed $valeur): bool
    {
        return self::texte($valeur) !== null && self::nombre($valeur) === null;
    }
}
