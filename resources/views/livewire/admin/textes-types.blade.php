<div class="space-y-6">
    @include('livewire.admin.partiels.entete-referentiels', ['table' => 'textes'])

    @if ($code)
        <section class="carte" aria-labelledby="titre-texte">
            <h2 id="titre-texte" class="text-lg font-semibold">Modifier {{ $code }}</h2>
            <p class="text-sm text-texte-doux">Conserver les variables entre accolades (par exemple {elevage}) : elles sont remplacées à l'envoi.</p>
            <form wire:submit="enregistrer" class="mt-3 space-y-2">
                <div><label for="t-texte" class="etiquette">Texte</label><textarea id="t-texte" wire:model="texte" rows="4" class="champ w-full"></textarea>@error('texte') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div><label for="t-decision" class="etiquette">Décision du comité technique</label><input id="t-decision" type="text" wire:model="decision" class="champ w-full">@error('decision') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div class="flex gap-2"><button type="submit" class="bouton-primaire">Enregistrer une nouvelle version</button><button type="button" wire:click="$set('code', null)" class="bouton-secondaire">Annuler</button></div>
            </form>
            @if ($historique->count() > 1)
                <h3 class="mt-4 font-semibold">Versions précédentes</h3>
                <ul class="mt-1 space-y-1 text-xs text-texte-doux">@foreach ($historique->skip(1) as $h) <li>v{{ $h->version_creation }} → v{{ $h->version_fin }} : {{ $h->texte }}</li> @endforeach</ul>
            @endif
        </section>
    @endif

    <section class="carte overflow-x-auto" aria-labelledby="titre-textes" tabindex="0">
        <h2 id="titre-textes" class="text-lg font-semibold">Textes types</h2>
        <div class="mt-2"><label for="t-filtre" class="etiquette">Filtrer (code ou texte)</label><input id="t-filtre" type="search" wire:model.live.debounce.300ms="filtre" class="champ"></div>
        <table class="mt-3 w-full text-sm">
            <thead><tr class="text-left text-texte-doux"><th scope="col" class="py-1 pr-3">Code</th><th scope="col" class="pr-3">Texte</th><th scope="col"></th></tr></thead>
            <tbody>
            @foreach ($textes as $t)
                <tr wire:key="t-{{ $t->id }}" class="border-t border-filet align-top">
                    <td class="py-1 pr-3 text-xs">{{ $t->code }}<span class="block text-texte-doux">v{{ $t->version_creation }}{{ $t->source ? ' · '.$t->source : '' }}</span></td>
                    <td class="pr-3">{{ $t->texte }}</td>
                    <td>@if ($peutEcrire) <button type="button" wire:click="choisir('{{ $t->code }}')" class="lien text-xs">modifier</button> @endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>
