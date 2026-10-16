<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'dossier.animaux'])

    @forelse ($dates as $jour)
        <section class="carte mb-6" aria-labelledby="animaux-{{ $loop->index }}" wire:key="animaux-{{ $jour['date'] }}">
            <h2 id="animaux-{{ $loop->index }}" class="text-lg font-semibold">{{ \Illuminate\Support\Carbon::parse($jour['date'])->format('d/m/Y') }}</h2>
            @include('livewire.dossier.lecture.partiels.animaux', ['mesures' => $jour['mesures'], 'profils' => $jour['profils']])
        </section>
    @empty
        <p class="carte text-texte-doux">Aucune mesure ni prise de sang à afficher.</p>
    @endforelse

    <x-limites :limites="$limites" />
</div>
