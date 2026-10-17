@php use App\Services\Dossier\Affichage; @endphp
<div class="space-y-6">
    <p class="text-texte-doux">{{ $libelleElevage }}@if ($libelleElevage !== $elevage->code_pseudo) ({{ $elevage->code_pseudo }})@endif</p>

    @if ($peutCreer)
        <section class="carte" aria-labelledby="titre-nouvel">
            <h2 id="titre-nouvel" class="text-lg font-semibold">Nouvel inventaire</h2>
            @error('inventaire') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
            <form wire:submit="creer" class="mt-3 grid gap-3 sm:grid-cols-2">
                <div>
                    <label for="inv-date" class="etiquette">Date de l'inventaire</label>
                    <input id="inv-date" type="date" wire:model="dateInventaire" class="champ w-full text-lg">
                </div>
                <div>
                    <label for="inv-type" class="etiquette">Type</label>
                    <select id="inv-type" wire:model="type" class="champ w-full text-lg">
                        @foreach ($types as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label for="inv-debut" class="etiquette">Période de référence : début</label>
                    <input id="inv-debut" type="date" wire:model="dateDebut" class="champ w-full text-lg">
                </div>
                <div>
                    <label for="inv-fin" class="etiquette">Fin (prochaine récolte de même nature)</label>
                    <input id="inv-fin" type="date" wire:model="dateFin" class="champ w-full text-lg">
                    @error('dateFin') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <label class="flex items-center gap-2 sm:col-span-2"><input type="checkbox" wire:model="anneeSeche" class="h-5 w-5"> Année sèche (marge de sécurité majorée)</label>
                <p class="text-xs text-texte-doux sm:col-span-2">Repères : plaine 160 jours minimum, montagne 200 ; transition de mise à l'herbe + 30 jours à demi-ration ; complémentation estivale 30 jours en année favorable, 90 à 100 en année sèche (FRGDS AURA / Idele 2019). Le choix vous appartient.</p>
                <div class="sm:col-span-2"><button type="submit" class="bouton-principal w-full sm:w-auto">Créer l'inventaire</button></div>
            </form>
        </section>
    @endif

    @if ($peutCreer)
        <details class="carte" @if ($apercu) open @endif>
            <summary class="cursor-pointer text-lg font-semibold">Reprendre un bilan du calculateur</summary>
            <p class="mt-2 text-sm text-texte-doux">Le calculateur d'autonomie fourragère garde sa saisie dans le navigateur (clé « gds-autonomie-fourragere-v1 »). Coller ce contenu ci-dessous : les silos et les balles deviennent les lots d'un nouvel inventaire, recalculés avec les règles de l'application.</p>
            <form wire:submit="analyserCalculateur" class="mt-3 space-y-2">
                <label for="contenu-calc" class="etiquette">Contenu du calculateur</label>
                <textarea id="contenu-calc" wire:model="contenuCalculateur" rows="4" class="champ w-full font-mono text-xs" placeholder='{"expl": "…", "silos": […], "balles": […]}'></textarea>
                @error('contenuCalculateur') <p class="text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
                <button type="submit" class="bouton-secondaire">Voir ce qui sera repris</button>
            </form>

            @if ($apercu)
                <div class="mt-4 space-y-3">
                    <p class="text-sm">{{ $apercu['inventaire']['libelle'] ?: 'Bilan sans nom' }} · bilan du {{ \Illuminate\Support\Carbon::parse($apercu['inventaire']['date'])->format('d/m/Y') }} sur {{ $apercu['inventaire']['jours'] }} jours · marge de sécurité {{ \App\Services\Dossier\Affichage::nombre(100 * $apercu['inventaire']['marge'], 0) }} %</p>
                    @foreach ($apercu['lots'] as $i => $lot)
                        <div class="rounded-md border border-filet p-3 text-sm" wire:key="apercu-{{ $i }}">
                            <p class="font-medium">{{ $lot['donnees']['libelle'] }}</p>
                            @if ($lot['donnees']['forme'] !== 'silo')
                                <div class="mt-2 grid gap-2 sm:grid-cols-2">
                                    <select wire:model="apercu.lots.{{ $i }}.donnees.famille_code" class="champ" aria-label="Famille de {{ $lot['donnees']['libelle'] }}">
                                        @foreach ($familles as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                                    </select>
                                    <select wire:model="apercu.lots.{{ $i }}.donnees.forme" class="champ" aria-label="Forme de {{ $lot['donnees']['libelle'] }}">
                                        <option value="balle_ronde">Balles rondes</option><option value="balle_carree">Balles carrées</option><option value="botte">Bottes</option>
                                    </select>
                                </div>
                            @else
                                <p class="text-texte-doux">Silo · {{ $familles[$lot['donnees']['famille_code']] ?? '' }} · {{ $lot['donnees']['ouvrage'] === 'taupiniere' ? 'taupinière' : 'couloir' }}</p>
                            @endif
                            @foreach ($lot['alertes'] as $alerte) <p class="mt-1 text-etat-vigilance">{{ $alerte }}</p> @endforeach
                        </div>
                    @endforeach
                    @if ($apercu['non_repris'] !== []) <p class="text-sm text-texte-doux">Non repris : {{ implode(' ; ', $apercu['non_repris']) }}.</p> @endif
                    <button type="button" wire:click="importerCalculateur" class="bouton-principal w-full sm:w-auto">Créer l'inventaire</button>
                </div>
            @endif
        </details>
    @endif

    <section aria-labelledby="titre-liste">
        <h2 id="titre-liste" class="text-lg font-semibold">Inventaires</h2>
        @forelse ($inventaires as $inv)
            <a href="{{ route('stocks.inventaire', [$elevage->id, $inv->id]) }}" class="carte mt-3 block hover:border-entite" wire:key="inv-{{ $inv->id }}">
                <span class="flex flex-wrap items-center justify-between gap-2">
                    <span class="font-medium">{{ $inv->date_inventaire->format('d/m/Y') }} — {{ $types[$inv->type] ?? $inv->type }}</span>
                    <x-pastille :etat="$inv->statut === 'courant' ? 'information' : 'couvert'" :libelle="$inv->statut === 'courant' ? 'En cours' : 'Clos'" />
                </span>
                <span class="mt-1 block text-sm text-texte-doux">Période du {{ $inv->date_debut->format('d/m/Y') }} au {{ $inv->date_fin->format('d/m/Y') }} · stock utile {{ Affichage::nombre((float) $inv->lots_sum_stock_utile_t_ms, 1) }} t MS</span>
            </a>
        @empty
            <p class="carte mt-3 text-texte-doux">Aucun inventaire.</p>
        @endforelse
    </section>
</div>
