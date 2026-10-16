<?php

namespace App\Livewire\Dossier;

use App\Models\App\User;
use App\Models\Dossier\NoteLiaison;
use App\Services\Dossier\NoteLiaisonService;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * Note de liaison, côté vétérinaire destinataire (EF-M2-09) : lecture de la note et de son PDF, saisie du retour.
 * La note est relue à travers les scopes (le consentement révoqué la rend introuvable) et la policy.
 */
class NoteLiaisonVeterinaire extends Component
{
    #[Locked]
    public int $noteId;

    public string $retour = '';

    public function mount(NoteLiaison $note): void
    {
        $this->authorize('view', $note);
        $this->noteId = $note->id;
    }

    public function repondre(NoteLiaisonService $service): void
    {
        $note = NoteLiaison::query()->findOrFail($this->noteId);
        $this->authorize('repondre', $note);
        $this->validate(['retour' => ['required', 'string', 'min:5', 'max:4000']], [], ['retour' => 'retour']);

        try {
            $service->repondre($note, $this->retour, $this->utilisateur());
            session()->flash('status', 'Retour enregistré : le technicien du GDS est prévenu.');
        } catch (SaisieRefusee $e) {
            $this->addError('retour', $e->getMessage());
        }
    }

    public function render(NoteLiaisonService $service): View
    {
        $note = NoteLiaison::query()->findOrFail($this->noteId);

        return view('livewire.dossier.note-liaison-veterinaire', [
            ...$service->donnees($note),
            'peutRepondre' => Gate::allows('repondre', $note),
            'lienPdf' => $note->pdf === null ? null : URL::temporarySignedRoute('notes-liaison.pdf', now()->addMinutes(30), ['note' => $note->id]),
        ])->layout('components.layouts.espace', ['titre' => 'Note de liaison']);
    }

    private function utilisateur(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('Utilisateur non connecté.');
        }

        return $user;
    }
}
