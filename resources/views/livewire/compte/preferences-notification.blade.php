<section class="carte" aria-labelledby="titre-preferences">
    <h2 id="titre-preferences" class="text-lg font-semibold">Préférences de notification</h2>
    @if (session('preferences'))
        <p class="mt-2 text-sm text-etat-couvert" role="status">{{ session('preferences') }}</p>
    @endif
    <form wire:submit="enregistrer" class="mt-3 space-y-3">
        <label class="flex items-center gap-2"><input type="checkbox" wire:model="email"> Recevoir un e-mail quand une fiche ou un résultat est publié</label>
        <label class="flex items-center gap-2"><input type="checkbox" wire:model="sms"> Recevoir des SMS</label>
        <div>
            <label for="telephone" class="etiquette">Numéro de mobile</label>
            <input id="telephone" type="tel" inputmode="tel" autocomplete="tel" wire:model="telephone" class="champ w-full sm:w-64" maxlength="20">
            @error('telephone') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
        </div>
        <p class="text-sm text-texte-doux">Les alertes de niveau 3 (situation critique) vous sont toujours envoyées par e-mail et par SMS.</p>
        <button type="submit" class="bouton-secondaire">Enregistrer mes préférences</button>
    </form>
</section>
