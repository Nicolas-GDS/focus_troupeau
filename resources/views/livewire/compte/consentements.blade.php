<div class="space-y-6">
    @if (session('status')) <p class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</p> @endif
    <p class="text-sm">Vous choisissez qui peut utiliser les données de votre élevage. Une révocation prend effet immédiatement ; vos données ne sont jamais diffusées avec votre identité.</p>
    @forelse ($elevages as $e)
        @php($cs = $consentements[$e->id] ?? collect())
        @php($references = $cs->first(fn ($c) => $c->type === 'references' && ! $c->revoque_le))
        @php($veterinaires = $cs->filter(fn ($c) => $c->type === 'veterinaire' && ! $c->revoque_le))
        <section wire:key="el-{{ $e->id }}" class="carte" aria-labelledby="titre-{{ $e->id }}">
            <h2 id="titre-{{ $e->id }}" class="text-lg font-semibold">{{ $libelles[$e->id] }}</h2>
            <p class="text-sm"><a href="{{ route('export.donnees', $e->id) }}" class="lien">Télécharger les données de l'élevage (ZIP)</a> <span class="text-xs text-texte-doux">— fiches, bulletins et données, au titre de la portabilité (RGPD)</span></p>
            @error('elevage-'.$e->id) <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror

            <h3 class="mt-3 font-semibold">Références départementales</h3>
            <p class="text-sm text-texte-doux">Vos lots analysés entrent, sans identité, dans les médianes et quartiles qui servent à positionner les fourrages de tous les éleveurs (seulement si au moins 5 lots comparables).</p>
            @if ($references)
                <p class="mt-1 text-sm"><x-pastille etat="couvert" libelle="Accordé" /> depuis le {{ $references->accorde_le->format('d/m/Y') }}
                    <button type="button" wire:click="revoquer({{ $references->id }})" wire:confirm="Retirer vos lots des références ?" class="lien text-xs">révoquer</button></p>
            @else
                <p class="mt-1 text-sm"><x-pastille etat="information" libelle="Non accordé" /> <button type="button" wire:click="accorderReferences('{{ $e->id }}')" class="bouton-secondaire ml-2">J'accepte</button></p>
            @endif

            <h3 class="mt-4 font-semibold">Partage avec votre vétérinaire</h3>
            <p class="text-sm text-texte-doux">Le vétérinaire désigné lit votre dossier (fiches, résultats publiés) et peut recevoir une note de liaison du technicien.</p>
            <ul class="mt-1 space-y-1 text-sm">
                @forelse ($veterinaires as $v)
                    <li><x-pastille etat="couvert" libelle="Accordé" /> {{ $v->veterinaire?->nomComplet() }} depuis le {{ $v->accorde_le->format('d/m/Y') }}
                        <button type="button" wire:click="revoquer({{ $v->id }})" wire:confirm="Retirer l'accès de ce vétérinaire ?" class="lien text-xs">révoquer</button></li>
                @empty
                    <li class="text-texte-doux">Aucun vétérinaire désigné.</li>
                @endforelse
            </ul>
            <form wire:submit="accorderVeterinaire('{{ $e->id }}')" class="mt-2 flex flex-wrap items-end gap-2">
                <div><label for="veto-{{ $e->id }}" class="etiquette">Désigner un vétérinaire</label>
                    <select id="veto-{{ $e->id }}" wire:model="veterinaires.{{ $e->id }}" class="champ"><option value="">Choisir…</option>@foreach ($choixVeterinaires as $v) <option value="{{ $v->id }}">{{ $v->nomComplet() }}</option> @endforeach</select></div>
                <button type="submit" class="bouton-secondaire">Accorder l'accès</button>
            </form>

            @if ($cs->whereNotNull('revoque_le')->isNotEmpty())
                <details class="mt-3 text-xs text-texte-doux"><summary>Historique</summary>
                    <ul class="mt-1 space-y-1">@foreach ($cs->whereNotNull('revoque_le') as $c) <li>{{ $c->type === 'references' ? 'Références' : 'Vétérinaire '.$c->veterinaire?->nomComplet() }} : du {{ $c->accorde_le->format('d/m/Y') }} au {{ $c->revoque_le->format('d/m/Y') }}</li> @endforeach</ul></details>
            @endif
        </section>
    @empty
        <p class="carte text-texte-doux">Aucun élevage rattaché à votre compte.</p>
    @endforelse
</div>
