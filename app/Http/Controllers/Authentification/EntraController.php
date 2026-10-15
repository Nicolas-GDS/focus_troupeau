<?php

namespace App\Http\Controllers\Authentification;

use App\Http\Controllers\Controller;
use App\Services\Authentification\CompteEntraRefuse;
use App\Services\Authentification\EntraConnexionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;

/**
 * Connexion des agents GDS par Entra ID, OpenID Connect (EF-M0-04, T-31).
 */
class EntraController extends Controller
{
    public function rediriger(): SymfonyRedirectResponse
    {
        abort_unless(config('entra.actif'), 404);

        return Socialite::driver('microsoft')->redirect();
    }

    public function retour(Request $request, EntraConnexionService $entra): RedirectResponse
    {
        abort_unless(config('entra.actif'), 404);

        try {
            $user = $entra->connecter(Socialite::driver('microsoft')->user());
        } catch (CompteEntraRefuse $refus) {
            return redirect()->route('login')->withErrors(['email' => $refus->getMessage()]);
        } catch (InvalidStateException) {
            return redirect()->route('login')->withErrors(['email' => 'La connexion Microsoft a expiré. Veuillez recommencer.']);
        }

        $request->session()->put('connexion.methode', 'entra');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(Fortify::redirects('login'));
    }
}
