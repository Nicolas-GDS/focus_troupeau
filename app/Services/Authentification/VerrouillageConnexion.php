<?php

namespace App\Services\Authentification;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

/**
 * Verrouillage progressif de la connexion par mot de passe (ENF-SEC-02).
 *
 * Les échecs sont comptés par couple identifiant + adresse IP (pas d'identifiant seul : un tiers ne doit
 * pas pouvoir bloquer le compte d'un éleveur depuis une autre adresse). À partir du 5e échec, chaque
 * nouvel échec verrouille la connexion pendant une durée qui double, jusqu'à un plafond.
 */
class VerrouillageConnexion
{
    public function __construct(private readonly Cache $cache) {}

    /**
     * Secondes restantes avant de pouvoir réessayer (0 si la connexion est possible).
     */
    public function secondesRestantes(string $identifiant, ?string $ip): int
    {
        $jusqua = $this->cache->get($this->cle($identifiant, $ip).':jusqua');

        return is_int($jusqua) ? max(0, $jusqua - now()->getTimestamp()) : 0;
    }

    /**
     * Enregistre un échec ; renvoie la durée du verrouillage déclenché (0 si aucun).
     */
    public function enregistrerEchec(string $identifiant, ?string $ip): int
    {
        $cle = $this->cle($identifiant, $ip);
        $memoire = now()->addHours((int) config('authentification.verrouillage.memoire_heures'));

        $echecs = (int) $this->cache->get($cle.':echecs', 0) + 1;
        $this->cache->put($cle.':echecs', $echecs, $memoire);

        $seuil = (int) config('authentification.verrouillage.seuil_echecs');
        if ($echecs < $seuil) {
            return 0;
        }

        $duree = min(
            (int) config('authentification.verrouillage.duree_initiale_secondes') * 2 ** ($echecs - $seuil),
            (int) config('authentification.verrouillage.duree_max_secondes'),
        );
        $this->cache->put($cle.':jusqua', now()->getTimestamp() + $duree, $memoire);

        return $duree;
    }

    public function effacer(string $identifiant, ?string $ip): void
    {
        $cle = $this->cle($identifiant, $ip);
        $this->cache->forget($cle.':echecs');
        $this->cache->forget($cle.':jusqua');
    }

    private function cle(string $identifiant, ?string $ip): string
    {
        return 'verrouillage-connexion:'.sha1(Str::lower(trim($identifiant)).'|'.$ip);
    }
}
