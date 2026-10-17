{{-- Formulaire d'une étiquette (EF-M1-06, trait FormulaireEtiquette) --}}
<form wire:submit="ajouter" class="mt-4 space-y-3">
    <h4 class="font-medium">{{ $titre }}</h4>
    <div class="grid gap-3 sm:grid-cols-3">
        <div>
            <label for="et-nature" class="etiquette">Nature</label>
            <select id="et-nature" wire:model="nature" class="champ w-full">
                @foreach ($natures as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
            </select>
        </div>
        <div class="sm:col-span-2">
            <label for="et-libelle" class="etiquette">Nom inscrit sur l'étiquette</label>
            <input id="et-libelle" type="text" wire:model="libelle" class="champ w-full" maxlength="200">
            @error('libelle') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="et-dose" class="etiquette">Dose (g par animal et par jour)</label>
            <input id="et-dose" type="number" step="any" min="0" inputmode="decimal" wire:model="dose" class="champ w-full">
        </div>
        <div>
            <label for="et-date" class="etiquette">Date limite</label>
            <input id="et-date" type="date" wire:model="dateLimite" class="champ w-full">
        </div>
        <div>
            <label for="et-ms" class="etiquette">Matière sèche (%)</label>
            <input id="et-ms" type="number" step="any" min="0" max="100" inputmode="decimal" wire:model="msPct" class="champ w-full">
            <p class="mt-1 text-xs text-texte-doux">Nécessaire pour l'inscrire dans une ration.</p>
        </div>
        <div>
            <label for="et-mode" class="etiquette">Mode de distribution</label>
            <input id="et-mode" type="text" wire:model="modeDistribution" class="champ w-full" placeholder="sur la ration, libre-service…" maxlength="200">
        </div>
    </div>
    <fieldset class="space-y-2">
        <legend class="etiquette">Composition par kg de produit</legend>
        @foreach ($composition as $i => $ligne)
            <div wire:key="compo-{{ $i }}" class="flex flex-wrap items-end gap-2">
                <label class="sr-only" for="compo-code-{{ $i }}">Élément</label>
                <select id="compo-code-{{ $i }}" wire:model="composition.{{ $i }}.code" class="champ">
                    <option value="">Élément…</option>
                    @foreach ($elements as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                </select>
                <label class="sr-only" for="compo-valeur-{{ $i }}">Teneur</label>
                <input id="compo-valeur-{{ $i }}" type="number" step="any" min="0" inputmode="decimal" wire:model="composition.{{ $i }}.valeur" class="champ w-28" placeholder="teneur">
                <label class="sr-only" for="compo-unite-{{ $i }}">Unité</label>
                <select id="compo-unite-{{ $i }}" wire:model="composition.{{ $i }}.unite" class="champ">
                    @foreach ($unites as $u) <option value="{{ $u }}">{{ $u }}</option> @endforeach
                </select>
                <button type="button" wire:click="retirerLigne({{ $i }})" class="lien text-sm">Retirer</button>
            </div>
        @endforeach
        <button type="button" wire:click="ajouterLigne" class="lien text-sm">Ajouter un élément</button>
        @error('composition.*.code') <p class="text-sm text-etat-critique">{{ $message }}</p> @enderror
    </fieldset>
    <button type="submit" class="bouton-principal">Enregistrer l'étiquette</button>
</form>
