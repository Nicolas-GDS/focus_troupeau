@php
    use App\Livewire\Dossier\Etats;
    use App\Services\Dossier\Affichage;
@endphp
<div class="carte">
    <h3 class="font-semibold">Eau d'abreuvement</h3>
    @error('saisie') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror

    @foreach ($analyses as $item)
        @php($analyse = $item['analyse'])
        @php($statut = Etats::statut($analyse->statut))
        <div wire:key="eau-{{ $analyse->id }}" class="mt-4 rounded-md border border-filet p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-medium">{{ $analyse->point_prelevement }} · {{ $analyse->date_prelevement->format('d/m/Y') }}</p>
                <x-pastille :etat="$statut['etat']" :libelle="$statut['libelle']" />
            </div>
            <div class="mt-2 overflow-x-auto" tabindex="0">
                <table class="min-w-full text-sm">
                    <caption class="sr-only">Résultats de l'analyse d'eau, classe, repère et source</caption>
                    <thead class="text-left text-texte-doux">
                        <tr><th scope="col" class="py-1 pr-4">Paramètre</th><th scope="col" class="py-1 pr-4">Valeur</th><th scope="col" class="py-1 pr-4">Lecture</th><th scope="col" class="py-1">Repère et source</th></tr>
                    </thead>
                    <tbody class="divide-y divide-filet">
                        @foreach ($item['lignes'] as $ligne)
                            @php($classe = Etats::classe($ligne['classe'], $ligne['gravite']))
                            <tr class="align-top">
                                <th scope="row" class="py-1 pr-4 text-left font-medium">{{ $ligne['libelle'] }}</th>
                                <td class="py-1 pr-4 whitespace-nowrap">{{ $ligne['censure'] }}{{ Affichage::nombre($ligne['valeur']) }} {{ $ligne['unite'] === '-' ? '' : $ligne['unite'] }}</td>
                                <td class="py-1 pr-4"><x-pastille :etat="$classe['etat']" :libelle="$classe['libelle']" /></td>
                                <td class="py-1">
                                    @if ($ligne['repere'])
                                        {{ $ligne['repere'] }}
                                        @include('livewire.dossier.saisie.partiels.source', ['source' => ['source' => $ligne['source'], 'statut' => $ligne['statut'], 'a_valider' => $ligne['a_valider']]])
                                    @else
                                        <span class="text-texte-doux">Repère non disponible (grille eau à compléter)</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($item['peutValider']) <button type="button" wire:click="valider({{ $analyse->id }})" class="bouton-secondaire">Valider l'analyse</button> @endif
                @if ($item['peutSupprimer']) <button type="button" wire:click="supprimer({{ $analyse->id }})" wire:confirm="Supprimer cette analyse d'eau ?" class="bouton-secondaire">Supprimer</button> @endif
            </div>
        </div>
    @endforeach

    @if ($analyses->isEmpty()) <p class="mt-2 text-sm text-texte-doux">Aucune analyse d'eau.</p> @endif

    @if ($peutAjouter)
        <form wire:submit="ajouter" class="mt-4 space-y-3">
            <h4 class="font-medium">Saisir une analyse d'eau</h4>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label for="eau-point" class="etiquette">Point de prélèvement</label>
                    <input id="eau-point" type="text" wire:model="point" class="champ w-full" placeholder="source, forage, abreuvoir…" maxlength="200">
                    @error('point') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="eau-date" class="etiquette">Date de prélèvement</label>
                    <input id="eau-date" type="date" wire:model="date" class="champ w-full">
                </div>
                <div>
                    <label for="eau-labo" class="etiquette">Laboratoire</label>
                    <select id="eau-labo" wire:model="laboratoire" class="champ w-full">
                        <option value="">Non précisé</option>
                        @foreach ($laboratoires as $id => $nom) <option value="{{ $id }}">{{ $nom }}</option> @endforeach
                    </select>
                </div>
            </div>
            <div class="grid gap-3 sm:grid-cols-2">
                @foreach ($parametres as $p)
                    <div wire:key="eau-saisie-{{ $p->code }}">
                        <label for="eau-{{ $p->code }}" class="etiquette">{{ $p->libelle }} ({{ $p->unite }})</label>
                        <div class="flex items-center gap-2">
                            <input id="eau-{{ $p->code }}" type="number" step="any" min="0" inputmode="decimal" wire:model="valeurs.{{ $p->code }}" class="champ w-full">
                            <label class="flex items-center gap-1 text-xs whitespace-nowrap"><input type="checkbox" wire:model="censures.{{ $p->code }}"> sous la limite</label>
                        </div>
                    </div>
                @endforeach
            </div>
            @error('valeurs.*') <p class="text-sm text-etat-critique">{{ $message }}</p> @enderror
            <button type="submit" class="bouton-principal">Enregistrer l'analyse d'eau</button>
        </form>
    @endif
</div>
