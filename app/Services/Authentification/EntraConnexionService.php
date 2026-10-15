<?php

namespace App\Services\Authentification;

use App\Models\App\User;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Contracts\User as SocialiteUser;

/**
 * Connexion des agents GDS par Entra ID (EF-M0-04, T-31).
 *
 * - Le compte est retrouvé par son object id Entra, ou rattaché par son adresse e-mail s'il existait déjà.
 * - Un agent dont aucun groupe Entra n'est déclaré dans config/entra.php est refusé, à chaque connexion.
 * - Rôles et GDS sont attribués à partir des groupes à la première connexion seulement,
 *   puis gérés dans l'application (ils ne sont pas réécrits aux connexions suivantes).
 */
class EntraConnexionService
{
    public function __construct(private readonly JournalService $journal) {}

    /**
     * @throws CompteEntraRefuse
     */
    public function connecter(SocialiteUser $entra): User
    {
        $oid = (string) $entra->getId();
        $email = $this->email($entra);
        $attributions = $this->attributions($entra);

        if ($attributions === []) {
            $this->journal->ecrire('connexion_refusee', null, null, ['methode' => 'entra', 'motif' => 'hors_groupes', 'email' => $email]);

            throw new CompteEntraRefuse('Votre compte n\'appartient à aucun groupe autorisé pour Focus Troupeau. Contactez l\'administrateur de votre GDS.');
        }

        return DB::transaction(function () use ($oid, $email, $entra, $attributions): User {
            $user = User::query()->where('entra_oid', $oid)->first()
                ?? User::query()->where('email', $email)->whereNull('entra_oid')->first();

            if ($user !== null && ! $user->actif) {
                $this->journal->ecrire('connexion_refusee', $user->id, null, ['methode' => 'entra', 'motif' => 'compte_inactif']);

                throw new CompteEntraRefuse('Votre compte Focus Troupeau est désactivé. Contactez l\'administrateur de votre GDS.');
            }

            $premiereConnexion = $user === null || $user->roles()->doesntExist();

            $user ??= new User;
            $user->forceFill([
                'email' => $email,
                'nom' => $this->valeur($entra, 'surname') ?? (string) $entra->getName(),
                'prenom' => $this->valeur($entra, 'givenName'),
                'entra_oid' => $oid,
            ])->save();

            if ($premiereConnexion) {
                $this->attribuer($user, $attributions);
            }

            return $user;
        });
    }

    /**
     * Rôles et GDS déduits des groupes Entra du jeton d'identité.
     *
     * @return list<array{role: string, gds: int|'*'}>
     */
    private function attributions(SocialiteUser $entra): array
    {
        $groupesConfigures = (array) config('entra.groupes');
        $rolesAutorises = (array) config('entra.roles_autorises');
        $attributions = [];

        foreach ((array) ($this->valeur($entra, 'groups') ?? []) as $groupe) {
            foreach ($groupesConfigures[$groupe] ?? [] as $attribution) {
                if (in_array($attribution['role'], $rolesAutorises, true)) {
                    $attributions[] = $attribution;
                }
            }
        }

        return $attributions;
    }

    /**
     * @param  list<array{role: string, gds: int|'*'}>  $attributions
     */
    private function attribuer(User $user, array $attributions): void
    {
        $roles = array_values(array_unique(array_column($attributions, 'role')));
        $gds = in_array('*', array_column($attributions, 'gds'), true)
            ? DB::table('app.gds')->where('actif', true)->pluck('id')->all()
            : array_values(array_unique(array_column($attributions, 'gds')));

        $user->syncRoles($roles);
        $user->gds()->sync($gds);

        $this->journal->ecrire('attribution_roles', $user->id, 'app.user:'.$user->id, [
            'origine' => 'groupes_entra',
            'roles' => $roles,
            'gds' => $gds,
        ]);
    }

    private function email(SocialiteUser $entra): string
    {
        return mb_strtolower((string) ($this->valeur($entra, 'mail') ?? $entra->getEmail()));
    }

    private function valeur(SocialiteUser $entra, string $cle): mixed
    {
        return method_exists($entra, 'getRaw') ? ($entra->getRaw()[$cle] ?? null) : null;
    }
}
