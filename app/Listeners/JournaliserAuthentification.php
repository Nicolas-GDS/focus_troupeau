<?php

namespace App\Listeners;

use App\Models\App\User;
use App\Services\Authentification\VerrouillageConnexion;
use App\Services\JournalService;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Journal des connexions (ENF-SEC-08) et comptage des échecs pour le verrouillage progressif (ENF-SEC-02).
 * Le mot de passe saisi n'est jamais journalisé.
 */
class JournaliserAuthentification
{
    public function __construct(
        private readonly JournalService $journal,
        private readonly VerrouillageConnexion $verrouillage,
        private readonly Request $request,
    ) {}

    public function connexion(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof User) {
            return;
        }

        $methode = $this->request->hasSession()
            ? (string) $this->request->session()->pull('connexion.methode', 'mot_de_passe')
            : 'mot_de_passe';

        DB::table('app.user')->where('id', $user->id)->update(['derniere_connexion' => now()]);
        $this->verrouillage->effacer($user->email, $this->request->ip());
        $this->journal->ecrire('connexion', $user->id, null, ['methode' => $methode]);
    }

    public function echec(Failed $event): void
    {
        $email = (string) ($event->credentials['email'] ?? '');
        $duree = $this->verrouillage->enregistrerEchec($email, $this->request->ip());

        $this->journal->ecrire('connexion_echec', $event->user instanceof User ? $event->user->id : null, null, [
            'methode' => 'mot_de_passe',
            'email' => $email,
        ]);

        if ($duree > 0) {
            $this->journal->ecrire('verrouillage_connexion', null, null, ['email' => $email, 'duree_secondes' => $duree]);
        }
    }

    public function deconnexion(Logout $event): void
    {
        if ($event->user instanceof User) {
            $this->journal->ecrire('deconnexion', $event->user->id);
        }
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'connexion',
            Failed::class => 'echec',
            Logout::class => 'deconnexion',
        ];
    }
}
