<?php

namespace App\Services;

use App\Models\App\User;

/**
 * Clé de chiffrement du brouillon local (ENF-ACC-03, chapitre 6.1) : dérivée de la clé de l'application et du compte,
 * fournie à la page ; un brouillon laissé sur un téléphone partagé n'est lisible que dans la session de son auteur.
 */
class BrouillonLocal
{
    public static function secret(User $user): string
    {
        return hash_hmac('sha256', 'brouillon-local:'.$user->id, (string) config('app.key'));
    }
}
