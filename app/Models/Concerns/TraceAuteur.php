<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Renseigne cree_par et maj_par avec l'utilisateur connecté (EF-GEN-06).
 * Hors session (tâche planifiée, import), les colonnes restent telles que fournies.
 */
trait TraceAuteur
{
    public static function bootTraceAuteur(): void
    {
        static::creating(function (Model $modele): void {
            $auteur = Auth::id();

            if ($auteur !== null) {
                $modele->setAttribute('cree_par', $modele->getAttribute('cree_par') ?? $auteur);
                $modele->setAttribute('maj_par', $auteur);
            }
        });

        static::updating(function (Model $modele): void {
            $auteur = Auth::id();

            if ($auteur !== null) {
                $modele->setAttribute('maj_par', $auteur);
            }
        });
    }
}
