<div class="carte">
    <h3 class="font-semibold">Minéraux et aliments distribués</h3>
    <p class="mt-1 text-sm text-texte-doux">Apport par animal et par jour = teneur par kg × dose distribuée. La comparaison aux besoins viendra avec les rations (lot L5).</p>
    @error('saisie') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror

    @foreach ($etiquettes as $item)
        <div wire:key="etiquette-{{ $item['etiquette']->id }}" class="mt-4 rounded-md border border-filet p-3">
            @include('livewire.dossier.partiels.etiquette', ['item' => $item, 'datePeremption' => 'à la date de la visite'])
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($item['peutValider']) <button type="button" wire:click="valider({{ $item['etiquette']->id }})" class="bouton-secondaire">Valider l'étiquette</button> @endif
                @if ($item['peutSupprimer']) <button type="button" wire:click="supprimer({{ $item['etiquette']->id }})" wire:confirm="Supprimer cette étiquette ?" class="bouton-secondaire">Supprimer</button> @endif
            </div>
        </div>
    @endforeach

    @if ($etiquettes->isEmpty()) <p class="mt-2 text-sm text-texte-doux">Aucune étiquette saisie.</p> @endif

    @if ($peutAjouter)
        @include('livewire.dossier.partiels.formulaire-etiquette', ['titre' => 'Saisir une étiquette'])
    @endif
</div>
