<?php

namespace App\Scopes;

use App\Services\Cloisonnement\Perimetre;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * Cloisonnement par élevage (chapitre 6.3) : restreint un modèle aux élevages du périmètre de l'utilisateur courant —
 * éleveur : ses élevages ; vétérinaire d'élevage : les élevages consentis ; agent : les élevages de ses GDS.
 *
 * Le modèle porte l'élevage directement (colonne elevage_id, ou id pour l'élevage lui-même) ou à travers une chaîne
 * de tables : une analyse le tient de son prélèvement, une interprétation de son analyse. Le chemin est une liste
 * d'étapes [table, colonne clé, colonne suivante], de la table référencée par la colonne du modèle jusqu'à elevage_id.
 */
class ElevageScope implements Scope
{
    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $chemin
     */
    public function __construct(
        private readonly string $colonne = 'elevage_id',
        private readonly array $chemin = [],
    ) {}

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

        $builder->whereIn($model->qualifyColumn($this->colonne), $this->sousRequete($perimetre->elevagesAccessibles($user)));
    }

    /**
     * Remonte le chemin depuis les élevages accessibles jusqu'aux clés de la table référencée par le modèle.
     *
     * @param  Closure(QueryBuilder): QueryBuilder  $elevages
     * @return Closure(QueryBuilder): mixed
     */
    private function sousRequete(Closure $elevages): Closure
    {
        $sous = $elevages;

        foreach (array_reverse($this->chemin) as [$table, $cle, $suivante]) {
            $interieur = $sous;
            $sous = fn (QueryBuilder $q) => $q->select($cle)->from($table)->whereIn($suivante, $interieur);
        }

        return $sous;
    }
}
