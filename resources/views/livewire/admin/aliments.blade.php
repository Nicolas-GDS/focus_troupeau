<div class="space-y-6">
    @include('livewire.admin.partiels.entete-referentiels', ['table' => 'aliments'])

    @if ($formulaireOuvert)
        <section class="carte" aria-labelledby="titre-aliment">
            <h2 id="titre-aliment" class="text-lg font-semibold">{{ $alimentId ? 'Modifier l\'aliment' : 'Nouvel aliment' }}</h2>
            <p class="text-sm text-texte-doux">Aliment décrit par sa catégorie et ses valeurs, jamais par une marque (EF-GEN-04). Valeurs par kg de MS, unités des bulletins.</p>
            @error('aliment') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
            <form wire:submit="enregistrer" class="mt-3 space-y-3">
                <div class="grid gap-2 sm:grid-cols-3">
                    <div><label for="a-code" class="etiquette">Code</label><input id="a-code" type="text" wire:model="aliment.code" class="champ w-full"></div>
                    <div class="sm:col-span-2"><label for="a-libelle" class="etiquette">Libellé</label><input id="a-libelle" type="text" wire:model="aliment.libelle" class="champ w-full"></div>
                    <div><label for="a-cat" class="etiquette">Catégorie</label><select id="a-cat" wire:model="aliment.categorie" class="champ w-full">@foreach ($categories as $c) <option value="{{ $c }}">{{ str_replace('_', ' ', $c) }}</option> @endforeach</select></div>
                    <div><label for="a-fam" class="etiquette">Famille de fourrage</label><select id="a-fam" wire:model="aliment.famille_code" class="champ w-full"><option value="">—</option>@foreach ($familles as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach</select></div>
                    <div><label for="a-ms" class="etiquette">MS (%)</label><input id="a-ms" type="text" inputmode="decimal" wire:model="aliment.ms_pct" class="champ w-full"></div>
                    <div><label for="a-ref" class="etiquette">Référence</label><select id="a-ref" wire:model="aliment.reference_id" class="champ w-full"><option value="">—</option>@foreach ($references as $id => $cle) <option value="{{ $id }}">{{ $cle }}</option> @endforeach</select></div>
                    <div><label for="a-statut" class="etiquette">Statut épistémique</label><select id="a-statut" wire:model="aliment.statut_epistemique" class="champ w-full">@foreach ($statuts as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach</select></div>
                    <label class="mt-5 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="aliment.actif"> Aliment proposé dans les rations</label>
                </div>
                <fieldset><legend class="etiquette">Valeurs par kg de MS</legend>
                    <div class="grid gap-2 sm:grid-cols-4">
                        @foreach ($parametres as $code => $p)
                            <div><label for="v-{{ $code }}" class="etiquette">{{ $p->libelle }} ({{ $p->unite }})</label><input id="v-{{ $code }}" type="text" inputmode="decimal" wire:model="valeurs.{{ $code }}" class="champ w-full"></div>
                        @endforeach
                    </div></fieldset>
                <div class="flex gap-2"><button type="submit" class="bouton-primaire">Enregistrer</button><button type="button" wire:click="$set('formulaireOuvert', false)" class="bouton-secondaire">Annuler</button></div>
            </form>
        </section>
    @endif

    <section class="carte overflow-x-auto" tabindex="0" aria-labelledby="titre-aliments">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <h2 id="titre-aliments" class="text-lg font-semibold">Aliments de table ({{ $aliments->count() }})</h2>
            @if ($peutEcrire) <button type="button" wire:click="nouveau" class="bouton-secondaire">Nouvel aliment</button> @endif
        </div>
        @if ($aliments->isEmpty())
            <p class="mt-2 text-sm text-texte-doux">Aucun aliment : les tables INRAE-CIRAD-AFZ ne sont pas chargées (point ouvert 5). Les concentrés et minéraux se décrivent par leur étiquette dans le dossier.</p>
        @else
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-texte-doux"><th scope="col" class="py-1 pr-3">Code</th><th scope="col" class="pr-3">Libellé</th><th scope="col" class="pr-3">Catégorie</th><th scope="col" class="pr-3">MS</th><th scope="col" class="pr-3">Statut</th><th scope="col"></th></tr></thead>
                <tbody>
                @foreach ($aliments as $a)
                    <tr wire:key="a-{{ $a->id }}" class="border-t border-filet">
                        <td class="py-1 pr-3">{{ $a->code }}</td><td class="pr-3">{{ $a->libelle }}@unless ($a->actif) <span class="text-xs text-texte-doux">(désactivé)</span>@endunless</td>
                        <td class="pr-3">{{ str_replace('_', ' ', $a->categorie) }}</td><td class="pr-3">{{ \App\Services\Dossier\Affichage::nombre((float) $a->ms_pct) }} %</td>
                        <td class="pr-3 text-xs">{{ $statuts[$a->statut_epistemique] ?? $a->statut_epistemique }}</td>
                        <td>@if ($peutEcrire) <button type="button" wire:click="modifier({{ $a->id }})" class="lien text-xs">modifier</button> @endif</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
</div>
