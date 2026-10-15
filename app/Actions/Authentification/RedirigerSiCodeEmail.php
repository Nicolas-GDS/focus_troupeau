<?php

namespace App\Actions\Authentification;

use App\Services\Authentification\AuthentificationMotDePasse;
use App\Services\Authentification\CodeVerificationService;
use Closure;
use Illuminate\Auth\Events\Failed;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Étape du pipeline de connexion Fortify : si le compte a activé la double authentification
 * (code par e-mail, EF-M0-03, ENF-SEC-03), le mot de passe juste ne suffit pas — un code est
 * envoyé et la connexion se termine sur l'écran de saisie du code.
 */
class RedirigerSiCodeEmail
{
    public function __construct(
        private readonly AuthentificationMotDePasse $authentification,
        private readonly CodeVerificationService $codes,
    ) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $user = ($this->authentification)($request);

        if ($user === null) {
            event(new Failed(config('fortify.guard'), null, ['email' => $request->input('email')]));

            throw ValidationException::withMessages(['email' => [trans('auth.failed')]]);
        }

        if (! $user->deux_facteurs_email) {
            return $next($request);
        }

        $request->session()->put('connexion.code', [
            'user_id' => $user->id,
            'remember' => $request->boolean('remember'),
        ]);
        $this->codes->envoyer($user);

        return redirect()->route('connexion.code');
    }
}
