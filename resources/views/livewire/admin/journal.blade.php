<div class="space-y-4">
    <section class="carte" aria-label="Filtres">
        <div class="flex flex-wrap gap-2">
            <div><label for="j-action" class="etiquette">Action</label><select id="j-action" wire:model.live="action" class="champ"><option value="">Toutes</option>@foreach ($actions as $a) <option value="{{ $a }}">{{ str_replace('_', ' ', $a) }}</option> @endforeach</select></div>
            <div><label for="j-compte" class="etiquette">Compte (e-mail)</label><input id="j-compte" type="search" wire:model.live.debounce.300ms="compte" class="champ"></div>
            <div><label for="j-elevage" class="etiquette">Élevage (code)</label><input id="j-elevage" type="search" wire:model.live.debounce.300ms="elevage" class="champ"></div>
            <div><label for="j-du" class="etiquette">Du</label><input id="j-du" type="date" wire:model.live="du" class="champ"></div>
            <div><label for="j-au" class="etiquette">Au</label><input id="j-au" type="date" wire:model.live="au" class="champ"></div>
        </div>
    </section>
    <section class="carte overflow-x-auto" aria-labelledby="titre-journal">
        <h2 id="titre-journal" class="text-lg font-semibold">Journal d'audit</h2>
        <table class="mt-3 w-full text-sm">
            <thead><tr class="text-left text-texte-doux"><th scope="col" class="py-1 pr-3">Date</th><th scope="col" class="pr-3">Action</th><th scope="col" class="pr-3">Compte</th><th scope="col" class="pr-3">Élevage</th><th scope="col" class="pr-3">Objet</th><th scope="col">Détail</th></tr></thead>
            <tbody>
            @forelse ($entrees as $e)
                <tr wire:key="j-{{ $e->id }}" class="border-t border-filet align-top">
                    <td class="whitespace-nowrap py-1 pr-3">{{ \Illuminate\Support\Carbon::parse($e->horodatage)->timezone(config('app.timezone'))->format('d/m/Y H:i:s') }}</td>
                    <td class="pr-3">{{ str_replace('_', ' ', $e->action) }}</td>
                    <td class="pr-3">{{ $e->email ?? 'système' }}</td>
                    <td class="pr-3">{{ $e->code_pseudo ?? '—' }}</td>
                    <td class="pr-3 text-xs">{{ $e->objet }}</td>
                    <td class="max-w-xs break-words text-xs text-texte-doux">{{ $e->detail }}@if ($e->ip) <span class="block">IP {{ $e->ip }}</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="6" class="py-2 text-texte-doux">Aucune entrée.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="mt-2">{{ $entrees->links() }}</div>
    </section>
</div>
