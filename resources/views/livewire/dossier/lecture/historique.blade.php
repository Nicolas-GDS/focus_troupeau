@php
    use App\Livewire\Dossier\Visites;
    use App\Livewire\Interpretation\InterpretationBulletin;
    $statuts = ['a_faire' => 'à faire', 'faite' => 'faite', 'abandonnee' => 'abandonnée'];
@endphp
<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'dossier.historique'])

    {{-- Évolution pluriannuelle (RC-4, EF-M1-08) --}}
    <section class="carte mb-6" aria-labelledby="titre-evolution">
        <h2 id="titre-evolution" class="text-lg font-semibold">Ce qui a changé d'une campagne à l'autre</h2>
        <p class="mt-1 text-sm text-texte-doux">Valeur de chaque campagne (médiane si plusieurs lots du même type) et écart avec la campagne précédente. Un écart n'est ni bon ni mauvais en soi : il se lit avec votre technicien.</p>
        @forelse ($evolution as $section)
            <h3 class="mt-4 font-medium">{{ $section['type'] }}</h3>
            @foreach ($section['courbes'] as $c)
                <div wire:key="courbe-{{ $loop->parent->index }}-{{ $c['code'] }}">
                    <x-historique :parametre="$c['libelle']" :unite="$c['unite']" :decimales="$c['decimales']" :points="$c['points']" :variation="$c['variation']" />
                </div>
            @endforeach
        @empty
            <p class="mt-2 text-sm text-texte-doux">Aucun lot analysé à comparer.</p>
        @endforelse
    </section>

    {{-- Frise par campagne : visites, lots, décisions --}}
    @forelse ($campagnes as $campagne => $contenu)
        <section class="carte mb-6" aria-labelledby="hist-{{ $loop->index }}" wire:key="hist-{{ $campagne }}">
            <h2 id="hist-{{ $loop->index }}" class="text-lg font-semibold">Campagne {{ $campagne }}</h2>

            @foreach ($contenu['visites'] as $v)
                <div class="mt-3 rounded-md border border-filet p-3" wire:key="hist-visite-{{ $v['visite']->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <a href="{{ route('dossier.visite', [$elevage->id, $v['visite']->id]) }}" class="lien font-medium">Visite du {{ $v['visite']->date_visite->format('d/m/Y') }} — {{ Visites::motifs()[$v['visite']->motif] ?? $v['visite']->motif }}</a>
                        @if ($v['interpretation']) @php($n = InterpretationBulletin::niveau($v['interpretation']->niveau_global)) <x-pastille :etat="$n['etat']" :libelle="$n['libelle']" /> @endif
                    </div>
                    @if ($v['messages']->isNotEmpty())
                        <ol class="mt-2 space-y-1 text-sm">
                            @foreach ($v['messages']->sortByDesc('gravite') as $m) <x-message-hierarchise :message="$m" /> @endforeach
                        </ol>
                    @endif
                </div>
            @endforeach

            @if ($contenu['lots'] !== [])
                <p class="mt-3 text-sm font-medium">Fourrages analysés</p>
                <ul class="mt-1 space-y-1 text-sm">
                    @foreach ($contenu['lots'] as $lot)
                        <li>
                            <a href="{{ route('dossier.fourrage', [$elevage->id, $lot['prelevement']->id]) }}" class="lien">{{ $lot['prelevement']->typeFourrage?->libelle ?? $lot['prelevement']->type_fourrage_code }}@if ($lot['prelevement']->nom_lot) « {{ $lot['prelevement']->nom_lot }} »@endif</a>
                            ({{ $lot['prelevement']->date_prelevement->format('d/m/Y') }})
                            @if ($lot['interpretation']) @php($n = InterpretationBulletin::niveau($lot['interpretation']->niveau_global)) <x-pastille :etat="$n['etat']" :libelle="$n['libelle']" /> @endif
                        </li>
                    @endforeach
                </ul>
            @endif

            @if (! empty($decisions[$campagne]))
                <p class="mt-3 text-sm font-medium">Décisions</p>
                <ol class="mt-1 space-y-1 border-l-2 border-filet pl-3 text-sm">
                    @foreach ($decisions[$campagne] as $d)
                        <li>
                            <span class="text-texte-doux">{{ $d['date']->format('d/m/Y') }} · {{ $d['nature'] === 'action' ? 'Action du plan' : 'Recommandation' }}</span>
                            <span class="block">{{ $d['texte'] }}@if ($d['statut']) <span class="text-texte-doux">({{ $statuts[$d['statut']] ?? $d['statut'] }})</span>@endif</span>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @empty
        <p class="carte text-texte-doux">Aucun historique à afficher.</p>
    @endforelse

    <x-limites :limites="$limites" />
</div>
