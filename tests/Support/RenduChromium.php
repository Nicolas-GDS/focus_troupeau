<?php

namespace Tests\Support;

use RuntimeException;

/**
 * Mesure d'un rendu Chromium pour T-26 : les rendus des tests parallèles sont sérialisés par un verrou de fichier.
 * Le seuil de 10 s n'est vérifié qu'en exécution séquentielle : en parallèle, la charge des autres processus de test
 * (PHP, PostgreSQL) fausse la mesure ; le PDF est contrôlé dans les deux cas.
 */
final class RenduChromium
{
    /**
     * @param  callable(): string  $rendu
     * @return array{0: string, 1: float} PDF et durée du rendu en secondes, attente du verrou exclue
     */
    public static function mesurer(callable $rendu): array
    {
        $verrou = fopen(sys_get_temp_dir().'/focus-troupeau-chromium.lock', 'c');
        if ($verrou === false || ! flock($verrou, LOCK_EX)) {
            throw new RuntimeException('Verrou du rendu Chromium impossible.');
        }
        try {
            $debut = microtime(true);
            $pdf = $rendu();

            return [$pdf, microtime(true) - $debut];
        } finally {
            flock($verrou, LOCK_UN);
            fclose($verrou);
        }
    }

    /** Durée de rendu dans le seuil de T-26 ; toujours vrai pendant une exécution parallèle (mesure non significative). */
    public static function dansLeSeuil(float $duree, float $seuil = 10.0): bool
    {
        return getenv('TEST_TOKEN') !== false || $duree < $seuil;
    }
}
