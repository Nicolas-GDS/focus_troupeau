@php
    use App\Livewire\Dossier\Etats;
    use App\Services\Dossier\Affichage;
    $e = $item['etiquette'];
    $statut = Etats::statut($e->statut);
@endphp
{{-- Étiquette (EF-M1-06) : composition par kg et apport journalier par élément (dose × teneur). Le libellé saisi est affiché tel quel (EF-GEN-04). --}}
<div class="flex flex-wrap items-center justify-between gap-2">
    <p class="font-medium">{{ $natures[$e->nature] ?? $e->nature }} — « {{ $e->libelle_eleveur }} »</p>
    <x-pastille :etat="$statut['etat']" :libelle="$statut['libelle']" />
</div>
<p class="mt-1 text-sm text-texte-doux">
    Dose : {{ $e->dose_g_par_animal_jour === null ? 'non déclarée' : Affichage::nombre((float) $e->dose_g_par_animal_jour).' g par animal et par jour' }}
    @if ($e->mode_distribution) · {{ $e->mode_distribution }} @endif
    @if ($e->date_limite) · date limite {{ $e->date_limite->format('d/m/Y') }} @endif
</p>
@if ($item['lecture']['perimee'])
    <p class="mt-1"><x-pastille etat="alerte" :libelle="'Date limite dépassée '.$datePeremption" /></p>
@endif
@if ($item['lecture']['composition'] !== [])
    <div class="mt-2 overflow-x-auto" tabindex="0">
        <table class="min-w-full text-sm">
            <caption class="sr-only">Composition par kg de produit et apport journalier</caption>
            <thead class="text-left text-texte-doux"><tr><th scope="col" class="py-1 pr-4">Élément</th><th scope="col" class="py-1 pr-4">Teneur</th><th scope="col" class="py-1">Apport par animal et par jour</th></tr></thead>
            <tbody class="divide-y divide-filet">
                @foreach ($item['lecture']['composition'] as $c)
                    <tr>
                        <th scope="row" class="py-1 pr-4 text-left font-medium">{{ $c['libelle'] }}</th>
                        <td class="py-1 pr-4">{{ Affichage::nombre($c['teneur']) }} {{ $c['unite'] }}</td>
                        <td class="py-1">{{ $c['apport'] === null ? '—' : Affichage::nombre(round($c['apport']['valeur'], 3)).' '.$c['apport']['unite'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
