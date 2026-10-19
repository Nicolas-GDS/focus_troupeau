@php
    use App\Livewire\Dossier\Etats;
    use App\Services\Dossier\Affichage;
@endphp
{{-- Résultats d'une analyse d'eau, classés par la grille eau d'abreuvement (EF-M1-04), avec repère et source --}}
<div class="mt-2 overflow-x-auto" tabindex="0">
    <table class="min-w-full text-sm">
        <caption class="sr-only">Résultats de l'analyse d'eau, lecture, repère et source</caption>
        <thead class="text-left text-texte-doux">
            <tr><th scope="col" class="py-1 pr-4">Paramètre</th><th scope="col" class="py-1 pr-4">Valeur et lecture</th><th scope="col" class="py-1">Source</th></tr>
        </thead>
        <tbody class="divide-y divide-filet">
            @foreach ($lignes as $ligne)
                <tr class="align-top">
                    <th scope="row" class="py-1 pr-4 text-left font-medium">{{ $ligne['libelle'] }}</th>
                    <td class="py-1 pr-4">
                        <x-valeur-repere :valeur="$ligne['censure'].Affichage::nombre($ligne['valeur']).($ligne['unite'] === '-' ? '' : ' '.$ligne['unite'])"
                            :etat="Etats::classe($ligne['classe'], $ligne['gravite'])" :repere="$ligne['repere']" sans-repere="Repère non disponible (grille eau à compléter)" />
                    </td>
                    <td class="py-1">@include('livewire.dossier.saisie.partiels.source', ['source' => $ligne['statut'] === null ? null : ['source' => $ligne['source'], 'statut' => $ligne['statut'], 'a_valider' => $ligne['a_valider']]])</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
