@php
    use App\Livewire\Dossier\Visites;
    use App\Livewire\Interpretation\InterpretationBulletin;
@endphp
<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'dossier.historique'])

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
                    @if ($v['actions']->isNotEmpty())
                        <p class="mt-2 text-sm font-medium">Décisions</p>
                        <ul class="mt-1 space-y-1 text-sm">
                            @foreach ($v['actions'] as $a)
                                <li>{{ $a->titre }} <x-pastille :etat="$a->statut === 'faite' ? 'couvert' : 'information'" :libelle="$a->statut === 'faite' ? 'Faite' : ($a->statut === 'abandonnee' ? 'Abandonnée' : 'À faire')" /></li>
                            @endforeach
                        </ul>
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
        </section>
    @empty
        <p class="carte text-texte-doux">Aucun historique à afficher.</p>
    @endforelse

    <p class="text-xs text-texte-doux">Les courbes pluriannuelles par paramètre (MAT et UFL des foins, cuivre et zinc, alertes) seront affichées au lot L4.</p>
    <x-limites :limites="$limites" />
</div>
