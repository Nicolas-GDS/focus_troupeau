<?php

namespace App\Actions\Authentification;

use App\Services\Authentification\VerrouillageConnexion;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Étape du pipeline de connexion Fortify : refuse toute tentative pendant un verrouillage (ENF-SEC-02).
 */
class VerifierVerrouillage
{
    public function __construct(private readonly VerrouillageConnexion $verrouillage) {}

    public function handle(Request $request, Closure $next): mixed
    {
        $secondes = $this->verrouillage->secondesRestantes((string) $request->input('email'), $request->ip());

        if ($secondes > 0) {
            throw ValidationException::withMessages([
                'email' => [trans('auth.throttle', ['seconds' => $secondes, 'minutes' => (int) ceil($secondes / 60)])],
            ]);
        }

        return $next($request);
    }
}
