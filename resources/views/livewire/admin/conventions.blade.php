@php($statuts = ['active' => 'Active', 'echue' => 'Échue', 'resiliee' => 'Résiliée'])
<div class="space-y-6">
    @if (session('status')) <p class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</p> @endif
    <div class="grid gap-6 lg:grid-cols-3">
        <section class="carte overflow-x-auto lg:col-span-2" aria-labelledby="titre-conventions">
            <h2 id="titre-conventions" class="text-lg font-semibold">Conventions</h2>
            <div class="mt-2 flex flex-wrap gap-2">
                <div><label for="f-statut" class="etiquette">Statut</label><select id="f-statut" wire:model.live="statut" class="champ"><option value="">Tous</option>@foreach ($statuts as $c => $l) <option value="{{ $c }}">{{ $l }}</option> @endforeach</select></div>
                <div><label for="f-rech" class="etiquette">Élevage</label><input id="f-rech" type="search" wire:model.live.debounce.300ms="recherche" class="champ" placeholder="code ou raison sociale"></div>
            </div>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-texte-doux"><th scope="col" class="py-1 pr-3">Élevage</th><th scope="col" class="pr-3">Période</th><th scope="col" class="pr-3">Statut</th><th scope="col"></th></tr></thead>
                <tbody>
                @forelse ($conventions as $c)
                    @php($proche = $c->statut === 'active' && $c->date_fin->lte(today()->addDays($rappel)))
                    @php($el = $elevages[$c->elevage_id] ?? null)
                    <tr wire:key="conv-{{ $c->id }}" class="border-t border-filet">
                        <td class="py-1 pr-3">{{ $el?->raison_sociale ?? $el?->code_pseudo }}<span class="block text-xs text-texte-doux">{{ $el?->code_pseudo }}{{ $c->reference_externe ? ' · '.$c->reference_externe : '' }}</span></td>
                        <td class="pr-3">{{ $c->date_debut->format('d/m/Y') }} → {{ $c->date_fin->format('d/m/Y') }}@if ($proche) <x-pastille etat="vigilance" libelle="Échéance proche" /> @endif</td>
                        <td class="pr-3">{{ $statuts[$c->statut] ?? $c->statut }}</td>
                        <td><button type="button" wire:click="choisir({{ $c->id }})" class="lien text-xs">gérer</button></td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="py-2 text-texte-doux">Aucune convention.</td></tr>
                @endforelse
                </tbody>
            </table>
            <div class="mt-2">{{ $conventions->links() }}</div>
        </section>

        @if ($selection)
            <section class="carte" aria-labelledby="titre-convention">
                <h2 id="titre-convention" class="text-lg font-semibold">Convention n° {{ $selection->id }}</h2>
                <p class="text-sm">{{ $statuts[$selection->statut] ?? $selection->statut }} · du {{ $selection->date_debut->format('d/m/Y') }} au {{ $selection->date_fin->format('d/m/Y') }}</p>
                @if ($selection->commentaire) <p class="mt-1 whitespace-pre-line text-xs text-texte-doux">{{ $selection->commentaire }}</p> @endif
                @error('convention') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
                @if ($selection->statut !== 'resiliee')
                    <form wire:submit="prolonger" class="mt-3 flex flex-wrap items-end gap-2">
                        <div><label for="fin" class="etiquette">Prolonger jusqu'au</label><input id="fin" type="date" wire:model="nouvelleFin" class="champ"></div>
                        <button type="submit" class="bouton-secondaire">Prolonger</button>
                    </form>
                    @error('nouvelleFin') <p class="text-xs text-etat-critique">{{ $message }}</p> @enderror
                    @if ($peutResilier)
                        <form wire:submit="resilier" class="mt-3 flex flex-wrap items-end gap-2">
                            <div class="w-full"><label for="motif" class="etiquette">Motif de résiliation</label><input id="motif" type="text" wire:model="motif" class="champ w-full"></div>
                            <button type="submit" wire:confirm="Résilier cette convention ? L'accès de l'éleveur passe en lecture seule." class="bouton-secondaire">Résilier</button>
                        </form>
                    @endif
                @endif
            </section>
        @endif
    </div>
</div>
