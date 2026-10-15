<?php

namespace App\Services\Authentification;

use App\Models\App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Timebox;

/**
 * Vérification e-mail + mot de passe pour Fortify (Fortify::authenticateUsing), EF-M0-03.
 *
 * Seuls les comptes actifs dotés d'un mot de passe local peuvent se connecter ainsi : les agents
 * GDS passent par Entra ID (EF-M0-04). Durée constante pour ne pas révéler l'existence d'un compte.
 */
class AuthentificationMotDePasse
{
    public function __invoke(Request $request): ?User
    {
        return (new Timebox)->call(function () use ($request): ?User {
            $user = User::query()
                ->where('email', (string) $request->input('email'))
                ->where('actif', true)
                ->whereNotNull('password')
                ->first();

            if ($user === null || ! Hash::check((string) $request->input('password'), (string) $user->password)) {
                return null;
            }

            if (Hash::needsRehash((string) $user->password)) {
                $user->forceFill(['password' => (string) $request->input('password')])->save();
            }

            return $user;
        }, 200_000);
    }
}
