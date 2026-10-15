<?php

namespace App\Http\Middleware;

use App\Models\App\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ENF-RGPD-02 : la notice d'information est affichée à la première connexion ; tant qu'elle n'a pas été
 * acceptée (acceptation horodatée), l'espace reste fermé.
 */
class NoticeRgpdAcceptee
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->notice_rgpd_acceptee_le === null) {
            return redirect()->guest(route('notice-rgpd'));
        }

        return $next($request);
    }
}
