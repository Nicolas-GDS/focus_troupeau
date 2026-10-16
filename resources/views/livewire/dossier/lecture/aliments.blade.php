<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'dossier.aliments'])

    <section class="carte" aria-labelledby="titre-etiquettes">
        <h2 id="titre-etiquettes" class="text-lg font-semibold">Minéraux et aliments distribués</h2>
        <p class="mt-1 text-sm text-texte-doux">Apport par animal et par jour = teneur par kg × dose distribuée. La comparaison aux besoins de vos animaux viendra avec les rations (lot L5).</p>
        @error('saisie') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror

        @forelse ($etiquettes as $item)
            <div wire:key="etiquette-{{ $item['etiquette']->id }}" class="mt-4 rounded-md border border-filet p-3">
                @include('livewire.dossier.partiels.etiquette', ['item' => $item, 'datePeremption' => 'à ce jour'])
            </div>
        @empty
            <p class="mt-2 text-sm text-texte-doux">Aucune étiquette à afficher.</p>
        @endforelse

        @if ($peutAjouter)
            @include('livewire.dossier.partiels.formulaire-etiquette', ['titre' => 'Ajouter une étiquette'])
        @endif
    </section>

    <x-limites :limites="$limites" />
</div>
