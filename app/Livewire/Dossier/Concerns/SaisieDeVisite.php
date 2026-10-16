<?php

namespace App\Livewire\Dossier\Concerns;

use App\Models\App\User;
use App\Models\Dossier\Visite;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use RuntimeException;

/**
 * Volet de saisie d'une visite (E03, côté technicien) : la visite est relue à chaque requête à travers les scopes
 * de cloisonnement (404 hors périmètre) et chaque action est autorisée côté serveur (ENF-SEC-04).
 */
trait SaisieDeVisite
{
    #[Locked]
    public int $visiteId;

    public function mount(int $visiteId): void
    {
        $this->visiteId = $visiteId;
        $this->authorize('view', $this->visite());
    }

    protected function visite(): Visite
    {
        return Visite::query()->with('elevage')->findOrFail($this->visiteId);
    }

    protected function modifiable(): bool
    {
        return Gate::allows('update', $this->visite());
    }

    /** Exécute une opération ; un refus métier est affiché sans erreur technique. */
    protected function executer(callable $operation, ?string $message = null): void
    {
        try {
            $operation();

            if ($message !== null) {
                session()->flash('status', $message);
            }
        } catch (SaisieRefusee $e) {
            $this->addError('saisie', $e->getMessage());
        }
    }

    protected function utilisateur(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('Utilisateur non connecté.');
        }

        return $user;
    }

    /**
     * Codes d'un référentiel, pour la validation (la règle « exists » lirait le schéma « ref » comme une connexion).
     *
     * @return list<string>
     */
    protected static function codes(string $table, string $colonne = 'code'): array
    {
        return DB::table($table)->pluck($colonne)->map(fn ($v) => (string) $v)->all();
    }

    protected static function nombreOuNul(mixed $valeur): ?float
    {
        return $valeur === null || $valeur === '' ? null : (float) str_replace(',', '.', (string) $valeur);
    }
}
