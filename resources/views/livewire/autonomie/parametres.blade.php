@php
    use App\Services\Dossier\Affichage;
    $affiche = fn (float $v, string $unite) => $unite === 'fraction' ? Affichage::nombre(round(100 * $v, 2)).' %' : Affichage::nombre($v).' '.$unite;
@endphp
<div class="space-y-6">
    <p class="text-texte-doux">{{ $libelleElevage }}@if ($libelleElevage !== $elevage->code_pseudo) ({{ $elevage->code_pseudo }})@endif · <a href="{{ route('stocks', $elevage->id) }}" class="lien">stocks</a> · <a href="{{ route('autonomie', $elevage->id) }}" class="lien">bilan d'autonomie</a></p>
    @if (session('status')) <p class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</p> @endif
    <p class="text-sm">Les valeurs par défaut viennent du référentiel du GDS. Une valeur propre à l'élevage s'applique aux prochaines saisies ; la référence reste affichée à côté (D10).</p>

    @if ($voitEcarts)
        {{-- Écarts à la référence, pour le technicien (EF-M4-07) --}}
        <section class="carte" aria-labelledby="titre-ecarts">
            <h2 id="titre-ecarts" class="text-lg font-semibold">Écarts à la référence</h2>
            @if ($ecarts === [])
                <p class="mt-2 text-sm text-texte-doux">Aucune valeur modifiée pour cet élevage.</p>
            @else
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($ecarts as $e)
                        <li>{{ $e['libelle'] }} : <strong>{{ $affiche($e['valeur'], $e['unite']) }}</strong> au lieu de {{ $affiche($e['reference'], $e['unite']) }}
                            <span class="text-xs text-texte-doux">— {{ $e['maj_par'] }}, le {{ \Illuminate\Support\Carbon::parse($e['maj_le'])->format('d/m/Y') }}{{ $e['motif'] ? ' : '.$e['motif'] : '' }}</span></li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif

    @foreach ($groupes as $prefixe => $titre)
        @continue(! isset($lignes[$prefixe]))
        <section class="carte overflow-x-auto" aria-labelledby="titre-{{ Str::slug($titre) }}" tabindex="0">
            <h2 id="titre-{{ Str::slug($titre) }}" class="text-lg font-semibold">{{ $titre }}</h2>
            <ul class="mt-2 divide-y divide-filet text-sm">
                @foreach ($lignes[$prefixe] as $l)
                    @php($k = App\Livewire\Autonomie\Parametres::champ($l['cle']))
                    <li wire:key="param-{{ $k }}" class="py-2">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <span>{{ $l['libelle'] }}</span>
                            <span>
                                @if ($l['valeur'] !== null) <strong>{{ $affiche($l['valeur'], $l['unite']) }}</strong> <span class="text-xs">(valeur de l'élevage)</span> · @endif
                                <span class="{{ $l['valeur'] !== null ? 'text-texte-doux' : '' }}">référence {{ $affiche($l['reference'], $l['unite']) }}</span>
                            </span>
                        </div>
                        <p class="text-xs text-texte-doux">{{ $l['source'] }}{{ $l['statut'] ? ' · '.$l['statut'] : '' }}</p>
                        @if ($peutSaisir)
                            <form wire:submit="enregistrer('{{ $l['cle'] }}')" class="mt-1 flex flex-wrap items-end gap-2">
                                <div><label for="val-{{ $k }}" class="sr-only">Valeur de l'élevage</label>
                                    <input id="val-{{ $k }}" type="text" inputmode="decimal" wire:model="valeurs.{{ $k }}" class="champ w-28" placeholder="{{ $l['unite'] === 'fraction' ? 'en %' : $l['unite'] }}"></div>
                                <div><label for="motif-{{ $k }}" class="sr-only">Motif</label>
                                    <input id="motif-{{ $k }}" type="text" wire:model="motifs.{{ $k }}" class="champ w-56" placeholder="Motif (facultatif)"></div>
                                <button type="submit" class="bouton-secondaire">Enregistrer</button>
                                @if ($l['valeur'] !== null) <button type="button" wire:click="retablir('{{ $l['cle'] }}')" class="lien text-xs">rétablir la référence</button> @endif
                            </form>
                            @error('valeurs.'.$k) <p class="mt-1 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>
    @endforeach
</div>
