@php
    use App\Livewire\Dossier\Etats;
    use App\Services\Dossier\Affichage;
    $parJour = fn (string $unite) => trim(str_replace(['/kg MS', 'kg MS'], '', $unite));
@endphp
{{-- Apports, couverture et contrôles d'une ration (RC-9, EF-M5-03) : classe par pictogramme, libellé et valeur ; repère et source --}}
<div class="mt-3">
    <p class="text-sm font-medium">Apports et couverture · {{ Affichage::nombre($analyse['msi'], 2) }} kg MS ingérés/animal/j</p>
    @if ($analyse['inconnus'] !== [])
        <p class="mt-1 text-sm text-etat-vigilance">Apports inconnus pour : {{ implode(', ', $analyse['inconnus']) }}. La couverture n'est pas calculée : analyser ces fourrages (ou relier le lot de stock à son analyse).</p>
    @endif
    @if ($analyse['sans_besoin'])
        <p class="mt-1 text-sm text-texte-doux">Besoin non renseigné pour cette catégorie et ce stade dans les repères chargés.</p>
    @endif
    @if ($analyse['manquants'] !== [])
        <p class="mt-1 text-sm text-texte-doux">Production laitière à saisir pour calculer : {{ implode(', ', $analyse['manquants']) }}.</p>
    @endif
    @if ($analyse['elements'] !== [])
        <div class="mt-2 overflow-x-auto" tabindex="0">
            <table class="min-w-full text-sm">
                <caption class="sr-only">Apports de la ration comparés aux besoins : valeur, couverture, besoin résiduel, repère et source</caption>
                <thead class="text-left text-texte-doux"><tr><th scope="col" class="py-1 pr-3">Élément</th><th scope="col" class="py-1 pr-3">Apport</th><th scope="col" class="py-1 pr-3">Besoin</th><th scope="col" class="py-1">Couverture</th></tr></thead>
                <tbody class="divide-y divide-filet">
                    @foreach ($analyse['elements'] as $e)
                        @php($r = $e['resultat'])
                        @php($b = $e['besoin'])
                        @php($etat = Etats::couverture($r->classe, $r->gravite))
                        <tr class="align-top">
                            <th scope="row" class="py-1 pr-3 text-left font-medium">{{ $e['libelle'] }}</th>
                            <td class="py-1 pr-3 whitespace-nowrap">
                                @if ($b->mode === 'par_jour') {{ Affichage::nombre($r->apport, 2) }} {{ $parJour($e['unite']) }}/j
                                @else {{ Affichage::nombre($r->concentration, 2) }} {{ $e['unite'] }} @endif
                            </td>
                            <td class="py-1 pr-3">
                                @if ($b->mode === 'par_jour') {{ Affichage::nombre($b->valeur, 2) }} {{ $parJour($e['unite']) }}/j
                                @else {{ Affichage::bornes($b->min, $b->max, $e['unite']) }}@if ($b->maximum !== null) <span class="block text-xs">maximum {{ Affichage::nombre($b->maximum) }} {{ $e['unite'] }}</span>@endif @endif
                                @include('livewire.dossier.saisie.partiels.source', ['source' => $e['source']])
                            </td>
                            <td class="py-1">
                                <x-pastille :etat="$etat['etat']" :libelle="$etat['libelle'].($r->ratio !== null ? ' — '.Affichage::nombre(100 * $r->ratio, 0).' %' : '')" />
                                @if ($r->residuel) <span class="block text-xs text-texte-doux">manque {{ Affichage::nombre($r->residuel, 2) }} {{ $parJour($e['unite']) }}/animal/j</span> @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
    @if ($analyse['controles'] !== [])
        <ul class="mt-2 space-y-1 text-sm">
            @foreach ($analyse['controles'] as $c)
                @php($etat = Etats::controle($c->gravite))
                <li class="flex flex-wrap items-center gap-2"><span class="font-medium">{{ $c->libelle }} : {{ Affichage::nombre($c->valeur, $c->code === 'BACA' || $c->code === 'PDI_UFL' ? 0 : 2) }}</span>
                    <x-pastille :etat="$etat['etat']" :libelle="$etat['libelle']" /> <span class="text-texte-doux">{{ $c->constat }}</span></li>
            @endforeach
        </ul>
    @endif
    <p class="mt-2 text-xs text-texte-doux">Repères chargés à valider par le vétérinaire conseil ; ce calcul ne remplace pas un calcul de ration complet.</p>
</div>
