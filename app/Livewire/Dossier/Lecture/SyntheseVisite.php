<?php

namespace App\Livewire\Dossier\Lecture;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Elevage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * E03 — synthèse d'une visite (EF-M1-09) dans l'ordre des six étapes : collecter, analyser, mesurer, croiser, agir,
 * suivre. L'éleveur lit la dernière version publiée ; un agent lit aussi une visite non publiée, signalée comme telle.
 */
class SyntheseVisite extends Component
{
    use VoletDossier;

    #[Locked]
    public int $visiteId;

    public function mount(Elevage $elevage, Visite $visite): void
    {
        abort_unless($visite->elevage_id === $elevage->id, 404);
        $this->authorize('view', $visite);
        $this->monterVolet($elevage);
        $this->visiteId = $visite->id;
    }

    public function render(): View
    {
        $visite = Visite::query()->where('elevage_id', $this->elevageId)->findOrFail($this->visiteId);
        $synthese = $this->lecture()->syntheseVisite($visite, $this->lecteur());
        $interpretation = $synthese['interpretation'] ?? null;

        return $this->volet('synthese-visite', 'Visite du '.$visite->date_visite->format('d/m/Y'), [
            'visite' => $visite,
            'synthese' => $synthese,
            'technicien' => $this->lecture()->nomUtilisateur($visite->technicien_id),
            'lienFiche' => $interpretation === null || $interpretation->fiche_html === null ? null
                : URL::temporarySignedRoute('visites.fiche.pdf', now()->addMinutes(30), ['visite' => $visite->id, 'version' => $interpretation->version]),
            'lienInterpretation' => Gate::allows('create', [InterpretationVisite::class, $visite]) || ($interpretation !== null && Gate::allows('update', $interpretation))
                ? route('visites.interpretation', $visite->id) : null,
        ]);
    }
}
