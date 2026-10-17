@php
    use App\Services\Dossier\Affichage;
    $t = fn ($v) => Affichage::nombre((float) $v, 1);
    $eur = fn ($v) => number_format(round((float) $v), 0, ',', ' ').' €';
    $pct = fn ($v) => Affichage::nombre(round(100 * (float) $v), 0).' %';
    $b = $evaluation['bilan'];
    $aides = [
        'achat_fourrage' => ['quantite' => 'Quantité achetée (t MS)', 'parametre' => 'Prix (€/t MS)', 'famille' => 'Famille achetée'],
        'concentre_substitution' => ['quantite' => 'Quantité de concentré (t MS)', 'parametre' => 'Prix (€/t MS)', 'famille' => 'Ration à base de'],
        'decapitalisation' => ['quantite' => 'UGB retirés', 'parametre' => 'Perte de produit (€/UGB)', 'famille' => 'Famille économisée'],
        'genisses' => ['quantite' => 'Génisses supprimées (têtes)', 'parametre' => 't MS par génisse', 'famille' => 'Famille économisée'],
        'paturage' => ['quantite' => 'Jours gagnés', 'parametre' => 'Coût total (€)', 'famille' => 'Famille économisée'],
        'ratelier' => ['quantite' => '—', 'parametre' => 'Nouveau taux de pertes (ex. 0,04)', 'famille' => 'Famille économisée'],
        'reaffectation' => ['quantite' => 'Quantité réaffectée (t MS)', 'parametre' => '—', 'famille' => 'Famille déficitaire'],
        'autre' => ['quantite' => 'Gain (t MS)', 'parametre' => 'Coût total (€)', 'famille' => 'Famille'],
    ];
    $aide = $aides[$ligne['levier_code']] ?? $aides['autre'];
