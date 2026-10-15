<?php

namespace App\Models\Concerns;

use App\Scopes\GdsScope;

/**
 * Modèle portant gds_id, soumis au cloisonnement par GDS (chapitre 6.3).
 */
trait CloisonneParGds
{
    public static function bootCloisonneParGds(): void
    {
        static::addGlobalScope(new GdsScope);
    }
}
