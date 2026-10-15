<?php

namespace App\Scopes;

use App\Services\Cloisonnement\Perimetre;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

/**
 * Cloisonnement par élevage (chapitre 6.3) : restreint un modèle portant elevage_id (ou l'élevage lui-même)
 * aux élevages du périmètre de l'utilisateur courant — éleveur : ses élevages ; vétérinaire d'élevage :
 * les élevages consentis ; agent : les élevages de ses GDS.
 */
class ElevageScope implements Scope
{
    public function __construct(private readonly string $colonne = 'elevage_id') {}

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

        $builder->whereIn($model->qualifyColumn($this->colonne), $perimetre->elevagesAccessibles($user));
    }
}
