<?php

namespace App\Services\Cloisonnement;

use App\Models\App\User;
use Illuminate\Support\Facades\DB;

/**
 * Un compte peut-il ouvrir une session (EF-M0-05) ?
 *
 * Un compte désactivé ne le peut jamais. Un compte de vétérinaire d'élevage (rôle veterinaire seul, sans rôle
 * d'agent) n'est actif que lorsque au moins un consentement « vétérinaire » non révoqué le désigne.
 */
class AccesCompte
{
    public function __construct(private readonly Perimetre $perimetre) {}

    public function peutSeConnecter(User $user): bool
    {
        if (! $user->actif) {
            return false;
        }

        if ($this->estVeterinaireElevageSeulement($user)) {
            return DB::table('app.consentement')
                ->where('type', 'veterinaire')
                ->where('veterinaire_user_id', $user->id)
                ->whereNull('revoque_le')
                ->exists();
        }

        return true;
    }

    private function estVeterinaireElevageSeulement(User $user): bool
    {
        return $user->hasRole('veterinaire')
            && ! $this->perimetre->estAgent($user)
            && ! $user->hasRole(Perimetre::ROLE_GLOBAL)
            && ! $user->hasRole('eleveur');
    }
}
