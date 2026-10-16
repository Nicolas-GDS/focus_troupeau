@php
    use App\Livewire\Interpretation\InterpretationBulletin;
    use App\Services\Dossier\Affichage;
@endphp
<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'dossier.matrice'])

    @if ($matrice['campagnes'] === [])
        <p class="carte text-texte-doux">Aucun fourrage analysé à afficher.</p>
    @else
        <section class="carte" aria-labelledby="titre-matrice">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h2 id="titre-matrice" class="text-lg font-semibold">Campagne {{ $matrice['campagne'] }}</h2>
                @if (count($matrice['campagnes']) > 1)
                    <div>
                        <label for="campagne" class="etiquette">Campagne</label>
                        <select id="campagne" wire:model.live="campagne" class="champ">
                            @foreach ($matrice['campagnes'] as $c) <option value="{{ $c }}" @selected($c === $matrice['campagne'])>{{ $c }}</option> @endforeach
                        </select>
                    </div>
                @endif
            </div>
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <caption class="sr-only">Matrice de vigilance minérale : lots en lignes, éléments en colonnes ; valeur, classe et repère</caption>
                    <thead class="text-left text-texte-doux">
                        <tr>
                            <th scope="col" class="py-1 pr-3">Lot</th>
                            @foreach ($elements as $code) <th scope="col" class="py-1 pr-3"><abbr title="{{ $libelles[$code] ?? $code }}">{{ $code }}</abbr></th> @endforeach
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-filet">
                        @foreach ($matrice['lots'] as $lot)
                            <tr class="align-top" wire:key="matrice-{{ $lot['analyse']->id }}">
                                <th scope="row" class="py-2 pr-3 text-left font-medium">
                                    <a href="{{ route('dossier.fourrage', [$elevage->id, $lot['prelevement']->id]) }}" class="lien">{{ $lot['prelevement']->nom_lot ?: ($lot['prelevement']->typeFourrage?->libelle ?? $lot['analyse']->num_echantillon_labo) }}</a>
                                </th>
                                @foreach ($elements as $code)
                                    @php($v = $lot['valeurs'][$code] ?? null)
                                    <td class="py-2 pr-3">
                                        @if ($v && $v['valeur'] !== null)
                                            <x-valeur-repere :valeur="$v['censure'].Affichage::nombre($v['valeur'], $v['decimales'])" :etat="InterpretationBulletin::classe($v['classe'], $v['gravite'])"
                                                :repere="$v['repere']" :source="Affichage::sourceSeuil($v['source'], $v['statut'], $v['a_valider'])" sans-repere="Sans repère" />
                                        @else
                                            <span class="text-texte-doux">nd</span>
                                        @endif
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-2 text-xs text-texte-doux">Valeurs par kg de matière sèche (g pour les minéraux majeurs, mg pour les oligo-éléments, mEq pour le BACA) ; nd : non dosé. La variante « ration », qui agrège fourrages, concentrés et minéral distribué par lot d'animaux, sera disponible au lot L5.</p>
        </section>
    @endif

    <x-limites :limites="$limites" />
</div>
