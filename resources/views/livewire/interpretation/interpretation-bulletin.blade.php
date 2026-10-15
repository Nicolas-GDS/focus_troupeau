@php
    use App\Livewire\Interpretation\InterpretationBulletin as Ecran;
    use App\Services\Interpretation\InterpretationService as Service;
@endphp
<div class="space-y-8">
    {{-- Bulletin et lot --}}
    <section class="carte" aria-labelledby="titre-bulletin">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="titre-bulletin" class="text-lg font-semibold">Bulletin {{ $analyse->num_echantillon_labo }}</h2>
                <p class="mt-1 text-sm text-texte-doux">
                    {{ $libelleElevage }} ({{ $elevage->code_pseudo }})
                    · {{ $prelevement->typeFourrage?->libelle ?? $prelevement->type_fourrage_code }}
                    @if ($prelevement->nom_lot) · {{ $prelevement->nom_lot }} @endif
                    · prélevé le {{ $prelevement->date_prelevement->format('d/m/Y') }}
                </p>
            </div>
            @if ($interpretation)
                @php($statut = Ecran::statut($interpretation->statut))
                <div class="text-right">
                    <x-pastille :etat="$statut['etat']" :libelle="$statut['libelle']" />
                    <p class="mt-1 text-xs text-texte-doux">Version {{ $interpretation->version }} · grille v{{ $interpretation->version_grille }}</p>
                </div>
            @endif
        </div>
    </section>

    @error('circuit')
        <div class="rounded-md border border-etat-critique bg-etat-critique-fond p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    @if (! $interpretation)
        <section class="carte" aria-labelledby="titre-creation">
            <h2 id="titre-creation" class="text-lg font-semibold">Interprétation</h2>
            @if ($peutCreer)
                <p class="mt-1 text-sm text-texte-doux">Le bulletin est contrôlé : préparez le brouillon. Les alertes automatiques de la grille
                    active et le niveau proposé sont calculés pour la catégorie cible choisie.</p>
                <form wire:submit="creer" class="mt-4 flex flex-wrap items-end gap-3">
                    <div>
                        <label for="categorie-creation" class="etiquette">Catégorie cible</label>
                        <select id="categorie-creation" wire:model="categorieCible" class="champ">
                            <option value="">Non précisée</option>
                            @foreach ($categories as $code => $libelle)
                                <option value="{{ $code }}">{{ $libelle }}</option>
                            @endforeach
                        </select>
                        @error('categorieCible') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="bouton-principal">Préparer l'interprétation</button>
                </form>
            @else
                <p class="mt-1 text-sm text-texte-doux">Aucune interprétation n'a encore été préparée pour ce bulletin.</p>
            @endif
        </section>
    @endif

    {{-- Résultats : valeur, classe, repère et source (EF-M2-02, EF-GEN-02, EF-GEN-03) --}}
    <section aria-labelledby="titre-resultats">
        <h2 id="titre-resultats" class="text-lg font-semibold">Résultats du bulletin</h2>
        <div class="mt-3 overflow-x-auto rounded-lg border border-filet bg-surface">
            <table class="min-w-full text-sm">
                <caption class="sr-only">Résultats, classe dans la grille d'interprétation, repère et source</caption>
                <thead class="bg-fond font-titre text-left text-texte-doux">
                    <tr>
                        <th scope="col" class="px-4 py-3">Paramètre</th>
                        <th scope="col" class="px-4 py-3">Valeur</th>
                        <th scope="col" class="px-4 py-3">Classe</th>
                        <th scope="col" class="px-4 py-3">Repère et source</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-filet">
                    @foreach ($resultats as $ligne)
                        @php($classe = Ecran::classe($ligne['classe'], $ligne['gravite']))
                        <tr wire:key="resultat-{{ $ligne['code'] }}" class="align-top">
                            <th scope="row" class="px-4 py-3 text-left font-medium">
                                {{ $ligne['libelle'] }}
                                @if ($ligne['calcule']) <span class="block text-xs font-normal text-texte-doux">calculé par le GDS</span> @endif
                            </th>
                            <td class="px-4 py-3 whitespace-nowrap">
                                @if ($ligne['valeur'] === null)
                                    —
                                @else
                                    {{ $ligne['censure'] }}{{ Service::nombre($ligne['valeur'], $ligne['decimales']) }} {{ $ligne['unite'] === '-' ? '' : $ligne['unite'] }}
                                @endif
                                @if ($ligne['censure'] === '<') <span class="block text-xs text-texte-doux">sous la limite de quantification</span> @endif
                                @if ($ligne['anomalie']) <span class="block text-xs text-etat-critique">hors plage plausible (anomalie levée)</span> @endif
                            </td>
                            <td class="px-4 py-3"><x-pastille :etat="$classe['etat']" :libelle="$classe['libelle']" /></td>
                            <td class="px-4 py-3">
                                @if ($ligne['repere'])
                                    <span>Classe pour {{ $ligne['repere'] }}</span>
                                    <details class="mt-1 text-xs text-texte-doux">
                                        <summary class="cursor-pointer">Source et statut</summary>
                                        <p>{{ $ligne['source'] ?? 'Source non renseignée' }}</p>
                                        @if ($ligne['statut'])
                                            <p>{{ $ligne['statut']->libelle() }} [{{ $ligne['statut']->codeSpecifications() }}]</p>
                                        @endif
                                    </details>
                                @else
                                    <span class="text-texte-doux">—</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>

    @if ($interpretation)
        @php($lectureSeule = ! $peutModifier)

        {{-- Catégorie cible (RC-1 : précise la grille) --}}
        <section class="carte" aria-labelledby="titre-categorie">
            <h2 id="titre-categorie" class="text-lg font-semibold">Catégorie cible</h2>
            @if ($lectureSeule)
                <p class="mt-1">{{ $interpretation->categorie_cible_code ? ($categories[$interpretation->categorie_cible_code] ?? $interpretation->categorie_cible_code) : 'Non précisée' }}</p>
            @else
                <form wire:submit="reclasser" class="mt-3 flex flex-wrap items-end gap-3">
                    <div>
                        <label for="categorie" class="etiquette">Catégorie animale à qui le lot est destiné</label>
                        <select id="categorie" wire:model="categorieCible" class="champ">
                            <option value="">Non précisée</option>
                            @foreach ($categories as $code => $libelle)
                                <option value="{{ $code }}">{{ $libelle }}</option>
                            @endforeach
                        </select>
                        @error('categorieCible') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                    <button type="submit" class="bouton-secondaire">Reclasser le bulletin</button>
                </form>
                <p class="mt-2 text-xs text-texte-doux">Le reclassement recalcule les alertes automatiques ; vos alertes ajoutées et vos écarts motivés sont conservés.</p>
            @endif
        </section>

        {{-- Alertes (EF-M2-02, EF-M2-03) --}}
        <section aria-labelledby="titre-alertes">
            <h2 id="titre-alertes" class="text-lg font-semibold">Alertes</h2>
            @if ($alertes === [])
                <p class="carte mt-3 text-texte-doux">Aucune alerte : aucune valeur n'atteint un seuil de vigilance de la grille.</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($alertes as $ligne)
                        @php($alerte = $ligne['alerte'])
                        @php($classe = Ecran::classe($alerte->classe, $alerte->gravite))
                        <li wire:key="alerte-{{ $alerte->id }}" class="carte {{ $alerte->retenue ? '' : 'opacity-75' }}">
                            <div class="flex flex-wrap items-center gap-2">
                                <x-pastille :etat="$classe['etat']" :libelle="$classe['libelle'].' (gravité '.$alerte->gravite.')'" />
                                @if ($alerte->parametre_code)
                                    <span class="font-medium">{{ $alerte->parametre_code }}</span>
                                    @if ($alerte->valeur !== null)
                                        <span>{{ $alerte->censure }}{{ Service::nombre((float) $alerte->valeur) }}</span>
                                    @endif
                                @endif
                                <span class="text-xs text-texte-doux">{{ $alerte->origine === 'technicien' ? 'Ajoutée par le technicien' : 'Automatique' }}</span>
                                <span class="text-xs font-medium">{{ $alerte->retenue ? 'Retenue' : 'Écartée' }}</span>
                            </div>
                            <p class="mt-2 text-sm">{{ $alerte->message }}</p>
                            @if ($ligne['repere'])
                                <p class="mt-1 text-xs text-texte-doux">
                                    Seuil : {{ $ligne['repere'] }} · Source : {{ $ligne['source'] ?? 'non renseignée' }}
                                    @if ($ligne['statut']) · {{ $ligne['statut']->libelle() }} [{{ $ligne['statut']->codeSpecifications() }}] @endif
                                </p>
                            @endif
                            @if (! $alerte->retenue && $alerte->motif_ecart)
                                <p class="mt-1 text-xs">Motif de l'écart : {{ $alerte->motif_ecart }}</p>
                            @endif

                            @unless ($lectureSeule)
                                <div class="mt-3 flex flex-wrap items-end gap-2">
                                    @if ($alerte->retenue)
                                        <div class="min-w-64 flex-1">
                                            <label for="motif-ecart-{{ $alerte->id }}" class="etiquette">Motif pour écarter cette alerte</label>
                                            <input id="motif-ecart-{{ $alerte->id }}" type="text" wire:model="motifsEcart.{{ $alerte->id }}" class="champ">
                                            @error('motifsEcart.'.$alerte->id) <p class="mt-1 text-xs text-etat-critique">{{ $message }}</p> @enderror
                                        </div>
                                        <button type="button" wire:click="ecarter({{ $alerte->id }})" class="bouton-secondaire">Écarter</button>
                                    @else
                                        <button type="button" wire:click="retenir({{ $alerte->id }})" class="bouton-secondaire">Retenir</button>
                                    @endif
                                </div>
                            @endunless
                        </li>
                    @endforeach
                </ul>
            @endif

            @unless ($lectureSeule)
                <form wire:submit="ajouterAlerte" class="carte mt-3 grid gap-3 sm:grid-cols-2">
                    <h3 class="font-semibold sm:col-span-2">Ajouter une alerte</h3>
                    <div>
                        <label for="alerte-parametre" class="etiquette">Paramètre (facultatif)</label>
                        <select id="alerte-parametre" wire:model="nouvelleAlerte.parametre" class="champ">
                            <option value="">Aucun</option>
                            @foreach ($parametresBulletin as $code => $libelle)
                                <option value="{{ $code }}">{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="alerte-gravite" class="etiquette">Gravité</label>
                        <select id="alerte-gravite" wire:model="nouvelleAlerte.gravite" class="champ">
                            @foreach ([0, 1, 2, 3] as $g)
                                <option value="{{ $g }}">{{ Ecran::niveau($g)['libelle'] }}</option>
                            @endforeach
                        </select>
                        @error('nouvelleAlerte.gravite') <p class="mt-1 text-xs text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="alerte-classe" class="etiquette">Classe</label>
                        <input id="alerte-classe" type="text" wire:model="nouvelleAlerte.classe" class="champ">
                        @error('nouvelleAlerte.classe') <p class="mt-1 text-xs text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="alerte-message" class="etiquette">Message</label>
                        <input id="alerte-message" type="text" wire:model="nouvelleAlerte.message" class="champ">
                        @error('nouvelleAlerte.message') <p class="mt-1 text-xs text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                    <div class="sm:col-span-2">
                        <button type="submit" class="bouton-secondaire">Ajouter l'alerte</button>
                    </div>
                </form>
            @endunless
        </section>

        {{-- Niveau global (RC-1.4) --}}
        <section class="carte" aria-labelledby="titre-niveau">
            <h2 id="titre-niveau" class="text-lg font-semibold">Niveau global</h2>
            @php($propose = Ecran::niveau($interpretation->niveau_propose))
            <p class="mt-2">Niveau proposé : <x-pastille :etat="$propose['etat']" :libelle="$propose['libelle']" /></p>
            @if ($motifsRehaussement !== [])
                <ul class="mt-2 list-disc pl-5 text-sm">
                    @foreach ($motifsRehaussement as $motif)
                        <li>Rehaussement proposé : {{ $motif }}</li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-1 text-xs text-texte-doux">Gravité la plus forte des alertes retenues, avec les rehaussements de RC-1.4 ; proposition seulement, le niveau retenu vous appartient.</p>

            @if ($lectureSeule)
                @php($retenu = Ecran::niveau($interpretation->niveau_global))
                <p class="mt-3">Niveau retenu : <x-pastille :etat="$retenu['etat']" :libelle="$retenu['libelle']" /></p>
            @else
                <div class="mt-3">
                    <label for="niveau-retenu" class="etiquette">Niveau retenu</label>
                    <select id="niveau-retenu" wire:model="niveauRetenu" class="champ">
                        <option value="">À fixer…</option>
                        @foreach ([0, 1, 2, 3] as $n)
                            <option value="{{ $n }}">{{ Ecran::niveau($n)['libelle'] }}</option>
                        @endforeach
                    </select>
                    @error('niveauRetenu') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    <p class="mt-1 text-xs text-texte-doux">Un niveau 3 est publié après le visa du vétérinaire conseil.</p>
                </div>
            @endif
        </section>

        {{-- Rédaction (EF-M2-03) : trois lectures dans l'ordre imposé --}}
        <section class="carte space-y-4" aria-labelledby="titre-redaction">
            <h2 id="titre-redaction" class="text-lg font-semibold">Lectures et synthèse</h2>

            @foreach ([
                ['lectureConservation', '1. Propreté et conservation', $interpretation->lecture_sanitaire],
                ['lectureMineraux', '2. Minéraux', $interpretation->lecture_mineraux],
                ['lectureNutritionnelle', '3. Valeur nutritionnelle', $interpretation->lecture_nutritionnelle],
                ['synthese', 'Synthèse (3 à 5 lignes)', $interpretation->synthese],
                ['recommandations', 'Recommandations', $interpretation->recommandations],
            ] as [$champ, $titre, $texte])
                <div>
                    @if ($lectureSeule)
                        <h3 class="font-semibold">{{ $titre }}</h3>
                        <p class="mt-1 whitespace-pre-line text-sm">{{ $texte ?: '—' }}</p>
                    @else
                        <label for="{{ $champ }}" class="etiquette">{{ $titre }}</label>
                        <textarea id="{{ $champ }}" wire:model="{{ $champ }}" rows="{{ $champ === 'synthese' ? 5 : 3 }}" class="champ"></textarea>
                        @error($champ) <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    @endif
                </div>
            @endforeach
        </section>

        {{-- Utilisation du lot et orientation vétérinaire --}}
        <section class="carte space-y-4" aria-labelledby="titre-utilisation">
            <h2 id="titre-utilisation" class="text-lg font-semibold">Utilisation du lot</h2>
            @if ($lectureSeule)
                <p><span class="font-medium">Conseillée :</span> {{ $interpretation->utilisation_conseillee ?: '—' }}</p>
                <p><span class="font-medium">Déconseillée :</span>
                    {{ $deconseillees === [] ? '—' : collect($deconseillees)->map(fn ($c) => $categories[$c] ?? $c)->implode(' ; ') }}</p>
                <p><span class="font-medium">Orientation vétérinaire :</span> {{ $interpretation->orientation_veterinaire ? 'oui' : 'non' }}</p>
            @else
                <div>
                    <label for="utilisationConseillee" class="etiquette">Utilisation conseillée</label>
                    <input id="utilisationConseillee" type="text" wire:model="utilisationConseillee" class="champ">
                </div>
                <fieldset>
                    <legend class="etiquette">Utilisation déconseillée</legend>
                    <div class="mt-1 grid gap-1 sm:grid-cols-2">
                        @foreach ($categories as $code => $libelle)
                            <label class="flex items-center gap-2 text-sm">
                                <input type="checkbox" wire:model="utilisationDeconseillee" value="{{ $code }}"> {{ $libelle }}
                            </label>
                        @endforeach
                    </div>
                    @error('utilisationDeconseillee.*') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </fieldset>
                <label class="flex items-center gap-2">
                    <input type="checkbox" wire:model="orientationVeterinaire"> Orientation vers le vétérinaire traitant
                </label>
            @endif
        </section>

        {{-- Circuit (EF-M2-04) --}}
        @if ($peutModifier || $peutViser || $peutPublier)
            <div class="flex flex-wrap gap-3">
                @if ($peutModifier)
                    <button type="button" wire:click="enregistrer" class="bouton-secondaire">Enregistrer le brouillon</button>
                    <button type="button" wire:click="valider" class="bouton-principal">Valider l'interprétation</button>
                @endif
                @if ($peutViser)
                    <button type="button" wire:click="viser" class="bouton-principal">Viser (niveau 3)</button>
                @endif
                @if ($peutPublier)
                    <button type="button" wire:click="publier" class="bouton-principal">Publier</button>
                @endif
            </div>
        @endif

        {{-- Refus de visa (EF-M2-04) : retour en brouillon avec motif --}}
        @if ($peutViser)
            <div class="carte">
                <label for="motif-refus-visa" class="etiquette">Refuser le visa — motif adressé au technicien</label>
                <textarea id="motif-refus-visa" wire:model="motifRefusVisa" rows="2" class="champ"></textarea>
                @error('motifRefusVisa') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                <button type="button" wire:click="refuserVisa" class="bouton-secondaire mt-2">Refuser le visa</button>
            </div>
        @endif

        @if ($interpretation->refus_visa_motif && $interpretation->statut === 'brouillon')
            <div class="rounded-md border border-etat-alerte bg-etat-alerte-fond p-3 text-sm" role="note">
                <x-pastille etat="alerte" libelle="Visa refusé" />
                <p class="mt-1">{{ $interpretation->refus_visa_motif }}</p>
                <p class="mt-1 text-xs text-texte-doux">Le {{ $interpretation->refus_visa_le?->format('d/m/Y à H:i') }}</p>
            </div>
        @endif

        @if ($interpretation->statut === 'valide' && $interpretation->niveau_global === 3 && ! $peutViser)
            <p class="text-sm text-texte-doux">Niveau 3 : en attente du visa du vétérinaire conseil avant publication.</p>
        @endif
    @endif
</div>
