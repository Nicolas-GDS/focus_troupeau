@php
    use App\Livewire\Dossier\Etats;
    use App\Livewire\Dossier\Interpretation\EcranVisite;
    use App\Livewire\Dossier\Interpretation\PlanActions;
    use App\Livewire\Dossier\Visites;
    use App\Livewire\Interpretation\InterpretationBulletin;
    use App\Services\Dossier\Affichage;
    use App\Services\Dossier\FicheVisiteService as Fiche;
    $jalons = PlanActions::jalons();
    $familles = EcranVisite::familles();
    $responsables = ['eleveur' => 'Éleveur', 'technicien' => 'Technicien', 'veterinaire' => 'Vétérinaire'];
    $natures = \App\Models\Dossier\EtiquetteAliment::LIBELLES_NATURES;
@endphp
<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'visites'])

    @if (! $synthese)
        <p class="carte text-texte-doux">L'interprétation de cette visite n'est pas encore rédigée.@if ($lienInterpretation) <a href="{{ $lienInterpretation }}" class="lien">Rédiger l'interprétation</a>@endif</p>
    @else
        @php
            $interpretation = $synthese['interpretation'];
            $niveau = InterpretationBulletin::niveau($interpretation->niveau_global);
            $statut = EcranVisite::statut($interpretation->statut);
            $controles = $synthese['actions']->flatten()->flatMap(fn ($a) => $a->controles);
        @endphp
        <div class="space-y-6">
            {{-- L'essentiel --}}
            <section class="carte" aria-labelledby="titre-essentiel">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <h2 id="titre-essentiel" class="text-lg font-semibold">{{ $interpretation->titre ?: 'Votre visite d\'approche globale' }}</h2>
                    @if ($agent && $interpretation->statut !== 'publie') <x-pastille :etat="$statut['etat']" :libelle="$statut['libelle'].' — non publié'" /> @endif
                </div>
                <p class="mt-2"><x-pastille :etat="$niveau['etat']" :libelle="$niveau['libelle']" /></p>
                @if ($interpretation->synthese) <p class="mt-3 whitespace-pre-line">{{ $interpretation->synthese }}</p> @endif
                @if ($synthese['messages']->isNotEmpty())
                    <ol class="mt-3 space-y-2" aria-label="Messages classés par gravité">
                        @foreach ($synthese['messages']->sortByDesc('gravite') as $message)
                            <x-message-hierarchise :message="$message" wire:key="message-{{ $message->id }}" />
                        @endforeach
                    </ol>
                @endif
                <p class="mt-4 flex flex-wrap gap-4 text-sm">
                    @if ($lienFiche) <a href="{{ $lienFiche }}" class="lien">Télécharger la fiche (PDF, version {{ $interpretation->version }})</a> @endif
                    @if ($lienInterpretation) <a href="{{ $lienInterpretation }}" class="lien">Interprétation de la visite (E21)</a> @endif
                </p>
            </section>

            {{-- 1. Collecter --}}
            <section class="carte" aria-labelledby="etape-collecter">
                <h2 id="etape-collecter" class="text-lg font-semibold">1. Collecter</h2>
                <p class="mt-1 text-sm">Visite du {{ $visite->date_visite->format('d/m/Y') }}@if ($technicien) avec {{ $technicien }}@endif · {{ Visites::motifs()[$visite->motif] ?? $visite->motif }} · campagne {{ $visite->campagne_code ?? 'non déterminée' }}</p>
                @if ($synthese['prelevements']->isNotEmpty())
                    <ul class="mt-2 list-disc pl-5 text-sm">
                        @foreach ($synthese['prelevements'] as $p)
                            <li>{{ $p->typeFourrage?->libelle ?? $p->type_fourrage_code }}@if ($p->nom_lot) « {{ $p->nom_lot }} »@endif, prélevé le {{ $p->date_prelevement->format('d/m/Y') }}</li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-sm text-texte-doux">Aucun fourrage prélevé lors de cette visite.</p>
                @endif
            </section>

            {{-- 2. Analyser : fourrages, eau, minéraux distribués --}}
            <section class="carte" aria-labelledby="etape-analyser">
                <h2 id="etape-analyser" class="text-lg font-semibold">2. Analyser</h2>
                @if ($synthese['lots'] !== [])
                    <h3 class="mt-3 font-medium">Vos fourrages</h3>
                    <div class="mt-2 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Valeurs des lots analysés, avec leur classe, leur repère et sa source</caption>
                            <thead class="text-left text-texte-doux">
                                <tr>
                                    <th scope="col" class="py-1 pr-4">Lot</th>
                                    @foreach (Fiche::PARAMETRES_LOTS as $code)
                                        <th scope="col" class="py-1 pr-4">{{ $code === 'UFL_2018' ? 'UFL' : $code }} <span class="font-normal">{{ $synthese['unitesParametres'][$code] ?? '' }}</span></th>
                                    @endforeach
                                    <th scope="col" class="py-1 pr-4">Niveau</th>
                                    <th scope="col" class="py-1 pr-4">Position campagne</th>
                                    <th scope="col" class="py-1">Utilisation conseillée</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-filet">
                                @foreach ($synthese['lots'] as $lot)
                                    <tr class="align-top" wire:key="lot-{{ $lot['analyse']->id }}">
                                        <th scope="row" class="py-2 pr-4 text-left font-medium">
                                            <a href="{{ route('dossier.fourrage', [$elevage->id, $lot['prelevement']->id]) }}" class="lien">{{ $lot['prelevement']->typeFourrage?->libelle ?? $lot['prelevement']->type_fourrage_code }}</a>
                                            @if ($lot['prelevement']->nom_lot) <span class="block font-normal">« {{ $lot['prelevement']->nom_lot }} »</span> @endif
                                        </th>
                                        @foreach (Fiche::PARAMETRES_LOTS as $code)
                                            @php($v = $lot['valeurs'][$code] ?? null)
                                            <td class="py-2 pr-4">
                                                @if ($v && $v['valeur'] !== null)
                                                    <x-valeur-repere :valeur="$v['censure'].Affichage::nombre($v['valeur'], $v['decimales'])" :etat="InterpretationBulletin::classe($v['classe'], $v['gravite'])" :repere="$v['repere']" :source="$v['source']" sans-repere="Sans repère dans la grille" />
                                                @else
                                                    <span class="text-texte-doux">non dosé</span>
                                                @endif
                                            </td>
                                        @endforeach
                                        <td class="py-2 pr-4">@if ($lot['interpretation']) @php($n = InterpretationBulletin::niveau($lot['interpretation']->niveau_global)) <x-pastille :etat="$n['etat']" :libelle="$n['libelle']" /> @endif</td>
                                        <td class="py-2 pr-4">@forelse ($lot['positions'] as $code => $position)<span class="block">{{ $code === 'UFL_2018' ? 'UFL' : $code }} <x-position :position="$position" /></span>@empty<span class="text-texte-doux">—</span>@endforelse</td>
                                        <td class="py-2">{{ $lot['interpretation']?->utilisation_conseillee ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="mt-2 text-sm"><a href="{{ route('dossier.matrice', ['elevage' => $elevage->id, 'campagne' => $visite->campagne_code]) }}" class="lien">Matrice de vigilance minérale de la campagne</a></p>
                @endif

                @if ($synthese['positionnement'] !== [])
                    <h3 class="mt-4 font-medium">Où se situent vos fourrages dans la campagne ?</h3>
                    <p class="mt-1 text-sm text-texte-doux">Points gris : les autres lots du même type de la campagne ; bande : moitié centrale des valeurs (Q1 à Q3) ; trait pointillé : médiane ; points pleins : vos lots.</p>
                    @foreach ($synthese['positionnement'] as $section)
                        <h4 class="mt-3 text-sm font-semibold">{{ $section['type'] }}</h4>
                        @foreach ($section['graphiques'] as $g)
                            <x-positionnement :parametre="$g['libelle']" :unite="$g['unite']" :decimales="$g['decimales']" :reference="$g['reference']" :population="$g['population']" :valeurs="$g['valeurs']" :lots="$g['lots']" />
                        @endforeach
                    @endforeach
                @endif

                @foreach ($synthese['eau'] as $item)
                    <h3 class="mt-4 font-medium">Eau — {{ $item['analyse']->point_prelevement }} ({{ $item['analyse']->date_prelevement->format('d/m/Y') }})</h3>
                    @include('livewire.dossier.lecture.partiels.eau', ['lignes' => $item['lignes']])
                @endforeach

                @foreach ($synthese['etiquettes'] as $item)
                    <div class="mt-4 rounded-md border border-filet p-3">
                        @include('livewire.dossier.partiels.etiquette', ['item' => $item, 'datePeremption' => 'à la date de la visite'])
                    </div>
                @endforeach

                @if ($synthese['lots'] === [] && $synthese['eau'] === [] && $synthese['etiquettes'] === [])
                    <p class="mt-2 text-sm text-texte-doux">Aucune analyse rattachée à cette visite.</p>
                @endif
            </section>

            {{-- 3. Mesurer : animaux --}}
            <section class="carte" aria-labelledby="etape-mesurer">
                <h2 id="etape-mesurer" class="text-lg font-semibold">3. Mesurer</h2>
                @include('livewire.dossier.lecture.partiels.animaux', ['mesures' => $synthese['mesures'], 'profils' => $synthese['profils']])
                @if ($synthese['mesures'] === [] && $synthese['profils'] === [])
                    <p class="mt-2 text-sm text-texte-doux">Aucune mesure sur les animaux lors de cette visite.</p>
                @endif
            </section>

            {{-- 4. Croiser : bilan sanitaire et causes communes --}}
            <section class="carte" aria-labelledby="etape-croiser">
                <h2 id="etape-croiser" class="text-lg font-semibold">4. Croiser</h2>
                @if ($synthese['bilan'] !== [])
                    <h3 class="mt-3 font-medium">Bilan sanitaire de la campagne</h3>
                    @include('livewire.dossier.lecture.partiels.bilan', ['lignes' => $synthese['bilan']])
                @endif
                @if ($synthese['causes']->isNotEmpty())
                    <h3 class="mt-4 font-medium">Ce qui relie les troubles</h3>
                    <ul class="mt-2 space-y-3">
                        @foreach ($synthese['causes'] as $c)
                            <li class="rounded-md border border-filet p-3 text-sm">
                                <p class="font-medium">{{ $c->titre }} <span class="font-normal text-texte-doux">({{ $familles[$c->famille_cause] ?? $c->famille_cause }})</span></p>
                                @if ($c->preuves) <p class="mt-1"><span class="font-medium">Preuves :</span> {{ $c->preuves }}</p> @endif
                                @if ($c->consequences) <p class="mt-1"><span class="font-medium">Conséquences :</span> {{ $c->consequences }}</p> @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($synthese['bilan'] === [] && $synthese['causes']->isEmpty())
                    <p class="mt-2 text-sm text-texte-doux">Aucun élément croisé pour cette visite.</p>
                @endif
            </section>

            {{-- 5. Agir : plan d'action --}}
            <section class="carte" aria-labelledby="etape-agir">
                <h2 id="etape-agir" class="text-lg font-semibold">5. Agir</h2>
                @forelse ($synthese['actions'] as $priorite => $actions)
                    <h3 class="mt-3 font-medium">Priorité {{ $priorite }}</h3>
                    <ul class="mt-1 space-y-2 text-sm">
                        @foreach ($actions as $a)
                            <li wire:key="action-{{ $a->id }}">
                                <span class="font-medium">{{ $a->titre }}</span> <span class="rounded bg-fond px-2 py-0.5 text-xs">{{ $responsables[$a->responsable] ?? $a->responsable }}</span>
                                @if ($a->comment) <span class="block">Comment : {{ $a->comment }}</span> @endif
                                <span class="block text-texte-doux">Quand : {{ collect([$a->quand, $a->jalon ? ($jalons[$a->jalon] ?? $a->jalon) : null, $a->echeance?->format('d/m/Y')])->filter()->implode(' · ') ?: 'non précisé' }}</span>
                            </li>
                        @endforeach
                    </ul>
                @empty
                    <p class="mt-2 text-sm text-texte-doux">Aucune action proposée.</p>
                @endforelse
            </section>

            {{-- 6. Suivre : actions faites, contrôles --}}
            <section class="carte" aria-labelledby="etape-suivre">
                <h2 id="etape-suivre" class="text-lg font-semibold">6. Suivre</h2>
                @php($toutes = $synthese['actions']->flatten())
                @if ($toutes->isNotEmpty())
                    <p class="mt-1 text-sm">{{ $toutes->where('statut', 'faite')->count() }} action(s) faite(s) sur {{ $toutes->count() }}.</p>
                @endif
                @if ($controles->isNotEmpty())
                    <ul class="mt-2 space-y-2 text-sm">
                        @foreach ($controles as $c)
                            <li wire:key="controle-{{ $c->id }}">
                                {{ $c->mesure_code ? $c->mesure_code.' — ' : '' }}cible : {{ $c->cible }} · échéance {{ $c->echeance->format('d/m/Y') }}
                                <x-pastille :etat="$c->statut === 'realise' ? 'couvert' : ($c->echeance->isPast() ? 'vigilance' : 'information')" :libelle="$c->statut === 'realise' ? 'Réalisé' : ($c->echeance->isPast() ? 'Échu' : 'Planifié')" />
                                @if ($c->resultat) <span class="block text-texte-doux">Résultat : {{ $c->resultat }}</span> @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="mt-2 text-sm text-texte-doux">Aucun contrôle prévu.</p>
                @endif
                <p class="mt-3 text-sm"><a href="{{ route('dossier.plan', $elevage->id) }}" class="lien">Suivre le plan d'action</a></p>
            </section>
        </div>

        <x-limites :limites="$limites" :version="$interpretation->version_grille"
            :non-doses="$synthese['nonDoses'] !== [] && $synthese['lots'] !== [] ? implode(', ', array_map(fn ($c) => $synthese['libellesParametres'][$c] ?? $c, $synthese['nonDoses'])) : null">
            @if ($interpretation->limites) {{ $interpretation->limites }} @endif
        </x-limites>
    @endif
</div>