@endphp
<div class="space-y-6">
    <p class="text-texte-doux">{{ $libelleElevage }} · <a href="{{ route('approvisionnement', $elevage->id) }}" class="lien">approvisionnement</a> · <a href="{{ route('autonomie', $elevage->id) }}" class="lien">bilan d'autonomie</a> · <a href="{{ route('scenarios.pdf', [$elevage->id, $scenario->id]) }}" class="lien">scénario en PDF</a></p>
    @if (session('status')) <p class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</p> @endif

    <section class="carte" aria-labelledby="titre-scenario">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h2 id="titre-scenario" class="text-lg font-semibold">{{ $scenario->libelle }}</h2>
            @if ($scenario->statut === 'retenu') <x-pastille etat="couvert" :libelle="'Retenu le '.$scenario->retenu_le?->format('d/m/Y')" /> @endif
        </div>
        <p class="text-sm text-texte-doux">Inventaire du {{ $inventaire->date_inventaire->format('d/m/Y') }} · {{ $scenario->origine === 'proposition' ? 'issu de la proposition initiale' : 'créé par l\'utilisateur' }}</p>
        <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-4">
            <div><dt class="text-texte-doux">Coût total</dt><dd class="text-xl font-semibold">{{ $eur($evaluation['cout']) }}</dd></div>
            <div><dt class="text-texte-doux">Couverture</dt><dd class="text-xl font-semibold">{{ $pct($b->couverture) }}</dd></div>
            <div><dt class="text-texte-doux">Autonomie</dt><dd class="text-xl font-semibold">{{ $b->autonomie_jours }} jours</dd></div>
            <div><dt class="text-texte-doux">Rupture</dt><dd class="text-xl font-semibold">{{ $b->date_rupture?->format('d/m/Y') ?? 'aucune' }}</dd>
                @if ($b->date_rupture) <dd class="text-xs text-texte-doux">entre le {{ $b->date_rupture_min?->format('d/m/Y') ?? '—' }} et le {{ $b->date_rupture_max?->format('d/m/Y') ?? 'la fin de période' }}</dd> @endif</div>
        </dl>
        <p class="mt-2 text-sm">Bilan, sécurité déduite : {{ $t($b->bilan_t_ms) }} t MS <span class="text-xs text-texte-doux">(RC-13.4 : gains des leviers appliqués à la projection à leur date d'effet)</span></p>
        @if ($evaluation['vigilance'] !== [])
            <h3 class="mt-3 font-semibold">Points de vigilance</h3>
            <ul class="mt-1 list-disc pl-5 text-sm">@foreach ($evaluation['vigilance'] as $v) <li>{{ $v }}</li> @endforeach</ul>
        @endif
        @if ($peutSaisir && $scenario->statut !== 'retenu')
            <button type="button" wire:click="retenir" wire:confirm="Retenir ce scénario ? Ses achats seront ajoutés à l'inventaire en cours ; ceux d'un scénario retenu auparavant seront retirés." class="bouton-primaire mt-3">Retenir ce scénario</button>
        @endif
    </section>

    <section class="carte overflow-x-auto" aria-labelledby="titre-leviers">
        <h2 id="titre-leviers" class="text-lg font-semibold">Leviers</h2>
        @if ($evaluation['lignes']->isEmpty())
            <p class="mt-2 text-sm text-texte-doux">Aucun levier.</p>
        @else
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-texte-doux"><th scope="col" class="py-1 pr-3">Levier</th><th scope="col" class="pr-3 text-right">Quantité</th><th scope="col" class="pr-3 text-right">Gain</th><th scope="col" class="pr-3 text-right">Coût</th><th scope="col" class="pr-3">Effet</th><th scope="col"></th></tr></thead>
                <tbody>
                    @foreach ($evaluation['lignes'] as $l)
                        <tr wire:key="ligne-{{ $l->id }}" class="border-t border-filet align-top">
                            <td class="py-1 pr-3">{{ $libelles[$l->levier_code] ?? $l->levier_code }}@if ($l->famille_code) <span class="block text-xs text-texte-doux">@if ($l->famille_source_code){{ $familles[$l->famille_source_code] ?? '' }} → @endif{{ $familles[$l->famille_code] ?? $l->famille_code }}</span> @endif</td>
                            <td class="pr-3 text-right">{{ Affichage::nombre((float) $l->quantite, 2) }} {{ $l->unite }}</td>
                            <td class="pr-3 text-right">{{ $t($l->gain_t_ms) }} t MS<span class="block text-xs text-texte-doux">au {{ $l->date_effet?->format('d/m/Y') ?? '—' }}</span></td>
                            <td class="pr-3 text-right">{{ $eur($l->cout_eur) }}@if ($l->prix_unitaire !== null) <span class="block text-xs text-texte-doux">{{ $eur($l->prix_unitaire) }}/t MS</span> @endif</td>
                            <td class="pr-3 text-xs">{{ $l->commentaire }}</td>
                            <td class="whitespace-nowrap">@if ($peutSaisir && $l->levier_code !== 'correction_nutritionnelle') <button type="button" wire:click="editer({{ $l->id }})" class="lien text-xs">modifier</button> @endif
                                @if ($peutSaisir) <button type="button" wire:click="supprimerLigne({{ $l->id }})" class="lien text-xs">retirer</button> @endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        @if ($peutSaisir)
            <form wire:submit="enregistrerLigne" class="mt-4 grid gap-2 sm:grid-cols-3">
                <div><label for="ligne-levier" class="etiquette">Levier</label>
                    <select id="ligne-levier" wire:model.live="ligne.levier_code" class="champ w-full">
                        @foreach (App\Domain\Autonomie\Levier::CODES as $code) <option value="{{ $code }}">{{ $libelles[$code] }}</option> @endforeach
                    </select></div>
                <div><label for="ligne-famille" class="etiquette">{{ $aide['famille'] }}</label>
                    <select id="ligne-famille" wire:model.live="ligne.famille_code" class="champ w-full">
                        <option value="">par défaut (plus fort déficit)</option>
                        @foreach ($familles as $code => $libelle) @continue($code === 'CONCENTRE') <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                    </select></div>
                @if ($ligne['levier_code'] === 'reaffectation')
                    <div><label for="ligne-source" class="etiquette">Famille cédée (excédentaire)</label>
                        <select id="ligne-source" wire:model="ligne.famille_source_code" class="champ w-full"><option value="">Choisir…</option>
                            @foreach ($familles as $code => $libelle) @continue($code === 'CONCENTRE') <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                        </select></div>
                @endif
                @if ($ligne['levier_code'] !== 'ratelier')
                    <div><label for="ligne-quantite" class="etiquette">{{ $aide['quantite'] }}</label><input id="ligne-quantite" type="text" inputmode="decimal" wire:model="ligne.quantite" class="champ w-full"></div>
                @endif
                @if ($ligne['levier_code'] !== 'reaffectation')
                    <div><label for="ligne-parametre" class="etiquette">{{ $aide['parametre'] }}</label><input id="ligne-parametre" type="text" inputmode="decimal" wire:model="ligne.parametre" class="champ w-full" placeholder="{{ $defaut !== null ? Affichage::nombre($defaut) : '' }}">
                        @if ($defaut !== null) <span class="text-xs text-texte-doux">Défaut : {{ Affichage::nombre($defaut) }} — valeur indicative à valider (RC-13.3)</span> @endif</div>
                @endif
                <div><label for="ligne-date" class="etiquette">Date d'effet ou de livraison</label><input id="ligne-date" type="date" wire:model="ligne.date_effet" class="champ w-full"></div>
                <div class="flex items-end gap-2">
                    <button type="submit" class="bouton-secondaire">{{ $ligneEditee ? 'Enregistrer' : 'Ajouter le levier' }}</button>
                    @if ($ligneEditee) <button type="button" wire:click="$set('ligneEditee', null)" class="lien text-xs">annuler</button> @endif
                </div>
            </form>
            @error('ligne') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
            @error('ligne.*') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
        @endif
    </section>
</div>
