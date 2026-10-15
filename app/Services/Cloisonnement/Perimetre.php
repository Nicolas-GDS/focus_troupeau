<?php

namespace App\Services\Cloisonnement;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Périmètre d'accès d'un utilisateur (chapitre 6.3, § 2.2) : l'ensemble des élevages et des GDS qu'il peut atteindre.
 *
 * - admin_global : toute l'instance ;
 * - agent (technicien, vétérinaire conseil, administrateur) : les élevages des GDS de app.user_gds ;
 * - éleveur : les élevages de app.elevage_acces (rôle eleveur) ;
 * - vétérinaire d'élevage : les élevages de app.elevage_acces (rôle veterinaire) dont le consentement
 *   « vétérinaire » le désigne et n'est pas révoqué — la révocation prend effet à la requête suivante.
 *
 * Sans utilisateur connecté : aucune restriction en console (tâches planifiées, files, commandes),
 * aucun accès dans une requête web (refus par défaut).
 *
 * Les sous-requêtes sont construites avec le Query Builder (jamais Eloquent) pour ne pas déclencher
 * les scopes de cloisonnement en cascade.
 */
class Perimetre
{
    public const ROLES_AGENTS = ['technicien', 'veterinaire_gds', 'admin'];

    public const ROLE_GLOBAL = 'admin_global';

    private readonly bool $console;

    public function __construct(?bool $console = null)
    {
        $this->console = $console ?? app()->runningInConsole();
    }

    public function utilisateurCourant(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * Vrai si aucune restriction ne s'applique (super-administrateur, ou traitement sans utilisateur en console).
     */
    public function sansRestriction(?User $user): bool
    {
        return $user === null ? $this->console : $user->hasRole(self::ROLE_GLOBAL);
    }

    public function estAgent(User $user): bool
    {
        return $user->hasAnyRole(self::ROLES_AGENTS);
    }

    /**
     * Sous-requête des identifiants d'élevages accessibles.
     *
     * @return Closure(Builder): Builder
     */
    public function elevagesAccessibles(?User $user): Closure
    {
        if ($user === null) {
            return fn (Builder $q) => $q->select('id')->from('fourrage.elevage')->whereRaw('false');
        }

        if ($this->estAgent($user)) {
            return fn (Builder $q) => $q->select('e.id')->from('fourrage.elevage as e')
                ->whereIn('e.gds_id', $this->gdsDeRattachement($user));
        }

        return fn (Builder $q) => $q->select('ea.elevage_id')->from('app.elevage_acces as ea')
            ->where('ea.user_id', $user->id)
            ->where(fn (Builder $role) => $role
                ->where('ea.role', 'eleveur')
                ->orWhere(fn (Builder $veterinaire) => $veterinaire
                    ->where('ea.role', 'veterinaire')
                    ->whereExists(fn (Builder $c) => $c->selectRaw('1')->from('app.consentement as c')
                        ->whereColumn('c.id', 'ea.consentement_id')
                        ->where('c.type', 'veterinaire')
                        ->whereColumn('c.veterinaire_user_id', 'ea.user_id')
                        ->whereNull('c.revoque_le'))));
    }

    /**
     * Sous-requête des identifiants de GDS accessibles : GDS de rattachement pour un agent,
     * GDS des élevages accessibles pour un éleveur ou un vétérinaire d'élevage.
     *
     * @return Closure(Builder): Builder
     */
    public function gdsAccessibles(?User $user): Closure
    {
        if ($user !== null && $this->estAgent($user)) {
            return $this->gdsDeRattachement($user);
        }

        return fn (Builder $q) => $q->select('e.gds_id')->from('fourrage.elevage as e')
            ->whereIn('e.id', $this->elevagesAccessibles($user));
    }

    public function couvreElevage(User $user, Elevage|string $elevage): bool
    {
        if ($this->sansRestriction($user)) {
            return true;
        }

        $id = $elevage instanceof Elevage ? $elevage->getKey() : $elevage;

        return DB::query()->fromSub(fn (Builder $q) => $this->elevagesAccessibles($user)($q), 'acces')
            ->where('acces.'.($this->estAgent($user) ? 'id' : 'elevage_id'), $id)
            ->exists();
    }

    /**
     * L'utilisateur est-il rattaché à l'élevage en tant qu'éleveur (et non comme vétérinaire ou agent) ?
     */
    public function estEleveurDe(User $user, Elevage|string $elevage): bool
    {
        $id = $elevage instanceof Elevage ? $elevage->getKey() : $elevage;

        return DB::table('app.elevage_acces')
            ->where('user_id', $user->id)
            ->where('elevage_id', $id)
            ->where('role', 'eleveur')
            ->exists();
    }

    /**
     * @return Closure(Builder): Builder
     */
    private function gdsDeRattachement(User $user): Closure
    {
        return fn (Builder $q) => $q->select('gds_id')->from('app.user_gds')->where('user_id', $user->id);
    }
}
