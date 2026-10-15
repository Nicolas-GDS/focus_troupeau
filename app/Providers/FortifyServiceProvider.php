<?php

namespace App\Providers;

use App\Actions\Authentification\RedirigerSiCodeEmail;
use App\Actions\Authentification\VerifierVerrouillage;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Services\Authentification\AuthentificationMotDePasse;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\AttemptToAuthenticate;
use Laravel\Fortify\Actions\CanonicalizeUsername;
use Laravel\Fortify\Actions\PrepareAuthenticatedSession;
use Laravel\Fortify\Fortify;

/**
 * Fortify : connexion par mot de passe, réinitialisation et changement de mot de passe (chapitre 6.1, EF-M0-03).
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Routes déclarées en français dans routes/authentification.php (annexe 10).
        Fortify::ignoreRoutes();
    }

    public function boot(): void
    {
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        Fortify::authenticateUsing(app(AuthentificationMotDePasse::class));

        Fortify::authenticateThrough(fn (Request $request) => [
            VerifierVerrouillage::class,
            CanonicalizeUsername::class,
            RedirigerSiCodeEmail::class,
            AttemptToAuthenticate::class,
            PrepareAuthenticatedSession::class,
        ]);

        Fortify::loginView(fn () => view('authentification.connexion'));
        Fortify::requestPasswordResetLinkView(fn () => view('authentification.mot-de-passe-oublie'));
        Fortify::resetPasswordView(fn (Request $request) => view('authentification.reinitialisation', ['request' => $request]));
    }
}
