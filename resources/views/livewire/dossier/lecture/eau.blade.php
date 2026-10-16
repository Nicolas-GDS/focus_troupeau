<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'dossier.eau'])

    @forelse ($analyses as $item)
        @php($a = $item['analyse'])
        <section class="carte mb-6" aria-labelledby="eau-{{ $a->id }}" wire:key="eau-{{ $a->id }}">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="eau-{{ $a->id }}" class="text-lg font-semibold">{{ $a->point_prelevement }} · {{ $a->date_prelevement->format('d/m/Y') }}</h2>
                @if ($agent && $a->statut !== 'publie') @php($s = \App\Livewire\Dossier\Etats::statut($a->statut)) <x-pastille :etat="$s['etat']" :libelle="$s['libelle'].' — non publié'" /> @endif
            </div>
            @include('livewire.dossier.lecture.partiels.eau', ['lignes' => $item['lignes']])
        </section>
    @empty
        <p class="carte text-texte-doux">Aucune analyse d'eau à afficher.</p>
    @endforelse

    <x-limites :limites="$limites" />
</div>
