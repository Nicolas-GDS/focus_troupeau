@php
    use App\Livewire\Dossier\Etats;
    use App\Services\Dossier\Affichage;
@endphp
{{-- Indicateurs du bilan sanitaire positionnés sur les seuils S1/S2 (EF-M1-07), avec la campagne précédente --}}
<div class="mt-2 overflow-x-auto">
    <table class="min-w-full text-sm">
        <caption class="sr-only">Indicateurs du bilan sanitaire : valeur, situation, seuils et source</caption>
        <thead class="text-left text-texte-doux"><tr><th scope="col" class="py-1 pr-4">Indicateur</th><th scope="col" class="py-1 pr-4">Valeur et situation</th><th scope="col" class="py-1">Campagne précédente</th></tr></thead>
        <tbody class="divide-y divide-filet">
            @foreach ($lignes as $l)
                @php($decimales = $l['mode'] === 'taux' ? 1 : 0)
                <tr class="align-top">
                    <th scope="row" class="py-1 pr-4 text-left font-medium">{{ $l['libelle'] }}</th>
                    <td class="py-1 pr-4">
                        @if ($l['valeur'] === null)
                            <span class="text-texte-doux">non renseigné</span>
                        @else
                            <x-valeur-repere :valeur="Affichage::nombre($l['valeur'], $decimales).' '.$l['unite']" :etat="Etats::bilan($l['classe'])" :repere="$l['seuils']" :source="$l['source']" />
                        @endif
                    </td>
                    <td class="py-1">{{ $l['precedente'] === null ? '—' : Affichage::nombre($l['precedente'], $decimales).' '.$l['unite'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
