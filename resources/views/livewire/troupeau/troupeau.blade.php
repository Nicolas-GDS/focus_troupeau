@php
    use App\Services\Dossier\Affichage;
    $t = fn ($v) => Affichage::nombre((float) $v, 1);
    $pct = fn ($v) => Affichage::nombre(100 * (float) $v, 1).' %';
    $sources = ['lot_stock' => 'Lot de stock', 'famille_stock' => 'Famille de stock', 'etiquette' => 'Aliment ou minéral (étiquette)', 'paturage' => 'Herbe pâturée'];
@endphp
<div class="space-y-6">
    <p class="text-texte-doux">{{ $libelleElevage }}@if ($libelleElevage !== $elevage->code_pseudo) ({{ $elevage->code_pseudo }})@endif · <a href="{{ route('stocks', $elevage->id) }}" class="lien">stocks</a></p>
    @error('troupeau') <p class="rounded-md border border-etat-critique bg-etat-critique-fond p-3 text-sm" role="alert">{{ $message }}</p> @enderror

    @if (! $inventaireCourant)
        <p class="carte text-texte-doux">Créer d'abord un inventaire des stocks : les lots d'animaux et leurs rations se décrivent pour sa période de référence. <a href="{{ route('stocks', $elevage->id) }}" class="lien">Inventaires</a></p>
    @else
        <section class="carte text-sm" aria-label="Période">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <p>Inventaire du {{ $inventaireCourant->date_inventaire->format('d/m/Y') }} · période du {{ $inventaireCourant->date_debut->format('d/m/Y') }} au {{ $inventaireCourant->date_fin->format('d/m/Y') }}</p>
                @if ($inventaires->count() > 1)
                    <div>
                        <label for="choix-inv" class="etiquette">Inventaire</label>
                        <select id="choix-inv" wire:model.live="inventaire" class="champ">
                            @foreach ($inventaires as $inv) <option value="{{ $inv->id }}" @selected($inv->id === $inventaireCourant->id)>{{ $inv->date_inventaire->format('d/m/Y') }}{{ $inv->statut === 'courant' ? ' (en cours)' : '' }}</option> @endforeach
                        </select>
                    </div>
                @endif
            </div>
            @if ($peutSaisir && $inventaires->where('id', '!=', $inventaireCourant->id)->isNotEmpty())
                <form wire:submit="copier" class="mt-3 flex flex-wrap items-end gap-2">
                    <div>
                        <label for="copie-source" class="etiquette">Repartir des lots et rations de l'inventaire du</label>
                        <select id="copie-source" wire:model="inventaireSource" class="champ">
                            <option value="">Choisir…</option>
                            @foreach ($inventaires->where('id', '!=', $inventaireCourant->id) as $inv) <option value="{{ $inv->id }}">{{ $inv->date_inventaire->format('d/m/Y') }}</option> @endforeach
                        </select>
                    </div>
                    <button type="submit" class="bouton-secondaire">Copier</button>
                </form>
            @endif
        </section>

        {{-- Besoins par les deux voies (RC-8, EF-M5-04) --}}
        <section class="carte" aria-labelledby="titre-besoins">
            <h2 id="titre-besoins" class="text-lg font-semibold">Besoins en fourrages sur la période</h2>
            <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                <div><dt class="text-sm text-texte-doux">Voie ration</dt><dd class="text-2xl font-semibold">{{ $t($besoins['ration_t_ms']) }} <span class="text-base font-normal">t MS</span></dd></div>
                <div><dt class="text-sm text-texte-doux">Prélevé sur les stocks (pertes à la distribution comprises)</dt><dd class="text-2xl font-semibold">{{ $t($besoins['stock_t_ms']) }} <span class="text-base font-normal">t MS</span></dd></div>
                <div><dt class="text-sm text-texte-doux">Voie UGB (cadrage)</dt><dd class="text-2xl font-semibold">{{ $t($besoins['ugb_t_ms']) }} <span class="text-base font-normal">t MS</span></dd></div>
            </dl>
            @if ($besoins['ecart'] !== null && $besoins['ration_t_ms'] > 0)
                <p class="mt-2 text-sm">
                    Écart entre les deux voies : {{ $pct($besoins['ecart']) }}
                    @if ($besoins['ecart'] > $besoins['ecart_max']) <x-pastille etat="vigilance" libelle="Vérifier les quantités ingérées saisies" /> @endif
                </p>
            @endif
        </section>

        {{-- Lots d'animaux --}}
        @foreach ($besoins['lots'] as $ligne)
            @php($lot = $ligne['lot'])
            <section class="carte" aria-labelledby="lot-{{ $lot->id }}" wire:key="lot-{{ $lot->id }}">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h2 id="lot-{{ $lot->id }}" class="text-lg font-semibold">{{ $lot->libelle }}</h2>
                        <p class="text-sm text-texte-doux">{{ $categories->firstWhere('code', $lot->categorie_code)?->libelle ?? $lot->categorie_code }}
                            · {{ $lot->coeff_ugb === null ? 'coefficient UGB à saisir' : 'UGB '.Affichage::nombre((float) $lot->coeff_ugb, 2) }}
                            @if ($lot->poids_vif_kg) · {{ Affichage::nombre((float) $lot->poids_vif_kg, 0) }} kg @endif
                            @if ($lot->production_valeur) · {{ Affichage::nombre((float) $lot->production_valeur, 1) }} {{ $lot->production_unite === 'kg_lait_j' ? 'kg de lait/j' : 'g de GMQ/j' }} @endif</p>
                    </div>
                    <p class="text-right text-sm">{{ $t($ligne['ration_t_ms']) }} t MS (ration)<span class="block text-texte-doux">{{ $t($ligne['ugb_t_ms']) }} t MS (UGB)</span></p>
                </div>

                @foreach ($ligne['phases'] as $p)
                    @php($phase = $p['phase'])
                    @php($ration = $phase->ration)
                    <div class="mt-4 rounded-md border border-filet p-3" wire:key="phase-{{ $phase->id }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-medium">{{ $stades[$phase->stade] ?? $phase->stade }} · {{ $phase->effectif }} animaux · du {{ $phase->date_debut->format('d/m/Y') }} au {{ $phase->date_fin->format('d/m/Y') }}</p>
                            <p class="text-sm text-texte-doux">{{ $p['jours'] }} jours dans la période · {{ Affichage::nombre($p['kg_ms_fourrage'], 2) }} kg MS de fourrage/animal/j</p>
                        </div>
                        @if ($ration)
                            <ul class="mt-2 space-y-1 text-sm">
                                @foreach ($ration->composants as $c)
                                    <li wire:key="composant-{{ $c->id }}" class="flex flex-wrap items-center justify-between gap-2">
                                        <span>{{ match ($c->source) {
                                            'lot_stock' => $c->lotStock?->libelle ?? 'Lot de stock',
                                            'famille_stock' => ($familles[$c->famille_code] ?? $c->famille_code).' (famille)',
                                            'etiquette' => '« '.($c->etiquette?->libelle_eleveur ?? '').' »',
                                            default => 'Herbe pâturée',
                                        } }}</span>
                                        <span>{{ Affichage::nombre((float) $c->kg_ms, 2) }} kg MS/j @if ($c->quantite_brute_kg) <span class="text-texte-doux">({{ Affichage::nombre((float) $c->quantite_brute_kg, 2) }} kg brut)</span>@endif
                                            @can('delete', $c) <button type="button" wire:click="supprimerComposant({{ $c->id }})" class="lien text-xs">retirer</button> @endcan</span>
                                    </li>
                                @endforeach
                                @if ($ration->composants->isEmpty()) <li class="text-texte-doux">Ration à décrire.</li> @endif
                            </ul>
                            <p class="mt-2 text-xs text-texte-doux">Distribution : {{ $distributionsProposees[$ration->mode_distribution] ?? 'non précisée' }} · pertes {{ $pct($ration->pertes_distribution_pct) }}</p>
                            @if ($ration->composants->isNotEmpty()) @include('livewire.troupeau.partiels.couverture', ['analyse' => $analyses[$phase->id]]) @endif

                            @if ($peutSaisir)
                                <details class="mt-2">
                                    <summary class="lien cursor-pointer text-sm">Ajouter un composant</summary>
                                    <form wire:submit="ajouterComposant({{ $ration->id }})" class="mt-2 space-y-2">
                                        <select wire:model.live="composants.{{ $ration->id }}.source" class="champ w-full text-lg" aria-label="Source">
                                            <option value="">Source…</option>
                                            @foreach ($sources as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                                        </select>
                                        @php($source = $composants[$ration->id]['source'] ?? '')
                                        @if ($source === 'lot_stock')
                                            <select wire:model="composants.{{ $ration->id }}.reference" class="champ w-full text-lg" aria-label="Lot de stock">
                                                <option value="">Lot…</option>
                                                @foreach ($lotsStock as $ls) <option value="{{ $ls->id }}">{{ $ls->libelle }}</option> @endforeach
                                            </select>
                                        @elseif ($source === 'famille_stock')
                                            <select wire:model="composants.{{ $ration->id }}.reference" class="champ w-full text-lg" aria-label="Famille">
                                                <option value="">Famille…</option>
                                                @foreach ($familles as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                                            </select>
                                        @elseif ($source === 'etiquette')
                                            <select wire:model="composants.{{ $ration->id }}.reference" class="champ w-full text-lg" aria-label="Étiquette">
                                                <option value="">Étiquette…</option>
                                                @foreach ($etiquettes as $e) <option value="{{ $e->id }}">« {{ $e->libelle_eleveur }} »{{ $e->ms_pct === null ? ' — MS à renseigner' : '' }}</option> @endforeach
                                            </select>
                                        @endif
                                        <div class="grid gap-2 sm:grid-cols-2">
                                            @if ($source !== 'paturage')
                                                <input type="number" step="any" min="0" inputmode="decimal" wire:model="composants.{{ $ration->id }}.brut" class="champ w-full text-lg" placeholder="kg brut/animal/j" aria-label="Quantité brute">
                                            @endif
                                            <input type="number" step="any" min="0" inputmode="decimal" wire:model="composants.{{ $ration->id }}.kg_ms" class="champ w-full text-lg" placeholder="ou kg MS/animal/j" aria-label="Quantité en kg MS">
                                        </div>
                                        @error("composants.{$ration->id}.*") <p class="text-sm text-etat-critique">{{ $message }}</p> @enderror
                                        <button type="submit" class="bouton-secondaire w-full sm:w-auto">Ajouter</button>
                                    </form>
                                </details>
                                <details class="mt-1">
                                    <summary class="lien cursor-pointer text-sm">Mode de distribution</summary>
                                    <form wire:submit="enregistrerDistribution({{ $ration->id }})" class="mt-2 grid gap-2 sm:grid-cols-3">
                                        <select wire:model="distributions.{{ $ration->id }}.mode" class="champ sm:col-span-2" aria-label="Mode de distribution">
                                            @foreach ($distributionsProposees as $cle => $libelle) <option value="{{ $cle }}" @selected($cle === $ration->mode_distribution)>{{ $libelle }}</option> @endforeach
                                        </select>
                                        <input type="number" step="any" min="0" max="99" inputmode="decimal" wire:model="distributions.{{ $ration->id }}.pertes" class="champ" placeholder="pertes % (référence)" aria-label="Pertes retenues">
                                        <button type="submit" class="bouton-secondaire sm:col-span-3 sm:w-auto">Enregistrer</button>
                                    </form>
                                </details>
                            @endif
                        @endif
                        @can('delete', $phase) <button type="button" wire:click="supprimerPhase({{ $phase->id }})" wire:confirm="Supprimer cette phase et sa ration ?" class="lien mt-2 text-xs">Supprimer la phase</button> @endcan
                    </div>
                @endforeach

                @if ($peutSaisir)
                    <details class="mt-3">
                        <summary class="lien cursor-pointer text-sm">Ajouter une phase</summary>
                        <form wire:submit="ajouterPhase({{ $lot->id }})" class="mt-2 grid gap-2 sm:grid-cols-4">
                            <select wire:model="phases.{{ $lot->id }}.stade" class="champ text-lg" aria-label="Stade">
                                <option value="">Stade…</option>
                                @foreach ($stades as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                            </select>
                            <input type="date" wire:model="phases.{{ $lot->id }}.debut" class="champ text-lg" aria-label="Début">
                            <input type="date" wire:model="phases.{{ $lot->id }}.fin" class="champ text-lg" aria-label="Fin">
                            <input type="number" min="0" inputmode="numeric" wire:model="phases.{{ $lot->id }}.effectif" class="champ text-lg" placeholder="effectif" aria-label="Effectif">
                            @error("phases.{$lot->id}.*") <p class="text-sm text-etat-critique sm:col-span-4">{{ $message }}</p> @enderror
                            <button type="submit" class="bouton-secondaire sm:col-span-4 sm:w-auto">Ajouter la phase</button>
                        </form>
                    </details>
                    <button type="button" wire:click="archiverLot({{ $lot->id }})" wire:confirm="Archiver ce lot ?" class="lien mt-2 text-xs">Archiver le lot</button>
                @endif
            </section>
        @endforeach

        @if ($besoins['lots'] === []) <p class="carte text-texte-doux">Aucun lot d'animaux pour cet inventaire.</p> @endif

        {{-- Affectation fourrage → lot d'animaux (EF-M5-05) --}}
        @if ($affectation !== [])
            <section class="carte" aria-labelledby="titre-affectation">
                <h2 id="titre-affectation" class="text-lg font-semibold">Qui mange quoi</h2>
                <ul class="mt-2 space-y-3 text-sm">
                    @foreach ($affectation as $a)
                        <li wire:key="affectation-{{ $a['lot']->id }}">
                            <p class="font-medium">{{ $a['lot']->libelle }}</p>
                            @if ($a['conseillee']) <p class="text-texte-doux">Utilisation conseillée : {{ $a['conseillee'] }}</p> @endif
                            <ul class="mt-1 space-y-1 pl-3">
                                @foreach ($a['consommateurs'] as $c)
                                    <li>{{ $c['lot']->libelle }} ({{ $stades[$c['phase']->stade] ?? $c['phase']->stade }}, du {{ $c['phase']->date_debut->format('d/m/Y') }} au {{ $c['phase']->date_fin->format('d/m/Y') }}) : {{ Affichage::nombre($c['t_ms_jour'], 3) }} t MS/j
                                        @if ($c['deconseille']) <x-pastille etat="vigilance" libelle="Lot déconseillé pour cette catégorie" /> @endif</li>
                                @endforeach
                            </ul>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @if ($peutSaisir)
            <section class="carte" aria-labelledby="titre-nouveau-lot">
                <h2 id="titre-nouveau-lot" class="text-lg font-semibold">Nouveau lot d'animaux</h2>
                <form wire:submit="creerLot" class="mt-3 space-y-3">
                    <div>
                        <label for="nl-libelle" class="etiquette">Libellé</label>
                        <input id="nl-libelle" type="text" wire:model="nouveauLot.libelle" class="champ w-full text-lg" placeholder="Vaches allaitantes" maxlength="200">
                        @error('nouveauLot.libelle') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div>
                            <label for="nl-cat" class="etiquette">Catégorie</label>
                            <select id="nl-cat" wire:model.live="nouveauLot.categorie_code" class="champ w-full text-lg">
                                @foreach ($categories as $c) <option value="{{ $c->code }}">{{ $c->libelle }}</option> @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="nl-ugb" class="etiquette">Coefficient UGB</label>
                            @php($defaut = $categories->firstWhere('code', $nouveauLot['categorie_code'])?->coefficient)
                            <input id="nl-ugb" type="number" step="any" min="0" inputmode="decimal" wire:model="nouveauLot.coeff_ugb" class="champ w-full text-lg" placeholder="{{ $defaut === null ? 'à saisir' : 'défaut '.Affichage::nombre((float) $defaut, 2) }}">
                            <p class="mt-1 text-xs text-texte-doux">Repères Idele 2020 : {{ $coefficients->map(fn ($c) => $c->libelle.' '.Affichage::nombre((float) $c->coefficient, 2))->implode(' ; ') }}.</p>
                        </div>
                        <div>
                            <label for="nl-poids" class="etiquette">Poids vif moyen (kg)</label>
                            <input id="nl-poids" type="number" step="any" min="0" inputmode="decimal" wire:model="nouveauLot.poids_vif_kg" class="champ w-full text-lg">
                        </div>
                        <div>
                            <label for="nl-prod" class="etiquette">Production</label>
                            <div class="flex gap-2">
                                <input id="nl-prod" type="number" step="any" min="0" inputmode="decimal" wire:model="nouveauLot.production_valeur" class="champ w-full text-lg">
                                <select wire:model="nouveauLot.production_unite" class="champ" aria-label="Unité de production">
                                    <option value="">—</option><option value="kg_lait_j">kg de lait/j</option><option value="gmq_g_j">g de GMQ/j</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="bouton-principal w-full sm:w-auto">Créer le lot</button>
                </form>
            </section>
        @endif
    @endif
</div>
