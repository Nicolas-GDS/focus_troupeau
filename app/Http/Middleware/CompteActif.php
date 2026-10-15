<?php

namespace App\Http\Middleware;

use App\Models\App\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Un compte désactivé en cours de session est déconnecté à la requête suivante (§ 2.4, ENF-RGPD-04).
 */
class CompteActif
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && ! $user->actif) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'Votre compte a été désactivé. Contactez votre GDS.']);
        }

        return $next($request);
    }
}
