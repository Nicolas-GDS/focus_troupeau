<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité (ENF-SEC-01) : HSTS sur HTTPS, politique de sécurité du contenu, interdiction de l'affichage
 * en cadre, type de contenu strict, référent limité, fonctions du navigateur désactivées. La CSP autorise les scripts
 * de l'application (Vite, Livewire) et l'évaluation d'expressions requise par Alpine (Livewire 3) ; les styles en
 * ligne des graphiques SVG ; les images et polices locales ou intégrées. En développement, le serveur Vite est
 * ajouté. Mode configurable (securite.csp : enforce, report, off).
 */
class EnTetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $reponse = $next($request);
        $entetes = $reponse->headers;

        $entetes->set('X-Frame-Options', 'DENY');
        $entetes->set('X-Content-Type-Options', 'nosniff');
        $entetes->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $entetes->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(self), payment=()');
        if ($request->isSecure()) {
            $entetes->set('Strict-Transport-Security', 'max-age='.(int) config('securite.hsts_secondes').'; includeSubDomains');
        }

        $mode = (string) config('securite.csp');
        if ($mode !== 'off' && ! $entetes->has('Content-Security-Policy')) {
            $entetes->set($mode === 'report' ? 'Content-Security-Policy-Report-Only' : 'Content-Security-Policy', $this->politique());
        }

        return $reponse;
    }

    private function politique(): string
    {
        $vite = array_filter(array_map('trim', explode(',', (string) config('securite.csp_sources_dev'))));
        $sources = fn (string ...$s) => implode(' ', [...$s, ...$vite]);

        return implode('; ', [
            "default-src 'self'",
            'script-src '.$sources("'self'", "'unsafe-eval'"),
            'style-src '.$sources("'self'", "'unsafe-inline'"),
            "img-src 'self' data: blob:",
            "font-src 'self' data:",
            'connect-src '.$sources("'self'", ...array_map(fn ($u) => preg_replace('#^http#', 'ws', $u) ?? $u, $vite)),
            "frame-ancestors 'none'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ]);
    }
}
