<?php

namespace App\Console\Commands;

use App\Models\App\User;
use App\Services\Authentification\LienConnexionService;
use App\Services\Cloisonnement\Perimetre;
use App\Services\JournalService;
use Illuminate\Console\Command;

/**
 * Premier compte de l'instance (déploiement, docs/exploitation/deploiement.md) : super-administrateur, invité par
 * e-mail à définir son mot de passe ; les autres comptes se créent ensuite dans l'application (E23).
 */
class CreerSuperAdministrateur extends Command
{
    protected $signature = 'compte:super-administrateur {email : adresse du compte} {--nom=Administrateur : nom affiché} {--prenom= : prénom}';

    protected $description = 'Crée le super-administrateur initial et lui envoie l\'invitation d\'activation';

    public function handle(LienConnexionService $liens, JournalService $journal): int
    {
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }
        $user = User::query()->firstOrCreate(['email' => $email], ['nom' => (string) $this->option('nom'), 'prenom' => $this->option('prenom') ?: null, 'actif' => true]);
        $user->assignRole(Perimetre::ROLE_GLOBAL);
        $journal->ecrire('attribution_roles', null, 'app.user:'.$user->id, ['apres' => ['roles' => [Perimetre::ROLE_GLOBAL]], 'origine' => 'console']);
        if ($user->password === null && $user->entra_oid === null) {
            $liens->inviter($user);
            $this->info("Super-administrateur {$email} créé ; invitation envoyée.");
        } else {
            $this->info("Rôle de super-administrateur attribué à {$email}.");
        }

        return self::SUCCESS;
    }
}
