<?php

namespace App\Models\Concerns;

use App\Scopes\ElevageScope;

/**
 * Modèle rattaché à un élevage, soumis au cloisonnement par élevage (chapitre 6.3).
 *
 * - par défaut, la colonne elevage_id du modèle ;
 * - COLONNE_ELEVAGE : autre colonne (l'élevage lui-même déclare 'id', une analyse 'prelevement_id'…) ;
 * - CHEMIN_ELEVAGE : étapes [table, colonne clé, colonne suivante] reliant cette colonne à elevage_id
 *   (ex. analyse : [['fourrage.prelevement', 'id', 'elevage_id']]).
 */
trait CloisonneParElevage
{
    public static function bootCloisonneParElevage(): void
    {
        $colonne = defined(static::class.'::COLONNE_ELEVAGE') ? constant(static::class.'::COLONNE_ELEVAGE') : 'elevage_id';
        $chemin = defined(static::class.'::CHEMIN_ELEVAGE') ? constant(static::class.'::CHEMIN_ELEVAGE') : [];

        static::addGlobalScope(new ElevageScope($colonne, $chemin));
    }
}
