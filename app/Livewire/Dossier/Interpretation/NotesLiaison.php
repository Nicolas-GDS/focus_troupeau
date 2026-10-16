<?php

namespace App\Livewire\Dossier\Interpretation;

use App\Models\App\User;
use App\Models\Dossier\NoteLiaison;
use App\Models\Dossier\Visite;
use App\Services\Dossier\InterpretationVisiteService;
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
 * Notes de liaison vétérinaire d'une visite (EF-M2-09, E22 intégré à E21) : rédaction et envoi au vétérinaire désigné,
 * suivi de l'envoi et du retour.
 */
class NotesLiaison extends Component
{
    #[Locked]
    public int $visiteId;

    public string $veterinaire = '';

    public string $lotAnimaux = '';

    public string $question = '';

    public function mount(int $visiteId): void
    {
        $this->visiteId = $visiteId;
        $this->authorize('view', Visite::query()->findOrFail($visiteId));
    }

    public function envoyer(NoteLiaisonService $service, InterpretationVisiteService $interpretations): void
    {
        $visite = Visite::query()->findOrFail($this->visiteId);
        $interpretation = $interpretations->courante($visite) ?? abort(404);
        $this->authorize('create', [NoteLiaison::class, $interpretation]);
        $this->validate([
            'veterinaire' => ['required', 'integer'],
            'lotAnimaux' => ['nullable', 'string', 'max:300'],
            'question' => ['required', 'string', 'min:10', 'max:2000'],
        ], [], ['veterinaire' => 'vétérinaire', 'lotAnimaux' => 'animaux concernés', 'question' => 'question posée']);

        try {
            $service->envoyer($interpretation, User::query()->findOrFail((int) $this->veterinaire), $this->lotAnimaux, $this->question, $this->utilisateur());
            $this->reset('lotAnimaux', 'question');
            session()->flash('note', 'Note de liaison envoyée au vétérinaire.');
        } catch (SaisieRefusee $e) {
            $this->addError('note', $e->getMessage());
        }
    }

    public function render(NoteLiaisonService $service, InterpretationVisiteService $interpretations): View
    {
        $visite = Visite::query()->findOrFail($this->visiteId);
        $interpretation = $interpretations->courante($visite);

        return view('livewire.dossier.interpretation.notes-liaison', [
            'notes' => NoteLiaison::query()->where('elevage_id', $visite->elevage_id)
                ->whereIn('interpretation_visite_id', $interpretations->versions($visite)->pluck('id'))->orderByDesc('id')->get()
                ->filter(fn (NoteLiaison $n) => Gate::allows('view', $n)),
            'peutEnvoyer' => $interpretation !== null && Gate::allows('create', [NoteLiaison::class, $interpretation]),
            'veterinaires' => $service->veterinairesConsentis($visite->elevage_id),
            'lienPdf' => fn (NoteLiaison $n) => URL::temporarySignedRoute('notes-liaison.pdf', now()->addMinutes(30), ['note' => $n->id]),
        ]);
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
