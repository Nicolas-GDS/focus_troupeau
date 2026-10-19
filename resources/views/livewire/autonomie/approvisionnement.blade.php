@php
    use App\Services\Autonomie\ApprovisionnementService as Appro;
    use App\Services\Dossier\Affichage;
    $t = fn ($v) => Affichage::nombre((float) $v, 1);
    $eur = fn ($v) => number_format(round((float) $v), 0, ',', ' ').' €';
    $pct = fn ($v) => Affichage::nombre(round(100 * (float) $v), 0).' %';
    $statuts = ['brouillon' => 'Brouillon', 'retenu' => 'Retenu', 'archive' => 'Archivé'];
@endphp
<div class="space-y-6">
    <p class="text-texte-doux">{{ $libelleElevage }}@if ($libelleElevage !== $elevage->code_pseudo) ({{ $elevage->code_pseudo }})@endif · <a href="{{ route('autonomie', $elevage->id) }}" class="lien">bilan d'autonomie</a></p>
    @if (session('status')) <p class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</p> @endif
    @error('scenario') <p class="rounded-md border border-etat-critique bg-etat-critique-fond p-3 text-sm" role="alert">{{ $message }}</p> @enderror

    @if (! $inventaireCourant)
        <p class="carte text-texte-doux">Les scénarios se construisent sur l'inventaire en cours. <a href="{{ route('stocks', $elevage->id) }}" class="lien">Créer un inventaire</a></p>
    @else
        {{-- Proposition initiale (EF-M7-01, RC-13.2) --}}
        <section class="carte" aria-labelledby="titre-proposition">
            <h2 id="titre-proposition" class="text-lg font-semibold">Proposition initiale</h2>
            @if ($proposition['deficits'] === [] && $proposition['residuels'] === [])
                <p class="mt-2 text-sm">Aucun déficit sur la période, sécurité comprise : pas de proposition.</p>
            @else
                <dl class="mt-2 grid gap-2 text-sm sm:grid-cols-2">
                    <div><dt class="text-texte-doux">Familles en déficit (sécurité comprise)</dt>
                        <dd>@forelse ($proposition['deficits'] as $f => $q) <span class="block">{{ $familles[$f] ?? $f }} : {{ $t($q) }} t MS</span> @empty aucune @endforelse</dd></div>
                    <div><dt class="text-texte-doux">Familles en excédent</dt>
                        <dd>@forelse ($proposition['excedents'] as $f => $q) <span class="block">{{ $familles[$f] ?? $f }} : {{ $t($q) }} t MS</span> @empty aucune @endforelse</dd></div>
                </dl>
                @if ($proposition['lignes'] !== [])
                    <ol class="mt-3 space-y-2 text-sm">
                        @foreach ($proposition['lignes'] as $l)
                            <li class="border-t border-filet pt-2">
                                <strong>{{ Appro::LIBELLES[$l['levier']] }}</strong> :
                                @if ($l['levier'] === 'reaffectation') {{ $familles[$l['source']] ?? $l['source'] }} → @endif{{ $familles[$l['famille']] ?? $l['famille'] }}, {{ $t($l['quantite']) }} t MS
                                @if ($l['prix'] !== null) · {{ $eur($l['prix']) }}/t MS, soit {{ $eur($l['quantite'] * $l['prix']) }} <span class="text-xs text-texte-doux">(prix indicatif à valider — RC-13.3, hypothèse locale [CP])</span>
                                @elseif ($l['levier'] === 'achat_fourrage') · <span class="text-xs">prix à saisir</span> @endif
                                <span class="block text-xs text-texte-doux">{{ $l['consequence'] }}</span>
                            </li>
                        @endforeach
                    </ol>
                @endif
                @if ($proposition['residuels'] !== [])
                    <h3 class="mt-4 font-semibold">Besoins résiduels des lots d'animaux</h3>
                    <p class="text-xs text-texte-doux">Étapes 3 et 4 de RC-13.2 : besoin exprimé par animal et par jour, sans quantité de concentré ni de minéral (tables d'aliments non chargées).</p>
                    <ul class="mt-1 space-y-1 text-sm">
                        @foreach ($proposition['residuels'] as $r)
                            <li>{{ app(Appro::class)->texteResiduel($r) }}</li>
                        @endforeach
                    </ul>
                @endif
            @endif
            @if ($peutSaisir)
                <form wire:submit="creer" class="mt-4 flex flex-wrap items-end gap-2">
                    <div><label for="scenario-libelle" class="etiquette">Nom du scénario</label><input id="scenario-libelle" type="text" wire:model="libelle" class="champ" placeholder="Proposition initiale"></div>
                    <button type="submit" class="bouton-primaire">Créer un scénario depuis la proposition</button>
                    <button type="button" wire:click="creer(false)" class="bouton-secondaire">Créer un scénario vide</button>
                </form>
            @endif
        </section>

        {{-- Scénarios (EF-M7-02, EF-M7-05) --}}
        <section class="carte" aria-labelledby="titre-scenarios">
            <h2 id="titre-scenarios" class="text-lg font-semibold">Scénarios</h2>
            @if ($scenarios->isEmpty())
                <p class="mt-2 text-sm text-texte-doux">Aucun scénario.</p>
            @else
                <p class="mt-1 text-xs text-texte-doux">Cocher jusqu'à quatre scénarios pour les comparer.</p>
                @error('compares') <p class="mt-1 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
                <ul class="mt-2 space-y-2 text-sm">
                    @foreach ($scenarios as $s)
                        <li wire:key="scenario-{{ $s->id }}" class="flex flex-wrap items-center gap-2 border-t border-filet pt-2">
                            <input type="checkbox" id="comparer-{{ $s->id }}" value="{{ $s->id }}" wire:model.live="compares">
                            <label for="comparer-{{ $s->id }}"><a href="{{ route('scenarios.voir', [$elevage->id, $s->id]) }}" class="lien">{{ $s->libelle }}</a></label>
                            @if ($s->statut === 'retenu') <x-pastille etat="couvert" :libelle="'Retenu le '.$s->retenu_le?->format('d/m/Y')" /> @else <span class="text-xs text-texte-doux">{{ $statuts[$s->statut] ?? $s->statut }}</span> @endif
                            @if ($peutSaisir)
                                @if ($s->statut !== 'retenu') <button type="button" wire:click="retenir({{ $s->id }})" wire:confirm="Retenir ce scénario ? Les achats d'un scénario retenu auparavant seront retirés." class="lien text-xs">retenir</button> @endif
                                <button type="button" wire:click="dupliquer({{ $s->id }})" class="lien text-xs">dupliquer</button>
                                @if ($s->statut !== 'retenu') <button type="button" wire:click="supprimer({{ $s->id }})" wire:confirm="Supprimer ce scénario ?" class="lien text-xs">supprimer</button> @endif
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Comparaison côte à côte (EF-M7-02, RC-13.4, T-23) --}}
        @if ($comparaison !== [])
            <section class="carte overflow-x-auto" aria-labelledby="titre-comparaison" tabindex="0">
                <h2 id="titre-comparaison" class="text-lg font-semibold">Comparaison</h2>
                <table class="mt-3 w-full text-sm">
                    <thead><tr><th scope="col" class="py-1 pr-3 text-left text-texte-doux"></th>
                        @foreach ($comparaison as $c) <th scope="col" class="pr-3 text-left">{{ $c['scenario']->libelle }}@if ($c['scenario']->statut === 'retenu') <span class="block text-xs font-normal">(retenu)</span> @endif</th> @endforeach
                    </tr></thead>
                    <tbody>
                        <tr class="border-t border-filet"><th scope="row" class="py-1 pr-3 text-left font-normal text-texte-doux">Coût total</th>@foreach ($comparaison as $c) <td class="pr-3">{{ $eur($c['cout']) }}</td> @endforeach</tr>
                        <tr class="border-t border-filet"><th scope="row" class="py-1 pr-3 text-left font-normal text-texte-doux">Couverture</th>@foreach ($comparaison as $c) <td class="pr-3">{{ $pct($c['bilan']->couverture) }}</td> @endforeach</tr>
                        <tr class="border-t border-filet"><th scope="row" class="py-1 pr-3 text-left font-normal text-texte-doux">Bilan (sécurité déduite)</th>@foreach ($comparaison as $c) <td class="pr-3">{{ $t($c['bilan']->bilan_t_ms) }} t MS</td> @endforeach</tr>
                        <tr class="border-t border-filet"><th scope="row" class="py-1 pr-3 text-left font-normal text-texte-doux">Autonomie</th>@foreach ($comparaison as $c) <td class="pr-3">{{ $c['bilan']->autonomie_jours }} jours</td> @endforeach</tr>
                        <tr class="border-t border-filet"><th scope="row" class="py-1 pr-3 text-left font-normal text-texte-doux">Rupture</th>@foreach ($comparaison as $c) <td class="pr-3">{{ $c['bilan']->date_rupture?->format('d/m/Y') ?? 'aucune' }}</td> @endforeach</tr>
                        <tr class="border-t border-filet"><th scope="row" class="py-1 pr-3 text-left font-normal text-texte-doux">Lots d'animaux en déficit nutritionnel</th>@foreach ($comparaison as $c) <td class="pr-3">{{ $c['lots_deficit'] }}</td> @endforeach</tr>
                        <tr class="border-t border-filet align-top"><th scope="row" class="py-1 pr-3 text-left font-normal text-texte-doux">Points de vigilance</th>
                            @foreach ($comparaison as $c) <td class="pr-3 text-xs"><ul class="space-y-1">@forelse ($c['vigilance'] as $v) <li>{{ $v }}</li> @empty <li>aucun</li> @endforelse</ul></td> @endforeach</tr>
                    </tbody>
                </table>
            </section>
        @endif
    @endif
</div>
