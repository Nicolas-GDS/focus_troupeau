<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'dossier.sanitaire'])

    <section class="carte mb-6" aria-labelledby="titre-bilan">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <h2 id="titre-bilan" class="text-lg font-semibold">Bilan sanitaire @if ($bilan['campagne']) — campagne {{ $bilan['campagne'] }} @endif</h2>
            @if (count($bilan['campagnes']) > 1)
                <div>
                    <label for="campagne" class="etiquette">Campagne</label>
                    <select id="campagne" wire:model.live="campagne" class="champ">
                        @foreach ($bilan['campagnes'] as $c) <option value="{{ $c }}" @selected($c === $bilan['campagne'])>{{ $c }}</option> @endforeach
                    </select>
                </div>
            @endif
        </div>
        @if ($bilan['lignes'] === [])
            <p class="mt-2 text-sm text-texte-doux">Aucun indicateur à afficher.</p>
        @else
            @include('livewire.dossier.lecture.partiels.bilan', ['lignes' => $bilan['lignes']])
        @endif
    </section>

    <section class="carte" aria-labelledby="titre-evenements">
        <h2 id="titre-evenements" class="text-lg font-semibold">Événements sanitaires déclarés</h2>
        @if ($bilan['evenements']->isEmpty())
            <p class="mt-2 text-sm text-texte-doux">Aucun événement déclaré.</p>
        @else
            <ul class="mt-2 divide-y divide-filet text-sm">
                @foreach ($bilan['evenements'] as $e)
                    <li class="py-2" wire:key="evenement-{{ $e->id }}">
                        <span class="font-medium">{{ ucfirst(str_replace('_', ' ', $e->type_evenement)) }}</span> · {{ $e->date_evenement->format('d/m/Y') }}
                        · {{ $especes[$e->espece_code] ?? $e->espece_code }}@if ($e->nb_animaux) · {{ $e->nb_animaux }} animal(aux) @endif
                        @if ($e->description) <span class="block text-texte-doux">{{ $e->description }}</span> @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    <x-limites :limites="$limites" />
</div>
