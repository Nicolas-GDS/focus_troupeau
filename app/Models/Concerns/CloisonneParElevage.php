<?php

namespace App\Models\Concerns;

use App\Scopes\ElevageScope;

/**
 * Modèle portant elevage_id, soumis au cloisonnement par élevage (chapitre 6.3).
 * L'élevage lui-même déclare COLONNE_ELEVAGE = 'id'.
 */
trait CloisonneParElevage
{
    public static function bootCloisonneParElevage(): void
    {
        $colonne = defined(static::class.'::COLONNE_ELEVAGE') ? constant(static::class.'::COLONNE_ELEVAGE') : 'elevage_id';

        static::addGlobalScope(new ElevageScope($colonne));
    }
}
