@php
    use App\Livewire\Dossier\Etats;
    use App\Services\Dossier\Affichage;
@endphp
{{-- Mesures terrain par stade et profils sanguins (EF-M1-05) : valeur, lecture, repère du stade et source --}}
@foreach ($mesures as $groupe)
    <div class="mt-3 rounded-md border border-filet p-3">
        <h4 class="font-medium">Mesures terrain — {{ $groupe['libelle'] }} · {{ $especes[$groupe['espece']] ?? 'espèce non précisée' }}</h4>
        <ul class="mt-2 space-y-3 text-sm">
            @foreach ($groupe['mesures'] as $m)
                <li>
                    <span class="font-medium">{{ $m['libelle'] }}</span>
                    <x-valeur-repere :valeur="'médiane '.Affichage::nombre($m['mediane'], $m['decimales']).($m['unite'] === '-' ? '' : ' '.$m['unite'])"
                        :etat="Etats::mesure($m['lecture']?->value)" :repere="$m['repere']" :source="$m['source']" />
                    <span class="block text-xs text-texte-doux">Valeurs : {{ collect($m['mesures'])->map(fn ($x) => ($x->identifiant_animal ? $x->identifiant_animal.' : ' : '').Affichage::nombre((float) $x->valeur, $m['decimales']))->implode(' · ') }}</span>
                    @if ($agent && collect($m['mesures'])->contains(fn ($x) => $x->statut !== 'publiee')) <span class="block text-xs text-etat-vigilance">non publié</span> @endif
                </li>
            @endforeach
        </ul>
        @foreach ($groupe['lectures'] as $lecture)
            <p class="mt-2 text-sm"><span class="font-medium">Lecture :</span> {{ $lecture['libelle'] }} @include('livewire.dossier.saisie.partiels.source', ['source' => $lecture['source']])</p>
        @endforeach
    </div>
@endforeach
@if ($profils !== [])
    <h4 class="mt-4 font-medium">Prises de sang</h4>
    <div class="mt-2 overflow-x-auto" tabindex="0">
        <table class="min-w-full text-sm">
            <caption class="sr-only">Profils sanguins : valeur, lecture, repère et source</caption>
            <thead class="text-left text-texte-doux"><tr><th scope="col" class="py-1 pr-4">Élément</th><th scope="col" class="py-1">Résultat, lecture et référence</th></tr></thead>
            <tbody class="divide-y divide-filet">
                @foreach ($profils as $l)
                    @php($p = $l['profil'])
                    <tr class="align-top">
                        <th scope="row" class="py-1 pr-4 text-left font-medium">{{ ucfirst($p->parametre) }}
                            <span class="block text-xs font-normal text-texte-doux">{{ $p->matrice }}@if ($p->nb_animaux) · {{ $p->nb_animaux }} animaux @endif</span>
                            @if ($agent && $p->statut !== 'publiee') <span class="block text-xs font-normal text-etat-vigilance">non publié</span> @endif
                        </th>
                        <td class="py-1">
                            <x-valeur-repere :valeur="Affichage::nombre($p->valeur_moyenne === null ? null : (float) $p->valeur_moyenne).' '.$p->unite" :etat="Etats::profil($l['lecture'])" :repere="$l['repere']" :source="$l['source']" />
                            @if ($l['unite_differente']) <span class="block text-xs text-etat-vigilance">unité différente de celle du repère : pas de lecture</span> @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
