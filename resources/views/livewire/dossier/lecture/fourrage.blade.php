@php
    use App\Livewire\Interpretation\InterpretationBulletin;
    use App\Services\Dossier\Affichage;
@endphp
<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'dossier.fourrages'])

    <p class="mb-4 text-sm"><a href="{{ route('dossier.fourrages', $elevage->id) }}" class="lien">Tous les fourrages</a></p>

    <section class="carte mb-6" aria-labelledby="titre-lot">
        <h2 id="titre-lot" class="text-lg font-semibold">Le lot</h2>
        <p class="mt-1 text-sm">Campagne {{ $prelevement->campagne_code ?? 'non déterminée' }} · prélevé le {{ $prelevement->date_prelevement->format('d/m/Y') }}
            @if ($prelevement->date_recolte) · récolté le {{ $prelevement->date_recolte->format('d/m/Y') }} @endif
            @if ($prelevement->cycle_coupe) · {{ $prelevement->cycle_coupe }} @endif
            @if ($prelevement->mode_conservation) · {{ $prelevement->mode_conservation }} @endif</p>
    </section>

    @foreach ($lots as $lot)
        @php($i = $lot['interpretation'])
        <div class="mb-6 space-y-6" wire:key="analyse-{{ $lot['analyse']->id }}">
            @if ($i)
                @php($n = InterpretationBulletin::niveau($i->niveau_global))
                <section class="carte" aria-labelledby="lecture-{{ $lot['analyse']->id }}">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 id="lecture-{{ $lot['analyse']->id }}" class="text-lg font-semibold">Ce qu'en dit votre technicien</h2>
                        <span class="flex flex-wrap gap-2">
                            <x-pastille :etat="$n['etat']" :libelle="$n['libelle']" />
                            @if ($agent && $i->statut !== 'publie') @php($s = InterpretationBulletin::statut($i->statut)) <x-pastille :etat="$s['etat']" :libelle="$s['libelle'].' — non publié'" /> @endif
                        </span>
                    </div>
                    @if ($i->synthese) <p class="mt-3 whitespace-pre-line">{{ $i->synthese }}</p> @endif
                    {{-- Ordre imposé des trois lectures (EF-M2-03) --}}
                    <dl class="mt-3 space-y-2 text-sm">
                        @foreach (['lecture_sanitaire' => 'Propreté et conservation', 'lecture_mineraux' => 'Minéraux', 'lecture_nutritionnelle' => 'Valeur nutritionnelle', 'recommandations' => 'Recommandations'] as $champ => $titre)
                            @if ($i->{$champ}) <div><dt class="font-medium">{{ $titre }}</dt><dd class="whitespace-pre-line">{{ $i->{$champ} }}</dd></div> @endif
                        @endforeach
                        @if ($i->utilisation_conseillee) <div><dt class="font-medium">Utilisation conseillée</dt><dd>{{ $i->utilisation_conseillee }}</dd></div> @endif
                        @if ($lot['deconseillee'] !== []) <div><dt class="font-medium">Déconseillé pour</dt><dd>{{ collect($lot['deconseillee'])->map(fn ($c) => $categories[$c] ?? $c)->implode(', ') }}</dd></div> @endif
                        @if ($i->orientation_veterinaire) <div><dt class="font-medium">Orientation vétérinaire</dt><dd>Votre technicien conseille d'en parler à votre vétérinaire.</dd></div> @endif
                    </dl>
                    @if ($lot['alertes'] !== [])
                        <h3 class="mt-4 font-medium">Points d'attention</h3>
                        <ul class="mt-2 space-y-2 text-sm">
                            @foreach ($lot['alertes'] as $l)
                                @php($e = InterpretationBulletin::niveau($l['alerte']->gravite))
                                <li class="flex flex-wrap items-start gap-2">
                                    <x-pastille :etat="$e['etat']" :libelle="'Gravité '.$l['alerte']->gravite" />
                                    <span>{{ $l['alerte']->message }}@if ($l['repere']) <span class="block text-xs text-texte-doux">Repère : {{ $l['repere'] }}</span>@endif
                                        @include('livewire.dossier.saisie.partiels.source', ['source' => Affichage::sourceSeuil($l['source'], $l['statut'], false)])</span>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endif

            <section class="carte" aria-labelledby="valeurs-{{ $lot['analyse']->id }}">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="valeurs-{{ $lot['analyse']->id }}" class="text-lg font-semibold">Résultats du bulletin {{ $lot['analyse']->num_echantillon_labo }}</h2>
                    @if ($url = $lienPdf($lot['analyse'])) <a href="{{ $url }}" class="lien text-sm">Bulletin original (PDF)</a> @endif
                </div>
                <div class="mt-2 overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <caption class="sr-only">Résultats du bulletin : valeur, classe, repère et source</caption>
                        <thead class="text-left text-texte-doux"><tr><th scope="col" class="py-1 pr-4">Paramètre</th><th scope="col" class="py-1">Valeur, classe et repère</th></tr></thead>
                        <tbody class="divide-y divide-filet">
                            @foreach ($lot['lignes'] as $l)
                                @continue($l['valeur'] === null)
                                <tr class="align-top">
                                    <th scope="row" class="py-1 pr-4 text-left font-medium">{{ $l['libelle'] }}@if ($l['calcule']) <span class="block text-xs font-normal text-texte-doux">calculé par le GDS</span>@endif</th>
                                    <td class="py-1">
                                        <x-valeur-repere :valeur="$l['censure'].Affichage::nombre($l['valeur'], $l['decimales']).($l['unite'] === '-' ? '' : ' '.$l['unite'])"
                                            :etat="InterpretationBulletin::classe($l['classe'], $l['gravite'])" :repere="$l['repere']"
                                            :source="Affichage::sourceSeuil($l['source'], $l['statut'], $l['a_valider'])" sans-repere="Sans repère dans la grille" />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p class="mt-2 text-xs text-texte-doux">La position du lot dans la campagne (références des élevages du GDS) sera affichée au lot L4.</p>
            </section>
        </div>
    @endforeach

    <x-limites :limites="$limites" :version="$lots[0]['interpretation']?->version_grille ?? null" />
</div>
