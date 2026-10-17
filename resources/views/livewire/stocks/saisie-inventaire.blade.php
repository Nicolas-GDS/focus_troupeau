@php
    use App\Services\Dossier\Affichage;
    $origines = ['analyse' => 'MS analysée', 'saisie' => 'MS saisie', 'defaut' => 'MS par défaut'];
    $formes = ['silo' => 'Silo', 'balle_ronde' => 'Balles rondes', 'balle_carree' => 'Balles carrées', 'botte' => 'Bottes', 'vrac' => 'Vrac'];
    $pct = fn ($v) => Affichage::nombre(100 * (float) $v, 1).' %';
@endphp
<div class="space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <p class="text-texte-doux">{{ $libelleElevage }} · <a href="{{ route('stocks', $elevage->id) }}" class="lien">tous les inventaires</a></p>
        <x-pastille :etat="$inventaire->statut === 'courant' ? 'information' : 'couvert'" :libelle="$inventaire->statut === 'courant' ? 'En cours' : 'Clos'" />
    </div>
    <section class="carte text-sm" aria-label="Inventaire">
        <p>{{ $types[$inventaire->type] ?? $inventaire->type }} · période du {{ $inventaire->date_debut->format('d/m/Y') }} au {{ $inventaire->date_fin->format('d/m/Y') }} ({{ $inventaire->date_debut->diffInDays($inventaire->date_fin) + 1 }} jours)</p>
        <p class="text-texte-doux">Marge de sécurité {{ $pct($inventaire->marge_securite) }}@if ($inventaire->annee_seche) (année sèche)@endif — position du GDS, à discuter avec votre technicien.</p>
        @if ($peutSaisir) <button type="button" wire:click="clore" wire:confirm="Clore cet inventaire ? Il ne sera plus modifiable." class="lien mt-2 text-sm">Clore l'inventaire</button> @endif
    </section>

    {{-- Tableau de bord par famille (EF-M4-06) --}}
    <section aria-labelledby="titre-familles">
        <h2 id="titre-familles" class="text-lg font-semibold">Stock utile par famille</h2>
        @if ($tableau === [])
            <p class="carte mt-3 text-texte-doux">Aucun lot saisi.</p>
        @else
            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($tableau as $f)
                    <div class="carte" wire:key="famille-{{ $f['famille'] }}">
                        <p class="text-sm text-texte-doux">{{ $f['libelle'] }}</p>
                        <p class="text-2xl font-semibold">{{ Affichage::nombre($f['stock_utile_t_ms'], 1) }} <span class="text-base font-normal">t MS</span></p>
                        <p class="text-xs text-texte-doux">analysé à {{ Affichage::nombre(100 * $f['part_analysee'], 0) }} %@if ($f['mouvements_t_ms'] != 0.0) · mouvements {{ $f['mouvements_t_ms'] > 0 ? '+' : '−' }} {{ Affichage::nombre(abs($f['mouvements_t_ms']), 1) }} t MS @endif @if ($f['recomptages']) · {{ $f['recomptages'] }} re-comptage(s) @endif</p>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-texte-doux">Stock à l'inventaire, avant consommation. La projection mois par mois, la date de rupture et l'incertitude viendront avec le bilan d'autonomie.</p>
        @endif
    </section>

    {{-- Lots --}}
    <section aria-labelledby="titre-lots">
        <h2 id="titre-lots" class="text-lg font-semibold">Lots de stock</h2>
        @foreach ($lots as $l)
            <div class="carte mt-3" wire:key="lot-{{ $l->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <p class="font-medium">{{ $l->libelle }}</p>
                        <p class="text-sm text-texte-doux">{{ $formes[$l->forme] ?? $l->forme }} · {{ $familles[$l->famille_code] ?? $l->famille_code }}</p>
                    </div>
                    <p class="text-right"><span class="text-xl font-semibold">{{ Affichage::nombre((float) $l->stock_utile_t_ms, 2) }}</span> t MS utiles
                        <span class="block text-xs text-texte-doux">brut {{ Affichage::nombre((float) $l->stock_brut_t_ms, 2) }} t MS</span></p>
                </div>
                <p class="mt-2 flex flex-wrap gap-2 text-xs">
                    <span class="rounded bg-fond px-2 py-0.5">{{ $origines[$l->ms_origine] ?? 'MS' }} : {{ Affichage::nombre((float) $l->ms_pct, 1) }} %</span>
                    @if ($l->forme !== 'silo' && $l->forme !== 'vrac') <span class="rounded bg-fond px-2 py-0.5">{{ $l->poids_mesure ? 'poids pesé' : 'poids estimé' }} : {{ Affichage::nombre((float) $l->poids_brut_kg, 0) }} kg</span> @endif
                    @if ($l->forme === 'silo') <span class="rounded bg-fond px-2 py-0.5">{{ Affichage::nombre((float) $l->volume_calc_m3, 0) }} m³ · {{ Affichage::nombre((float) $l->densite_calc, 1) }} kg MS/m³ ({{ $l->methode_densite === 'equation' ? 'équation' : 'mesurée' }})</span> @endif
                    <span class="rounded bg-fond px-2 py-0.5">pertes {{ $pct($l->pertes_pct) }}@if ($l->pertes_defaut_pct !== null && (float) $l->pertes_defaut_pct !== (float) $l->pertes_pct) (référence {{ $pct($l->pertes_defaut_pct) }})@endif</span>
                    @if ($l->ms_origine !== 'analyse') <span class="text-etat-vigilance">Lot non analysé : l'analyser donnerait sa MS et ses valeurs.</span> @endif
                </p>

                @if ($l->mouvements->isNotEmpty())
                    <ul class="mt-2 space-y-1 border-l-2 border-filet pl-3 text-sm">
                        @foreach ($l->mouvements as $m)
                            <li wire:key="mvt-{{ $m->id }}">{{ $m->date_mouvement->format('d/m/Y') }} · {{ $typesMouvement[$m->type] }} :
                                {{ $m->type === 'recomptage' ? 'stock recompté à ' : ((float) $m->quantite_t_ms >= 0 ? '+ ' : '− ') }}{{ Affichage::nombre(abs((float) $m->quantite_t_ms), 2) }} t MS
                                @if ($m->prix_eur) · {{ Affichage::nombre((float) $m->prix_eur, 0) }} € @endif @if ($m->motif) · {{ $m->motif }} @endif
                                @can('delete', $m) <button type="button" wire:click="supprimerMouvement({{ $m->id }})" class="lien text-xs">retirer</button> @endcan
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($peutModifier($l))
                    <details class="mt-2">
                        <summary class="lien cursor-pointer text-sm">Ajouter un mouvement</summary>
                        <form wire:submit="ajouterMouvement({{ $l->id }})" class="mt-2 grid gap-2 sm:grid-cols-5">
                            <select wire:model="mouvements.{{ $l->id }}.type" class="champ" aria-label="Type de mouvement">
                                <option value="">Type…</option>
                                @foreach ($typesMouvement as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                            </select>
                            <input type="date" wire:model="mouvements.{{ $l->id }}.date" class="champ" aria-label="Date">
                            <input type="number" step="any" inputmode="decimal" wire:model="mouvements.{{ $l->id }}.quantite" class="champ" placeholder="t MS" aria-label="Quantité en t MS">
                            <input type="number" step="any" inputmode="decimal" wire:model="mouvements.{{ $l->id }}.prix" class="champ" placeholder="prix € (facultatif)" aria-label="Prix">
                            <input type="text" wire:model="mouvements.{{ $l->id }}.motif" class="champ" placeholder="motif" maxlength="300" aria-label="Motif">
                            @error("mouvements.{$l->id}.*") <p class="text-sm text-etat-critique sm:col-span-5">{{ $message }}</p> @enderror
                            <button type="submit" class="bouton-secondaire sm:col-span-5 sm:w-auto">Enregistrer le mouvement</button>
                        </form>
                    </details>
                    <p class="mt-2 flex gap-4 text-sm">
                        <button type="button" wire:click="modifierLot({{ $l->id }})" class="lien">Modifier</button>
                        <button type="button" wire:click="supprimerLot({{ $l->id }})" wire:confirm="Supprimer ce lot ?" class="lien">Supprimer</button>
                    </p>
                @endif
            </div>
        @endforeach
    </section>

    {{-- Saisie d'un lot, une ligne à la fois (EF-GEN-07) --}}
    @if ($peutSaisir)
        <section class="carte" aria-labelledby="titre-saisie">
            <h2 id="titre-saisie" class="text-lg font-semibold">{{ $lotId ? 'Modifier le lot' : 'Ajouter un lot' }}</h2>
            @if (! $lotId)
                <div class="mt-3 flex flex-wrap gap-2" role="group" aria-label="Forme du lot">
                    @foreach ($formes as $code => $libelle)
                        <button type="button" wire:click="nouveauLot('{{ $code }}')" class="{{ $lot['forme'] === $code ? 'bouton-principal' : 'bouton-secondaire' }}" aria-pressed="{{ $lot['forme'] === $code ? 'true' : 'false' }}">{{ $libelle }}</button>
                    @endforeach
                </div>
            @endif
            @error('lot') <p class="mt-3 rounded-md border border-etat-critique bg-etat-critique-fond p-3 text-sm" role="alert">{{ $message }}</p> @enderror

            <form wire:submit="enregistrerLot" class="mt-4 space-y-3">
                <div>
                    <label for="lot-libelle" class="etiquette">Désignation</label>
                    <input id="lot-libelle" type="text" wire:model="lot.libelle" class="champ w-full text-lg" placeholder="Foin 1re coupe prairie du bas" maxlength="200">
                    @error('lot.libelle') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="lot-type" class="etiquette">Type de fourrage</label>
                        <select id="lot-type" wire:model.live="lot.type_fourrage_code" class="champ w-full text-lg">
                            <option value="">Non précisé</option>
                            @foreach ($typesFourrage as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                        </select>
                    </div>
                    @if (! $lot['type_fourrage_code'])
                        <div>
                            <label for="lot-famille" class="etiquette">Famille</label>
                            <select id="lot-famille" wire:model.live="lot.famille_code" class="champ w-full text-lg">
                                @foreach ($familles as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                            </select>
                        </div>
                    @endif
                </div>
                <div>
                    <label for="lot-prel" class="etiquette">Lot analysé (facultatif)</label>
                    <select id="lot-prel" wire:model.live="lot.prelevement_id" class="champ w-full text-lg">
                        <option value="">Pas d'analyse reliée</option>
                        @foreach ($prelevements as $p) <option value="{{ $p['prelevement_id'] }}">{{ $p['libelle'] }}@if ($p['ms']) — MS {{ Affichage::nombre($p['ms'], 1) }} %@endif</option> @endforeach
                    </select>
                    <p class="mt-1 text-xs text-texte-doux">Relié à une analyse, le lot prend la MS du bulletin.</p>
                </div>

                @if ($lot['forme'] === 'silo')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="lot-ouvrage" class="etiquette">Ouvrage</label>
                            <select id="lot-ouvrage" wire:model.live="lot.ouvrage" class="champ w-full text-lg"><option value="couloir">Silo couloir</option><option value="taupiniere">Taupinière</option></select>
                        </div>
                        <div>
                            <label for="lot-fermeture" class="etiquette">Date de fermeture (tassement)</label>
                            <input id="lot-fermeture" type="date" wire:model.live.debounce.500ms="lot.date_fermeture" class="champ w-full text-lg">
                        </div>
                        @foreach (['l1_m' => 'L1 : longueur à hauteur pleine (m)', 'l2_m' => 'L2 : longueur de la pente (m)', 'largeur_m' => 'Largeur à mi-hauteur (m)', 'hauteur_m' => 'Hauteur moyenne (m)'] as $champ => $libelle)
                            <div>
                                <label for="lot-{{ $champ }}" class="etiquette">{{ $libelle }}</label>
                                <input id="lot-{{ $champ }}" type="number" step="any" min="0" inputmode="decimal" wire:model.live.debounce.500ms="lot.{{ $champ }}" class="champ w-full text-lg">
                                @error('lot.'.$champ) <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                            </div>
                        @endforeach
                        <div>
                            <label for="lot-nbh" class="etiquette">Nombre de hauteurs mesurées</label>
                            <input id="lot-nbh" type="number" min="1" inputmode="numeric" wire:model="lot.nb_mesures_hauteur" class="champ w-full text-lg">
                            <p class="mt-1 text-xs text-texte-doux">Au moins trois (cinq en taupinière).</p>
                        </div>
                        <div>
                            <label for="lot-methode" class="etiquette">Densité</label>
                            <select id="lot-methode" wire:model.live="lot.methode_densite" class="champ w-full text-lg">
                                <option value="equation">Par équation</option><option value="carottage">Carottage</option><option value="tamisage">Tamisage</option><option value="saisie">Valeur saisie</option>
                            </select>
                        </div>
                        @if ($lot['methode_densite'] === 'equation')
                            <div class="sm:col-span-2">
                                <label for="lot-eq" class="etiquette">Équation</label>
                                <select id="lot-eq" wire:model.live="lot.equation_code" class="champ w-full text-lg">
                                    @foreach ($equations as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                                </select>
                            </div>
                            @if (str_starts_with($lot['equation_code'], 'mais') || str_starts_with($lot['equation_code'], 'sorgho'))
                                <div>
                                    <label for="lot-amidon" class="etiquette">Amidon (% MS, facultatif)</label>
                                    <input id="lot-amidon" type="number" step="any" inputmode="decimal" wire:model.live.debounce.500ms="lot.amidon_pct" class="champ w-full text-lg">
                                </div>
                                <div>
                                    <label for="lot-grain" class="etiquette">ou grain (%)</label>
                                    <input id="lot-grain" type="number" step="any" inputmode="decimal" wire:model.live.debounce.500ms="lot.grain_pct" class="champ w-full text-lg">
                                </div>
                            @endif
                        @else
                            <div class="sm:col-span-2">
                                <label for="lot-densite" class="etiquette">Densité mesurée (kg MS/m³)</label>
                                <input id="lot-densite" type="number" step="any" inputmode="decimal" wire:model.live.debounce.500ms="lot.densite_kg_ms_m3" class="champ w-full text-lg">
                            </div>
                        @endif
                    </div>
                    <p class="text-xs text-texte-doux">Les croquis de cubage du document 05 seront ajoutés ici dès qu'ils auront été versés au dépôt.</p>
                @elseif ($lot['forme'] === 'vrac')
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="lot-vol" class="etiquette">Volume (m³)</label>
                            <input id="lot-vol" type="number" step="any" min="0" inputmode="decimal" wire:model.live.debounce.500ms="lot.volume_m3" class="champ w-full text-lg">
                            @error('lot.volume_m3') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="lot-dens" class="etiquette">Densité (kg brut/m³, défaut foin)</label>
                            <input id="lot-dens" type="number" step="any" min="0" inputmode="decimal" wire:model.live.debounce.500ms="lot.poids_brut_kg" class="champ w-full text-lg">
                        </div>
                    </div>
                @else
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="lot-nombre" class="etiquette">Nombre</label>
                            <input id="lot-nombre" type="number" step="1" min="0" inputmode="numeric" wire:model.live.debounce.500ms="lot.nombre" class="champ w-full text-lg">
                            @error('lot.nombre') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="lot-poids" class="etiquette">Poids brut unitaire (kg)</label>
                            <input id="lot-poids" type="number" step="any" min="0" inputmode="decimal" wire:model.live.debounce.500ms="lot.poids_brut_kg" class="champ w-full text-lg">
                            <label class="mt-1 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="lot.poids_mesure" class="h-5 w-5"> pesé (sinon estimé)</label>
                        </div>
                    </div>
                @endif

                <div class="grid gap-3 sm:grid-cols-2">
                    <div>
                        <label for="lot-ms" class="etiquette">Matière sèche (%)</label>
                        <input id="lot-ms" type="number" step="any" min="0" max="100" inputmode="decimal" wire:model.live.debounce.500ms="lot.ms_pct" class="champ w-full text-lg" @if ($lot['prelevement_id']) disabled @endif placeholder="défaut : foin 85, paille 90">
                        @error('lot.ms_pct') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="lot-stock" class="etiquette">{{ $lot['forme'] === 'silo' ? 'Conduite du silo' : 'Mode de stockage' }}</label>
                        <select id="lot-stock" wire:model.live="lot.mode_stockage" class="champ w-full text-lg">
                            @foreach ($pertes as $cle => $p) <option value="{{ $cle }}">{{ $p['libelle'] }} ({{ $pct($p['defaut']) }})</option> @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="lot-pertes" class="etiquette">Pertes retenues (%, facultatif)</label>
                        <input id="lot-pertes" type="number" step="any" min="0" max="99" inputmode="decimal" wire:model.live.debounce.500ms="lot.pertes_pct" class="champ w-full text-lg" placeholder="valeur de référence">
                    </div>
                </div>

                {{-- Calcul en ligne (EF-M4-02) --}}
                <div class="rounded-md border border-filet bg-fond p-3 text-sm" aria-live="polite">
                    @if (is_array($apercu))
                        <p><span class="text-2xl font-semibold">{{ Affichage::nombre($apercu['stock_utile_t_ms'], 2) }}</span> t MS utiles
                            <span class="text-texte-doux">(brut {{ Affichage::nombre($apercu['stock_brut_t_ms'], 2) }} t MS ; pertes {{ $pct($apercu['pertes_pct']) }})</span></p>
                        <p class="text-xs text-texte-doux">{{ $origines[$apercu['ms_origine']] }} : {{ Affichage::nombre($apercu['ms_pct'], 1) }} %@if ($apercu['volume_calc_m3'] !== null) · volume {{ Affichage::nombre($apercu['volume_calc_m3'], 1) }} m³ · densité {{ Affichage::nombre($apercu['densite_calc'], 1) }} kg MS/m³@endif</p>
                    @elseif (is_string($apercu))
                        <p class="text-etat-vigilance">{{ $apercu }}</p>
                    @else
                        <p class="text-texte-doux">Le stock utile s'affichera dès que les mesures seront saisies.</p>
                    @endif
                </div>

                <div class="flex flex-wrap gap-3">
                    <button type="submit" class="bouton-principal w-full sm:w-auto">{{ $lotId ? 'Enregistrer les modifications' : 'Ajouter le lot' }}</button>
                    @if ($lotId) <button type="button" wire:click="nouveauLot" class="bouton-secondaire">Annuler</button> @endif
                </div>
            </form>
        </section>
    @endif
</div>
