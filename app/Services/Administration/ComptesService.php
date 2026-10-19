<?php

namespace App\Services\Administration;

use App\Models\App\ElevageAcces;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Authentification\LienConnexionService;
use App\Services\Cloisonnement\Perimetre;
use App\Services\Dossier\SaisieRefusee;
use App\Services\JournalService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Comptes (E23, EF-M10-01, § 2.2 à § 2.4) : création, rôles, rattachements aux GDS et aux élevages, activation,
 * invitation. Un administrateur ne gère que les comptes de ses GDS et n'attribue que ses GDS ; seul le
 * super-administrateur attribue le rôle admin_global. Un compte n'est jamais supprimé (désactivation). Toute
 * modification est journalisée (ENF-SEC-08). Le technicien invite les comptes éleveurs des élevages de son périmètre
 * (EF-M0-02).
 */
class ComptesService
{
    public const ROLES = ['eleveur', 'veterinaire', 'technicien', 'veterinaire_gds', 'admin', Perimetre::ROLE_GLOBAL];

    /** Rôles connectés par lien ou mot de passe, invités par e-mail (§ 2.4, EF-M0-05). */
    public const ROLES_INVITES = ['eleveur', 'veterinaire'];

    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly LienConnexionService $liens,
        private readonly JournalService $journal,
    ) {}

    /**
     * Comptes visibles par l'administrateur : rattachés à l'un de ses GDS ou à un élevage de ses GDS, ou sans aucun
     * rattachement.
     *
     * @return Builder<User>
     */
    public function visibles(User $admin): Builder
    {
        $requete = User::query();
        if ($this->perimetre->sansRestriction($admin)) {
            return $requete;
        }
        $gds = $this->perimetre->gdsAccessibles($admin);

        // un compte sans aucun rattachement (créé, pas encore rattaché) reste visible de tous les administrateurs
        return $requete->where(fn ($q) => $q
            ->whereIn('id', DB::table('app.user_gds')->select('user_id')->whereIn('gds_id', $gds))
            ->orWhereIn('id', DB::table('app.elevage_acces as a')->join('fourrage.elevage as e', 'e.id', '=', 'a.elevage_id')->select('a.user_id')->whereIn('e.gds_id', $gds))
            ->orWhere(fn ($q) => $q->whereNotIn('id', DB::table('app.user_gds')->select('user_id'))->whereNotIn('id', DB::table('app.elevage_acces')->select('user_id'))
                ->whereDoesntHave('roles', fn ($r) => $r->where('name', Perimetre::ROLE_GLOBAL))));
    }

    /** @return list<int> GDS que l'administrateur peut attribuer */
    public function gdsAttribuables(User $admin): array
    {
        $requete = DB::table('app.gds')->where('actif', true);
        if (! $this->perimetre->sansRestriction($admin)) {
            $requete->whereIn('id', $this->perimetre->gdsAccessibles($admin));
        }

        return $requete->orderBy('id')->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<string> */
    public function rolesAttribuables(User $admin): array
    {
        return $admin->hasRole(Perimetre::ROLE_GLOBAL) ? self::ROLES : array_values(array_diff(self::ROLES, [Perimetre::ROLE_GLOBAL]));
    }

    /**
     * @param  array{email: string, nom: string, prenom?: string|null, telephone?: string|null, roles: list<string>, gds: list<int>}  $d
     */
    public function creer(array $d, User $admin, bool $inviter = true): User
    {
        if (User::query()->whereRaw('lower(email::text) = ?', [mb_strtolower(trim($d['email']))])->exists()) {
            throw new SaisieRefusee('Un compte existe déjà avec cette adresse e-mail.');
        }
        $this->controler($d['roles'], $d['gds'], $admin);

        $user = DB::transaction(function () use ($d, $admin) {
            $user = User::query()->create(['email' => mb_strtolower(trim($d['email'])), 'nom' => trim($d['nom']), 'prenom' => trim((string) ($d['prenom'] ?? '')) ?: null,
                'telephone' => trim((string) ($d['telephone'] ?? '')) ?: null, 'actif' => true]);
            $user->syncRoles($d['roles']);
            $user->gds()->sync($d['gds']);
            $this->journal->ecrire('creation_compte', $admin->id, 'app.user:'.$user->id, ['roles' => $d['roles'], 'gds' => $d['gds']]);

            return $user;
        });

        if ($inviter && array_intersect($d['roles'], self::ROLES_INVITES) !== []) {
            $this->liens->inviter($user, $admin);
        }

        return $user;
    }

    /**
     * Rôles et GDS : les rôles et GDS hors de la portée de l'administrateur sont conservés tels quels.
     *
     * @param  array{nom: string, prenom?: string|null, telephone?: string|null, roles: list<string>, gds: list<int>}  $d
     */
    public function modifier(User $user, array $d, User $admin): User
    {
        $this->exigerVisible($user, $admin);
        $this->controler($d['roles'], $d['gds'], $admin);
        if ($user->id === $admin->id && array_intersect($user->getRoleNames()->all(), ['admin', Perimetre::ROLE_GLOBAL]) !== []
            && array_intersect($d['roles'], ['admin', Perimetre::ROLE_GLOBAL]) === []) {
            throw new SaisieRefusee('Vous ne pouvez pas retirer votre propre rôle d\'administrateur.');
        }

        return DB::transaction(function () use ($user, $d, $admin) {
            $avant = ['roles' => $user->getRoleNames()->sort()->values()->all(), 'gds' => DB::table('app.user_gds')->where('user_id', $user->id)->orderBy('gds_id')->pluck('gds_id')->map(fn ($i) => (int) $i)->all()];
            $horsPortee = array_values(array_diff($avant['roles'], $this->rolesAttribuables($admin)));
            $gdsHorsPortee = array_values(array_diff($avant['gds'], $this->gdsAttribuables($admin)));
            $roles = array_values(array_unique([...$d['roles'], ...$horsPortee]));
            $gds = array_values(array_unique([...$d['gds'], ...$gdsHorsPortee]));

            $user->update(['nom' => trim($d['nom']), 'prenom' => trim((string) ($d['prenom'] ?? '')) ?: null, 'telephone' => trim((string) ($d['telephone'] ?? '')) ?: null]);
            $user->syncRoles($roles);
            $user->gds()->sync($gds);
            sort($roles);
            sort($gds);
            if ($avant !== ['roles' => $roles, 'gds' => $gds]) {
                $this->journal->ecrire('attribution_roles', $admin->id, 'app.user:'.$user->id, ['avant' => $avant, 'apres' => ['roles' => $roles, 'gds' => $gds]]);
            }

            return $user->refresh();
        });
    }

    public function changerActivation(User $user, bool $actif, User $admin): void
    {
        $this->exigerVisible($user, $admin);
        if ($user->id === $admin->id && ! $actif) {
            throw new SaisieRefusee('Vous ne pouvez pas désactiver votre propre compte.');
        }
        $user->update(['actif' => $actif]);
        $this->journal->ecrire($actif ? 'reactivation_compte' : 'desactivation_compte', $admin->id, 'app.user:'.$user->id);
    }

    public function renvoyerInvitation(User $user, User $auteur): void
    {
        if (! $user->actif || $user->entra_oid !== null) {
            throw new SaisieRefusee('Invitation impossible : compte désactivé ou connecté par Entra ID.');
        }
        $this->liens->inviter($user, $auteur);
        $this->journal->ecrire('invitation_compte', $auteur->id, 'app.user:'.$user->id);
    }

    /** Rattache un compte à un élevage (éleveur, EF-M0-08) ; le vétérinaire passe par un consentement (§ 2.5). */
    public function rattacher(User $user, Elevage $elevage, User $auteur): void
    {
        if (! $this->perimetre->couvreElevage($auteur, $elevage)) {
            throw new SaisieRefusee('Cet élevage est hors de votre périmètre.');
        }
        $user->elevages()->syncWithoutDetaching([$elevage->id => ['role' => ElevageAcces::ROLE_ELEVEUR]]);
        if (! $user->hasRole('eleveur')) {
            $user->assignRole('eleveur');
        }
        $this->journal->ecrire('rattachement_elevage', $auteur->id, 'app.user:'.$user->id, [], $elevage->id);
    }

    public function detacher(User $user, Elevage $elevage, User $auteur): void
    {
        if (! $this->perimetre->couvreElevage($auteur, $elevage)) {
            throw new SaisieRefusee('Cet élevage est hors de votre périmètre.');
        }
        DB::table('app.elevage_acces')->where('user_id', $user->id)->where('elevage_id', $elevage->id)->where('role', ElevageAcces::ROLE_ELEVEUR)->delete();
        $this->journal->ecrire('detachement_elevage', $auteur->id, 'app.user:'.$user->id, [], $elevage->id);
    }

    /**
     * Invitation d'un compte éleveur pour un élevage (EF-M0-02) : compte créé s'il n'existe pas, rattaché sinon.
     * Condition : consentement au traitement des données saisi dans l'identité (§ 2.5).
     */
    public function inviterEleveur(Elevage $elevage, string $email, string $nom, ?string $prenom, User $auteur): User
    {
        if (! $this->perimetre->couvreElevage($auteur, $elevage)) {
            throw new SaisieRefusee('Cet élevage est hors de votre périmètre.');
        }
        if (! DB::table('prive.elevage_identite')->where('elevage_id', $elevage->id)->value('consentement_donnees')) {
            throw new SaisieRefusee('Le consentement au traitement des données doit être recueilli avant de créer le compte (§ 2.5).');
        }
        $email = mb_strtolower(trim($email));
        $user = User::query()->whereRaw('lower(email::text) = ?', [$email])->first();
        if ($user !== null && $user->hasAnyRole([...Perimetre::ROLES_AGENTS, Perimetre::ROLE_GLOBAL])) {
            throw new SaisieRefusee('Cette adresse est celle d\'un agent du GDS.');
        }

        $user = DB::transaction(function () use ($user, $email, $nom, $prenom, $elevage, $auteur) {
            $user ??= User::query()->create(['email' => $email, 'nom' => trim($nom), 'prenom' => trim((string) $prenom) ?: null, 'actif' => true]);
            $user->assignRole('eleveur');
            $user->elevages()->syncWithoutDetaching([$elevage->id => ['role' => ElevageAcces::ROLE_ELEVEUR]]);
            $this->journal->ecrire('invitation_compte', $auteur->id, 'app.user:'.$user->id, ['role' => 'eleveur'], $elevage->id);

            return $user;
        });
        if ($user->password === null && $user->derniere_connexion === null) {
            $this->liens->inviter($user, $auteur);
        }

        return $user;
    }

    /**
     * @param  list<string>  $roles
     * @param  list<int>  $gds
     */
    private function controler(array $roles, array $gds, User $admin): void
    {
        if ($roles === []) {
            throw new SaisieRefusee('Choisir au moins un rôle.');
        }
        if (array_diff($roles, $this->rolesAttribuables($admin)) !== []) {
            throw new SaisieRefusee('Rôle non attribuable.');
        }
        if (array_diff($gds, $this->gdsAttribuables($admin)) !== []) {
            throw new SaisieRefusee('GDS hors de votre périmètre.');
        }
        if (array_intersect($roles, Perimetre::ROLES_AGENTS) !== [] && $gds === [] && ! in_array(Perimetre::ROLE_GLOBAL, $roles, true)) {
            throw new SaisieRefusee('Un agent (technicien, vétérinaire conseil, administrateur) est rattaché à au moins un GDS.');
        }
    }

    private function exigerVisible(User $user, User $admin): void
    {
        if (! $this->visibles($admin)->whereKey($user->id)->exists()) {
            throw new SaisieRefusee('Compte hors de votre périmètre.');
        }
    }
}
