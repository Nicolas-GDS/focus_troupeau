@php
    use App\Services\Dossier\Affichage;
    $t = fn ($v) => Affichage::nombre((float) $v, 1);
    $pct = fn ($v) => Affichage::nombre(round(100 * (float) $v), 0).' %';
    $date = fn ($d) => $d?->format('d/m/Y') ?? '—';
@endphp
<div class="space-y-6">
    <p class="text-texte-doux">{{ $libelleElevage }}@if ($libelleElevage !== $elevage->code_pseudo) ({{ $elevage->code_pseudo }})@endif · <a href="{{ route('stocks', $elevage->id) }}" class="lien">stocks</a> · <a href="{{ route('troupeau', $elevage->id) }}" class="lien">troupeau</a></p>
    @if (session('status')) <p class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</p> @endif

    @if (! $inventaireCourant)
        <p class="carte text-texte-doux">Le bilan d'autonomie se calcule sur un inventaire des stocks et les rations du troupeau. <a href="{{ route('stocks', $elevage->id) }}" class="lien">Créer un inventaire</a></p>
    @else
        @php($i = $ecran['indicateurs'])
        <section class="carte text-sm" aria-label="Période">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <p>Inventaire du {{ $inventaireCourant->date_inventaire->format('d/m/Y') }}{{ $inventaireCourant->statut === 'courant' ? ' (en cours)' : ' (clos)' }} · période du {{ $inventaireCourant->date_debut->format('d/m/Y') }} au {{ $inventaireCourant->date_fin->format('d/m/Y') }} ({{ $ecran['calcul']['centrale']->jours }} jours)</p>
                @if ($inventaires->count() > 1)
                    <div>
                        <label for="choix-inv" class="etiquette">Inventaire</label>
                        <select id="choix-inv" wire:model.live="inventaire" class="champ">
                            @foreach ($inventaires as $inv) <option value="{{ $inv->id }}" @selected($inv->id === $inventaireCourant->id)>{{ $inv->date_inventaire->format('d/m/Y') }}{{ $inv->statut === 'courant' ? ' (en cours)' : '' }}</option> @endforeach
                        </select>
                    </div>
                @endif
            </div>
        </section>

        @if ($ecran['calcul']['centrale']->besoinStock <= 0)
            <p class="carte text-texte-doux">Aucune ration ne prélève sur les stocks pour cette période : décrire les lots d'animaux et leurs rations dans <a href="{{ route('troupeau', $elevage->id) }}" class="lien">Troupeau</a>.</p>
        @endif

        {{-- Trois indicateurs (EF-M6-03) et leur fourchette (EF-M6-04) --}}
        <section class="carte" aria-labelledby="titre-verdict">
            <h2 id="titre-verdict" class="text-lg font-semibold">Bilan de la période</h2>
            <dl class="mt-3 grid gap-4 sm:grid-cols-3">
                <div>
                    <dt class="text-sm text-texte-doux">Couverture des besoins</dt>
                    <dd class="text-2xl font-semibold">{{ $pct($i['couverture']['valeur']) }}</dd>
                    <dd class="text-xs text-texte-doux">fourchette {{ $pct($i['couverture']['basse']) }} à {{ $pct($i['couverture']['haute']) }}</dd>
                </div>
                <div>
                    <dt class="text-sm text-texte-doux">Autonomie</dt>
                    <dd class="text-2xl font-semibold">{{ $i['autonomie']['jours'] }} <span class="text-base font-normal">jours</span></dd>
                    <dd class="text-xs text-texte-doux">fourchette {{ $i['autonomie']['basse'] }} à {{ $i['autonomie']['haute'] }} jours</dd>
                    <dd class="text-sm">
                        @if ($ecran['rupture']['date'])
                            Rupture prévue le <strong>{{ $date($ecran['rupture']['date']) }}</strong>
                            <span class="block text-xs text-texte-doux">entre le {{ $date($ecran['rupture']['min']) }} et le {{ $ecran['rupture']['max'] ? $date($ecran['rupture']['max']) : 'la fin de période' }}</span>
                        @else
                            Aucune rupture prévue sur la période
                        @endif
                    </dd>
                </div>
                <div>
                    <dt class="text-sm text-texte-doux">{{ $i['bilan']['valeur'] < 0 ? 'Déficit' : 'Excédent' }} (sécurité déduite)</dt>
                    <dd class="text-2xl font-semibold">{{ $i['bilan']['valeur'] < 0 ? '−' : '+' }} {{ $t(abs($i['bilan']['valeur'])) }} <span class="text-base font-normal">t MS</span></dd>
                    <dd class="text-xs text-texte-doux">fourchette {{ $t($i['bilan']['basse']) }} à {{ $t($i['bilan']['haute']) }} t MS</dd>
                </div>
            </dl>
            <dl class="mt-4 grid gap-2 text-sm sm:grid-cols-3">
                <div><dt class="text-texte-doux">Stock utile</dt><dd>{{ $t($i['stock']['valeur']) }} t MS <span class="text-xs text-texte-doux">({{ $t($i['stock']['basse']) }} à {{ $t($i['stock']['haute']) }})</span></dd></div>
                <div><dt class="text-texte-doux">Besoin prélevé sur les stocks</dt><dd>{{ $t($ecran['calcul']['centrale']->besoinStock) }} t MS</dd></div>
                <div><dt class="text-texte-doux">Stock de sécurité ({{ $pct($inventaireCourant->marge_securite) }} du besoin)</dt><dd>{{ $t($ecran['calcul']['centrale']->securite) }} t MS
                    @if ($ecran['entame_securite']) <span class="block text-xs">entamé à partir du {{ $date($ecran['entame_securite']) }}</span> @endif</dd></div>
            </dl>
            @if ($ecran['repere'])
                <p class="mt-4 flex flex-wrap items-center gap-2 text-sm">
                    <x-pastille :etat="$i['couverture']['valeur'] >= $ecran['repere']['valeur'] ? 'couvert' : 'alerte'" :libelle="$i['couverture']['valeur'] >= $ecran['repere']['valeur'] ? 'Au-dessus du repère' : 'En dessous du repère'" />
                    {{ $ecran['repere']['texte'] }}
                    <span class="text-xs text-texte-doux">Repère : {{ $pct($ecran['repere']['valeur']) }} de couverture — {{ $ecran['repere']['source'] }}{{ $ecran['repere']['statut'] ? ' · '.$ecran['repere']['statut'] : '' }}</span>
                </p>
            @endif
        </section>

        {{-- Graphique (EF-M6-02) --}}
        <section class="carte" aria-labelledby="titre-graphique">
            <h2 id="titre-graphique" class="text-lg font-semibold">Projection des stocks</h2>
            <x-projection :points="$ecran['graphique']['points']" :familles="$ecran['graphique']['familles']" :securite="$ecran['graphique']['securite']" />
        </section>

        {{-- Par famille : stock, besoin, sécurité, rupture --}}
        <section class="carte overflow-x-auto" aria-labelledby="titre-familles">
            <h2 id="titre-familles" class="text-lg font-semibold">Par famille de fourrage</h2>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-texte-doux"><th scope="col" class="py-1 pr-3">Famille</th><th scope="col" class="pr-3 text-right">Stock (mouvements compris)</th><th scope="col" class="pr-3 text-right">Besoin</th><th scope="col" class="pr-3 text-right">Sécurité</th><th scope="col">Rupture</th></tr></thead>
                <tbody>
                    @foreach ($ecran['ruptures_famille'] as $code => $f)
                        <tr wire:key="famille-{{ $code }}" class="border-t border-filet">
                            <th scope="row" class="py-1 pr-3 text-left font-normal">{{ $f['libelle'] }}</th>
                            <td class="pr-3 text-right">{{ $t($f['stock']) }}</td>
                            <td class="pr-3 text-right">{{ $t($f['besoin']) }}</td>
                            <td class="pr-3 text-right">{{ $t($f['securite']) }}</td>
                            <td>@if ($f['rupture']) <x-pastille etat="critique" :libelle="'le '.$date($f['rupture'])" /> @else <span class="text-texte-doux">aucune</span> @endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-1 text-xs text-texte-doux">t MS. Dans une famille, le lot le plus ancien est consommé en premier ; une famille est en rupture quand tous ses lots sont épuisés.</p>
            @if ($ecran['lots'] !== [])
                <h3 class="mt-4 font-semibold">Lots de stock</h3>
                <ul class="mt-1 space-y-1 text-sm">
                    @foreach ($ecran['lots'] as $lot)
                        <li>{{ $lot['libelle'] }} : {{ $lot['epuisement'] ? 'épuisé le '.$date($lot['epuisement']) : 'non épuisé sur la période' }}{{ $lot['rupture'] ? ' — rupture le '.$date($lot['rupture']) : '' }}</li>
                    @endforeach
                </ul>
            @endif
            @if ($ecran['ecarts'] !== [])
                <h3 class="mt-4 font-semibold">Écarts de recalage</h3>
                <ul class="mt-1 space-y-1 text-sm">
                    @foreach ($ecran['ecarts'] as $e)
                        <li>{{ $e['libelle'] }}, le {{ $e['date']->format('d/m/Y') }} : projeté {{ $t($e['projete']) }}, recompté {{ $t($e['recompte']) }}, écart {{ $t($e['ecart']) }} t MS</li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Projection mensuelle (EF-M6-02) --}}
        <section class="carte overflow-x-auto" aria-labelledby="titre-mensuel">
            <h2 id="titre-mensuel" class="text-lg font-semibold">Projection mois par mois</h2>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-texte-doux"><th scope="col" class="py-1 pr-3">Mois</th><th scope="col" class="pr-3">Famille</th><th scope="col" class="pr-3 text-right">Stock début</th><th scope="col" class="pr-3 text-right">Consommation</th><th scope="col" class="pr-3 text-right">Mouvements</th><th scope="col" class="text-right">Stock fin</th></tr></thead>
                <tbody>
                    @foreach ($ecran['mensuel'] as $m)
                        @foreach ($m['familles'] as $code => $f)
                            @continue(! isset($ecran['familles'][$code]))
                            <tr wire:key="mois-{{ $m['mois'] }}-{{ $code }}" class="border-t border-filet">
                                <td class="py-1 pr-3">{{ $loop->first ? $m['libelle'] : '' }}</td>
                                <td class="pr-3">{{ $ecran['familles'][$code] }}</td>
                                <td class="pr-3 text-right">{{ $t($f['stock_debut']) }}</td>
                                <td class="pr-3 text-right">{{ $t($f['conso']) }}</td>
                                <td class="pr-3 text-right">{{ $t($f['mvts']) }}</td>
                                <td class="text-right">{{ $t($f['stock_fin']) }}</td>
                            </tr>
                        @endforeach
                        <tr class="border-t border-filet font-semibold">
                            <td class="py-1 pr-3"></td><td class="pr-3">Total</td>
                            <td class="pr-3 text-right">{{ $t($m['stock_debut']) }}</td><td class="pr-3 text-right">{{ $t($m['conso']) }}</td>
                            <td class="pr-3 text-right">{{ $t($m['mvts']) }}</td><td class="text-right">{{ $t($m['stock_fin']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <p class="mt-1 text-xs text-texte-doux">t MS ; calcul journalier, affichage mensuel (RC-11).</p>
        </section>

        {{-- Phases de la période de référence (RC-10, voie UGB) --}}
        <section class="carte" aria-labelledby="titre-periode">
            <h2 id="titre-periode" class="text-lg font-semibold">Période de référence (voie UGB)</h2>
            <p class="mt-1 text-sm text-texte-doux">Les rations portent déjà leurs dates : ces phases ne servent qu'au cadrage par les UGB. {{ $ecran['reperes_periode'] }}</p>
            @if ($ecran['periodes']->isNotEmpty())
                <ul class="mt-2 space-y-1 text-sm">
                    @foreach ($ecran['periodes'] as $p)
                        <li wire:key="periode-{{ $p->id }}" class="flex flex-wrap items-center gap-2">
                            {{ $libellesPeriode[$p->libelle] ?? $p->libelle }} : du {{ $p->date_debut->format('d/m/Y') }} au {{ $p->date_fin->format('d/m/Y') }}, coefficient {{ Affichage::nombre((float) $p->coefficient, 2) }}
                            @if ($peutSaisir) <button type="button" wire:click="supprimerPeriode({{ $p->id }})" class="lien text-xs">retirer</button> @endif
                        </li>
                    @endforeach
                </ul>
            @endif
            @php($ugb = $ecran['calcul']['voie_ugb'])
            <p class="mt-2 text-sm">Voie UGB : {{ $ugb['t_ms'] === null ? 'coefficients UGB non renseignés' : $t($ugb['t_ms']).' t MS' }} @if ($ugb['jours_equivalents'] !== null) sur {{ Affichage::nombre($ugb['jours_equivalents'], 0) }} jours équivalents @endif · voie ration : {{ $t($ecran['calcul']['besoin_ration_t_ms']) }} t MS avant pertes</p>
            @if ($peutSaisir)
                <form wire:submit="ajouterPeriode" class="mt-3 flex flex-wrap items-end gap-2">
                    <div>
                        <label for="periode-libelle" class="etiquette">Phase</label>
                        <select id="periode-libelle" wire:model="nouvellePeriode.libelle" class="champ">
                            @foreach ($libellesPeriode as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                        </select>
                    </div>
                    <div><label for="periode-debut" class="etiquette">Du</label><input id="periode-debut" type="date" wire:model="nouvellePeriode.debut" class="champ"></div>
                    <div><label for="periode-fin" class="etiquette">Au</label><input id="periode-fin" type="date" wire:model="nouvellePeriode.fin" class="champ"></div>
                    <div><label for="periode-coeff" class="etiquette">Coefficient (complémentation estivale)</label><input id="periode-coeff" type="text" inputmode="decimal" wire:model="nouvellePeriode.coefficient" class="champ w-24" placeholder="défaut"></div>
                    <button type="submit" class="bouton-secondaire">Ajouter</button>
                </form>
                @error('periode') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
                @error('nouvellePeriode.*') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
            @endif
        </section>

        {{-- Hypothèses et sources (EF-GEN-02) --}}
        <section class="carte" aria-labelledby="titre-hypotheses">
            <h2 id="titre-hypotheses" class="text-lg font-semibold">Hypothèses du calcul</h2>
            <ul class="mt-2 space-y-1 text-sm">
                @foreach ($ecran['hypotheses'] as $h)
                    <li>{{ $h['libelle'] }} : <strong>{{ $h['valeur'] }}</strong> <span class="text-xs text-texte-doux">— {{ $h['source'] }}{{ $h['statut'] ? ' · '.$h['statut'] : '' }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
