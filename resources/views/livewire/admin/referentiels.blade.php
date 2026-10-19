@php($composant = \App\Livewire\Admin\Referentiels::class)
@php($seuils = $table === 'seuils')
@php($unites = $parametres->pluck('unite', 'code'))
<div class="space-y-6">
    {{-- Version courante (EF-M10-02) --}}
    <section class="carte" aria-labelledby="titre-version">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 id="titre-version" class="text-lg font-semibold">Version courante des référentiels : {{ $versionCourante?->numero ?? '—' }}</h2>
                @if ($versionCourante)
                    <p class="mt-1 text-sm text-texte-doux">
                        Du {{ $versionCourante->date_version->format('d/m/Y') }}@if ($auteurCourant), par {{ $auteurCourant }}@endif.
                        Décision : {{ $versionCourante->commentaire ?? '—' }}
                    </p>
                @endif
                <p class="mt-2 text-sm text-texte-doux">Chaque enregistrement crée une nouvelle version : rien n'est effacé, les interprétations
                    conservent la version de grille avec laquelle elles ont été établies.</p>
            </div>
        </div>

        <nav aria-label="Référentiel édité" class="mt-4 flex flex-wrap gap-2">
            @foreach ($composant::TABLES as $cle => $libelle)
                <a href="{{ route('admin.referentiels', ['table' => $cle]) }}" class="lien-navigation" @if ($table === $cle) aria-current="page" @endif>{{ $libelle }}</a>
            @endforeach
        </nav>
    </section>

    @if (session('status'))
        <p class="carte" role="status"><x-pastille etat="couvert" libelle="Enregistré" /> {{ session('status') }}</p>
    @endif

    <div class="flex flex-wrap items-end justify-between gap-4">
        <div role="tablist" aria-label="Affichage" class="flex gap-2">
            <button type="button" role="tab" wire:click="afficher('lignes')" aria-selected="{{ $onglet === 'lignes' ? 'true' : 'false' }}"
                    class="{{ $onglet === 'lignes' ? 'bouton-principal' : 'bouton-secondaire' }}">Lignes actives</button>
            <button type="button" role="tab" wire:click="afficher('historique')" aria-selected="{{ $onglet === 'historique' ? 'true' : 'false' }}"
                    class="{{ $onglet === 'historique' ? 'bouton-principal' : 'bouton-secondaire' }}">Historique des versions</button>
        </div>

        @if ($onglet === 'lignes')
            <div class="flex flex-wrap items-end gap-2">
                <div>
                    <label for="filtre-parametre" class="etiquette">Paramètre</label>
                    <select id="filtre-parametre" wire:model.live="filtreParametre" class="champ">
                        <option value="">Tous</option>
                        @foreach ($parametres as $parametre)
                            <option value="{{ $parametre->code }}">{{ $parametre->code }} — {{ $parametre->libelle }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($peutEcrire)
                    <button type="button" wire:click="nouveau" class="bouton-principal">{{ $seuils ? 'Nouveau seuil' : 'Nouvelle plage' }}</button>
                @endif
            </div>
        @endif
    </div>

    {{-- Formulaire de création / modification --}}
    @if ($formulaireOuvert)
        <section class="carte" aria-labelledby="titre-formulaire">
            <h2 id="titre-formulaire" class="text-lg font-semibold">
                {{ $editionId === null ? ($seuils ? 'Nouveau seuil' : 'Nouvelle plage de plausibilité') : 'Modifier la ligne n° '.$editionId }}
            </h2>
            @if ($editionId !== null)
                <p class="mt-1 text-sm text-texte-doux">La ligne actuelle sera clôturée et remplacée par la ligne modifiée dans une nouvelle version.</p>
            @endif
            @error('formulaire') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror

            <form wire:submit="enregistrer" class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div>
                    <label for="f-parametre" class="etiquette">Paramètre</label>
                    <select id="f-parametre" wire:model="formulaire.parametre_code" class="champ">
                        <option value="">Choisir…</option>
                        @foreach ($parametres as $parametre)
                            <option value="{{ $parametre->code }}">{{ $parametre->code }} — {{ $parametre->libelle }} ({{ $parametre->unite }})</option>
                        @endforeach
                    </select>
                    @error('formulaire.parametre_code') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="f-famille" class="etiquette">Famille de fourrage</label>
                    <select id="f-famille" wire:model="formulaire.famille_fourrage_code" class="champ">
                        <option value="">Tous fourrages</option>
                        @foreach ($familles as $code => $libelle)
                            <option value="{{ $code }}">{{ $libelle }}</option>
                        @endforeach
                    </select>
                    @error('formulaire.famille_fourrage_code') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>

                @if ($seuils)
                    <div>
                        <label for="f-type" class="etiquette">Type de fourrage</label>
                        <select id="f-type" wire:model="formulaire.type_fourrage_code" class="champ">
                            <option value="">Toute la famille</option>
                            @foreach ($types as $type)
                                <option value="{{ $type->code }}">{{ $type->libelle }} ({{ $type->famille_code }})</option>
                            @endforeach
                        </select>
                        @error('formulaire.type_fourrage_code') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="f-espece" class="etiquette">Espèce</label>
                        <select id="f-espece" wire:model="formulaire.espece_code" class="champ">
                            <option value="">Toutes espèces</option>
                            @foreach ($especes as $code => $libelle)
                                <option value="{{ $code }}">{{ $libelle }}</option>
                            @endforeach
                        </select>
                        @error('formulaire.espece_code') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="f-categorie" class="etiquette">Catégorie animale</label>
                        <select id="f-categorie" wire:model="formulaire.categorie_code" class="champ">
                            <option value="">Toutes catégories</option>
                            @foreach ($categories as $categorie)
                                <option value="{{ $categorie->code }}">{{ $categorie->libelle }} ({{ $categorie->espece_code }})</option>
                            @endforeach
                        </select>
                        @error('formulaire.categorie_code') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="f-nature" class="etiquette">Nature</label>
                        <select id="f-nature" wire:model="formulaire.nature" class="champ">
                            <option value="">Choisir…</option>
                            @foreach ($natures as $code => $libelle)
                                <option value="{{ $code }}">{{ $libelle }}</option>
                            @endforeach
                        </select>
                        @error('formulaire.nature') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label for="f-min" class="etiquette">Borne basse (incluse)</label>
                    <input id="f-min" type="text" inputmode="decimal" wire:model="formulaire.borne_min" class="champ" aria-describedby="aide-bornes">
                    @error('formulaire.borne_min') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="f-max" class="etiquette">Borne haute {{ $seuils ? '(exclue)' : '(incluse)' }}</label>
                    <input id="f-max" type="text" inputmode="decimal" wire:model="formulaire.borne_max" class="champ" aria-describedby="aide-bornes">
                    @error('formulaire.borne_max') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>

                @if ($seuils)
                    <div>
                        <label for="f-classe" class="etiquette">Classe</label>
                        <input id="f-classe" type="text" wire:model="formulaire.classe" class="champ" placeholder="faible, carence_probable…">
                        @error('formulaire.classe') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="f-gravite" class="etiquette">Gravité</label>
                        <select id="f-gravite" wire:model="formulaire.gravite" class="champ">
                            <option value="">Choisir…</option>
                            @foreach ([0, 1, 2, 3] as $g)
                                <option value="{{ $g }}">{{ $composant::gravite($g)['libelle'] }}</option>
                            @endforeach
                        </select>
                        @error('formulaire.gravite') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>

                    <div>
                        <label for="f-sens" class="etiquette">Sens</label>
                        <select id="f-sens" wire:model="formulaire.sens" class="champ">
                            <option value="">Choisir…</option>
                            <option value="bas">{{ $composant::sens('bas') }}</option>
                            <option value="haut">{{ $composant::sens('haut') }}</option>
                        </select>
                        @error('formulaire.sens') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div>
                    <label for="f-statut" class="etiquette">Statut épistémique</label>
                    <select id="f-statut" wire:model="formulaire.statut_epistemique" class="champ">
                        @foreach ($statuts as $statut)
                            <option value="{{ $statut->value }}">{{ $statut->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('formulaire.statut_epistemique') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>

                @if ($seuils)
                    <div>
                        <label for="f-reference" class="etiquette">Source (référence bibliographique)</label>
                        <select id="f-reference" wire:model="formulaire.reference_id" class="champ">
                            <option value="">Aucune</option>
                            @foreach ($references as $reference)
                                <option value="{{ $reference->id }}">{{ $reference->cle }} — {{ \Illuminate\Support\Str::limit($reference->titre, 60) }}</option>
                            @endforeach
                        </select>
                        @error('formulaire.reference_id') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>

                    <div class="sm:col-span-2 lg:col-span-3">
                        <label for="f-message" class="etiquette">Message proposé au technicien (EF-M10-03)</label>
                        <textarea id="f-message" wire:model="formulaire.message" rows="2" class="champ"></textarea>
                        @error('formulaire.message') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div>
                        <label for="f-source" class="etiquette">Source</label>
                        <input id="f-source" type="text" wire:model="formulaire.source" class="champ">
                    </div>
                @endif

                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="f-commentaire" class="etiquette">Commentaire de la ligne (facultatif)</label>
                    <input id="f-commentaire" type="text" wire:model="formulaire.commentaire" class="champ">
                </div>

                <p id="aide-bornes" class="text-sm text-texte-doux sm:col-span-2 lg:col-span-3">
                    {{ $seuils ? 'Intervalle [borne basse ; borne haute[ : une borne vide n\'est pas bornée, au moins une est obligatoire. Deux seuils actifs du même paramètre et du même champ d\'application ne peuvent pas se chevaucher.'
                               : 'Plage acceptée [borne basse ; borne haute] : une borne vide n\'est pas bornée. Une seule plage active par paramètre et famille.' }}
                    Les nombres s'écrivent avec une virgule ou un point.
                </p>

                <div class="sm:col-span-2 lg:col-span-3">
                    <label for="f-decision" class="etiquette">Décision du comité technique (commentaire de version, obligatoire)</label>
                    <textarea id="f-decision" wire:model="decision" rows="2" class="champ"></textarea>
                    @error('decision') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-wrap gap-2 sm:col-span-2 lg:col-span-3">
                    <button type="submit" class="bouton-principal">Enregistrer une nouvelle version</button>
                    <button type="button" wire:click="annuler" class="bouton-secondaire">Annuler</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Confirmation de retrait --}}
    @if ($retraitId !== null)
        <section class="carte" aria-labelledby="titre-retrait">
            <h2 id="titre-retrait" class="text-lg font-semibold">Retirer la ligne n° {{ $retraitId }}</h2>
            <p class="mt-1 text-sm text-texte-doux">La ligne est clôturée dans une nouvelle version ; elle reste dans l'historique.</p>
            @error('formulaire') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
            <form wire:submit="retirer" class="mt-4 space-y-3">
                <div>
                    <label for="r-decision" class="etiquette">Décision du comité technique (commentaire de version, obligatoire)</label>
                    <textarea id="r-decision" wire:model="decision" rows="2" class="champ"></textarea>
                    @error('decision') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div class="flex flex-wrap gap-2">
                    <button type="submit" class="bouton-principal">Retirer</button>
                    <button type="button" wire:click="annuler" class="bouton-secondaire">Annuler</button>
                </div>
            </form>
        </section>
    @endif

    @if ($onglet === 'lignes')
        <section aria-labelledby="titre-lignes">
            <h2 id="titre-lignes" class="text-lg font-semibold">{{ $composant::TABLES[$table] }} — lignes actives ({{ $lignes->count() }})</h2>

            @if ($lignes->isEmpty())
                <p class="carte mt-3 text-texte-doux">Aucune ligne active{{ $filtreParametre !== '' ? ' pour ce paramètre' : '' }}.</p>
            @else
                <div class="mt-3 overflow-x-auto rounded-lg border border-filet bg-surface" tabindex="0">
                    <table class="min-w-full text-sm">
                        <caption class="sr-only">{{ $composant::TABLES[$table] }} : lignes en vigueur</caption>
                        <thead class="bg-fond font-titre text-left text-texte-doux">
                            <tr>
                                <th scope="col" class="px-3 py-3">N°</th>
                                <th scope="col" class="px-3 py-3">Paramètre</th>
                                <th scope="col" class="px-3 py-3">Champ d'application</th>
                                <th scope="col" class="px-3 py-3">{{ $seuils ? 'Intervalle' : 'Plage acceptée' }}</th>
                                @if ($seuils)
                                    <th scope="col" class="px-3 py-3">Classe et sens</th>
                                    <th scope="col" class="px-3 py-3">Gravité</th>
                                @endif
                                <th scope="col" class="px-3 py-3">Statut et source</th>
                                <th scope="col" class="px-3 py-3">Version</th>
                                @if ($peutEcrire)
                                    <th scope="col" class="px-3 py-3"><span class="sr-only">Actions</span></th>
                                @endif
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-filet">
                            @foreach ($lignes as $ligne)
                                @php($statut = \App\Domain\Referentiels\StatutEpistemique::tryFrom($ligne->statut_epistemique))
                                <tr wire:key="ligne-{{ $ligne->id }}" class="align-top">
                                    <td class="px-3 py-3">{{ $ligne->id }}</td>
                                    <td class="px-3 py-3 font-medium">{{ $ligne->parametre_code }}</td>
                                    <td class="px-3 py-3">
                                        @php($portee = array_filter([
                                            $ligne->famille_fourrage_code ? ($familles[$ligne->famille_fourrage_code] ?? $ligne->famille_fourrage_code) : null,
                                            $seuils ? $ligne->type_fourrage_code : null,
                                            $seuils ? ($ligne->espece_code ? ($especes[$ligne->espece_code] ?? $ligne->espece_code) : null) : null,
                                            $seuils ? $ligne->categorie_code : null,
                                        ]))
                                        {{ $portee === [] ? 'Tous fourrages'.($seuils ? ', toutes espèces' : '') : implode(' · ', $portee) }}
                                    </td>
                                    <td class="px-3 py-3 whitespace-nowrap">
                                        {{ $composant::intervalle($ligne->borne_min, $ligne->borne_max, $seuils) }}
                                        <span class="text-texte-doux">{{ $unites[$ligne->parametre_code] ?? '' }}</span>
                                    </td>
                                    @if ($seuils)
                                        <td class="px-3 py-3">
                                            {{ $ligne->classe }}
                                            <span class="block text-xs text-texte-doux">{{ $composant::sens($ligne->sens) }}</span>
                                            @if ($ligne->message)
                                                <span class="mt-1 block text-xs text-texte-doux">{{ $ligne->message }}</span>
                                            @endif
                                        </td>
                                        <td class="px-3 py-3">
                                            @php($g = $composant::gravite($ligne->gravite))
                                            <x-pastille :etat="$g['etat']" :libelle="$g['libelle']" />
                                        </td>
                                    @endif
                                    <td class="px-3 py-3">
                                        {{ $statut?->libelle() ?? $ligne->statut_epistemique }}
                                        @if ($statut?->aValider())
                                            <x-pastille etat="vigilance" libelle="À valider" class="mt-1" />
                                        @endif
                                        <span class="block text-xs text-texte-doux">
                                            Source : {{ $seuils ? ($ligne->reference?->cle ?? 'non renseignée') : ($ligne->source ?? 'non renseignée') }}
                                        </span>
                                    </td>
                                    <td class="px-3 py-3">{{ $ligne->version_creation }}</td>
                                    @if ($peutEcrire)
                                        <td class="px-3 py-3 whitespace-nowrap">
                                            <button type="button" wire:click="modifier({{ $ligne->id }})" class="lien">Modifier<span class="sr-only"> la ligne n° {{ $ligne->id }}</span></button>
                                            <button type="button" wire:click="demanderRetrait({{ $ligne->id }})" class="lien ml-3">Retirer<span class="sr-only"> la ligne n° {{ $ligne->id }}</span></button>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @else
        {{-- Historique : pour chaque version, les lignes créées et clôturées --}}
        <section aria-labelledby="titre-historique" class="space-y-3">
            <h2 id="titre-historique" class="text-lg font-semibold">Historique des versions</h2>
            @foreach ($historique as $entree)
                @php($creees = $seuils ? $entree['seuils_crees'] : $entree['plages_creees'])
                @php($cloturees = $seuils ? $entree['seuils_clotures'] : $entree['plages_cloturees'])
                <article class="carte" wire:key="version-{{ $entree['version']->numero }}">
                    <h3 class="font-semibold">Version {{ $entree['version']->numero }} — {{ $entree['version']->date_version->format('d/m/Y') }}{{ $entree['auteur'] ? ', '.$entree['auteur'] : '' }}</h3>
                    <p class="mt-1 text-sm">{{ $entree['version']->commentaire ?? '—' }}</p>
                    <p class="mt-1 text-sm text-texte-doux">
                        {{ $composant::TABLES[$table] }} : {{ count($creees) }} ligne(s) créée(s), {{ count($cloturees) }} ligne(s) clôturée(s).
                    </p>
                    @foreach (['Lignes créées' => $creees, 'Lignes clôturées' => $cloturees] as $titre => $liste)
                        @if ($liste !== [])
                            <details class="mt-2">
                                <summary class="cursor-pointer text-sm font-medium">{{ $titre }} ({{ count($liste) }})</summary>
                                <ul class="mt-1 list-disc pl-6 text-sm">
                                    @foreach ($liste as $l)
                                        <li>{{ $composant::resume($l, $seuils) }}</li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    @endforeach
                </article>
            @endforeach
        </section>
    @endif
</div>
