<?php

namespace App\Scopes;

use App\Services\Cloisonnement\Perimetre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Cloisonnement par GDS (chapitre 6.3, EF-M0-01) : restreint un modèle portant gds_id aux GDS
 * du périmètre de l'utilisateur courant. Le super-administrateur n'est pas restreint ; pour une requête
 * transversale explicite (références RC-3, tâches), utiliser withoutGlobalScope(GdsScope::class).
 */
class GdsScope implements Scope
{
    /**
     * @param  Builder<Model>  $builder
     */
    public function apply(Builder $builder, Model $model): void
    {
        $perimetre = app(Perimetre::class);
        $user = $perimetre->utilisateurCourant();

        if ($perimetre->sansRestriction($user)) {
            return;
        }

        $builder->whereIn($model->qualifyColumn('gds_id'), $perimetre->gdsAccessibles($user));
    }
}
