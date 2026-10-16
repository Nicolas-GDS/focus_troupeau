@php
    use App\Livewire\Interpretation\InterpretationBulletin;
@endphp
<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'dossier.fourrages'])

    @forelse ($campagnes as $campagne => $lots)
        <section class="carte mb-6" aria-labelledby="campagne-{{ $loop->index }}" wire:key="campagne-{{ $campagne }}">
            <h2 id="campagne-{{ $loop->index }}" class="text-lg font-semibold">Campagne {{ $campagne }}</h2>
            <ul class="mt-3 divide-y divide-filet">
                @foreach ($lots as $lot)
                    @php($p = $lot['prelevement'])
                    @php($i = $lot['interpretation'])
                    <li class="flex flex-wrap items-start justify-between gap-3 py-3" wire:key="lot-{{ $lot['analyse']->id }}">
                        <div>
                            <a href="{{ route('dossier.fourrage', [$elevage->id, $p->id]) }}" class="lien font-medium">{{ $p->typeFourrage?->libelle ?? $p->type_fourrage_code }}@if ($p->nom_lot) « {{ $p->nom_lot }} »@endif</a>
                            <p class="text-sm text-texte-doux">Prélevé le {{ $p->date_prelevement->format('d/m/Y') }} · échantillon {{ $lot['analyse']->num_echantillon_labo }}</p>
                            @if ($i?->utilisation_conseillee) <p class="text-sm">Utilisation conseillée : {{ $i->utilisation_conseillee }}</p> @endif
                            @if ($lot['positions'] !== [])
                                <p class="mt-1 flex flex-wrap gap-3 text-sm">
                                    <span class="text-texte-doux">Position dans la campagne :</span>
                                    @foreach ($lot['positions'] as $code => $position)
                                        <span>{{ $code === 'UFL_2018' ? 'UFL' : $code }} <x-position :position="$position" /></span>
                                    @endforeach
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            @if ($i)
                                @php($n = InterpretationBulletin::niveau($i->niveau_global))
                                <x-pastille :etat="$n['etat']" :libelle="$n['libelle']" />
                                @if ($agent && $i->statut !== 'publie') @php($s = InterpretationBulletin::statut($i->statut)) <x-pastille :etat="$s['etat']" :libelle="$s['libelle'].' — non publié'" /> @endif
                            @else
                                <x-pastille etat="information" libelle="Non interprété" />
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <p class="carte text-texte-doux">Aucun fourrage analysé à afficher.</p>
    @endforelse

    <x-limites :limites="$limites" />
</div>
