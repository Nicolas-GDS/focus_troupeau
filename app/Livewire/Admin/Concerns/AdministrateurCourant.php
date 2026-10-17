<?php

namespace App\Livewire\Admin\Concerns;

use App\Models\App\User;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Écrans d'administration (E23) : compte connecté et exécution d'une opération avec message de succès ou d'erreur.
 */
trait AdministrateurCourant
{
    protected function admin(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new RuntimeException('Utilisateur non connecté.');
        }

        return $user;
    }

    protected function executer(callable $operation, string $message, string $champ = 'admin'): bool
    {
        try {
            $operation();
            session()->flash('status', $message);

            return true;
        } catch (SaisieRefusee $e) {
            $this->addError($champ, $e->getMessage());

            return false;
        }
    }
}
