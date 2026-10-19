<div class="space-y-6">
    @if (session('status')) <p class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</p> @endif
    @foreach ($fiches as $id => $f)
        <section wire:key="gds-{{ $id }}" class="carte" aria-labelledby="titre-gds-{{ $id }}">
            <h2 id="titre-gds-{{ $id }}" class="text-lg font-semibold">GDS {{ $id }} — {{ $f['nom'] }}</h2>
            <p class="text-sm text-texte-doux">{{ $elevages[$id] ?? 0 }} élevages · {{ $conventions[$id] ?? 0 }} conventions actives · {{ $agents[$id] ?? 0 }} agents rattachés</p>
            @error('gds-'.$id) <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
            <form wire:submit="enregistrer({{ $id }})" class="mt-3 grid gap-2 sm:grid-cols-2">
                <div><label for="nom-{{ $id }}" class="etiquette">Nom</label><input id="nom-{{ $id }}" type="text" wire:model="fiches.{{ $id }}.nom" class="champ w-full">@error("fiches.{$id}.nom") <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div><label for="mail-{{ $id }}" class="etiquette">E-mail de contact</label><input id="mail-{{ $id }}" type="email" wire:model="fiches.{{ $id }}.contact_email" class="champ w-full">@error("fiches.{$id}.contact_email") <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div><label for="site-{{ $id }}" class="etiquette">Site web</label><input id="site-{{ $id }}" type="url" wire:model="fiches.{{ $id }}.site_web" class="champ w-full">@error("fiches.{$id}.site_web") <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                @if ($global)
                    <div><label for="coul-{{ $id }}" class="etiquette">Couleur locale (charte)</label><input id="coul-{{ $id }}" type="text" wire:model="fiches.{{ $id }}.couleur_locale" class="champ w-32"></div>
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="fiches.{{ $id }}.actif"> GDS actif</label>
                @endif
                <div class="sm:col-span-2"><button type="submit" class="bouton-primaire">Enregistrer</button></div>
            </form>
        </section>
    @endforeach

    @if ($global)
        <section class="carte" aria-labelledby="titre-nouveau-gds">
            <h2 id="titre-nouveau-gds" class="text-lg font-semibold">Nouveau GDS</h2>
            @error('nouveau') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
            <form wire:submit="creer" class="mt-3 grid gap-2 sm:grid-cols-3">
                <div><label for="ng-id" class="etiquette">Numéro (département)</label><input id="ng-id" type="text" inputmode="numeric" wire:model="nouveau.id" class="champ w-full">@error('nouveau.id') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div><label for="ng-code" class="etiquette">Code</label><input id="ng-code" type="text" wire:model="nouveau.code" class="champ w-full">@error('nouveau.code') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div><label for="ng-nom" class="etiquette">Nom</label><input id="ng-nom" type="text" wire:model="nouveau.nom" class="champ w-full">@error('nouveau.nom') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div><label for="ng-coul" class="etiquette">Couleur locale (#RRVVBB)</label><input id="ng-coul" type="text" wire:model="nouveau.couleur_locale" class="champ w-full">@error('nouveau.couleur_locale') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div><label for="ng-mail" class="etiquette">E-mail de contact</label><input id="ng-mail" type="email" wire:model="nouveau.contact_email" class="champ w-full"></div>
                <div class="flex items-end"><button type="submit" class="bouton-primaire">Créer le GDS</button></div>
            </form>
        </section>
    @endif
</div>
