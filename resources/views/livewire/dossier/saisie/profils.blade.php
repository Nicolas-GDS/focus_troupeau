@php
    use App\Livewire\Dossier\Etats;
    use App\Services\Dossier\Affichage;
@endphp
<div class="carte">
    <h3 class="font-semibold">Profils sanguins</h3>
    <p class="mt-1 text-sm text-texte-doux">Résultats du laboratoire saisis par élément ; la lecture compare la moyenne du lot au repère, dans la même unité.</p>
    @error('saisie') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror

    @if ($lignes !== [])
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full text-sm">
                <caption class="sr-only">Profils sanguins : valeur, lecture, repère et source</caption>
                <thead class="text-left text-texte-doux">
                    <tr><th scope="col" class="py-1 pr-4">Élément</th><th scope="col" class="py-1 pr-4">Valeur</th><th scope="col" class="py-1 pr-4">Lecture</th><th scope="col" class="py-1 pr-4">Repère et source</th><th scope="col" class="py-1">Statut</th></tr>
                </thead>
                <tbody class="divide-y divide-filet">
                    @foreach ($lignes as $l)
                        @php($p = $l['profil'])
                        @php($etat = Etats::profil($l['lecture']))
                        <tr wire:key="profil-{{ $p->id }}" class="align-top">
                            <th scope="row" class="py-1 pr-4 text-left font-medium">{{ ucfirst($p->parametre) }} <span class="block text-xs font-normal text-texte-doux">{{ $p->matrice }} · {{ $p->date_prelevement->format('d/m/Y') }}@if ($p->nb_animaux) · {{ $p->nb_animaux }} animaux @endif</span></th>
                            <td class="py-1 pr-4 whitespace-nowrap">
                                {{ Affichage::nombre((float) $p->valeur_moyenne) }} {{ $p->unite }}
                                @if ($p->valeur_min !== null || $p->valeur_max !== null)
                                    <span class="block text-xs text-texte-doux">de {{ Affichage::nombre($p->valeur_min === null ? null : (float) $p->valeur_min) }} à {{ Affichage::nombre($p->valeur_max === null ? null : (float) $p->valeur_max) }}</span>
                                @endif
                            </td>
                            <td class="py-1 pr-4">
                                <x-pastille :etat="$etat['etat']" :libelle="$etat['libelle']" />
                                @if ($l['unite_differente']) <span class="block text-xs text-etat-vigilance">unité différente de celle du repère : pas de lecture</span> @endif
                            </td>
                            <td class="py-1 pr-4">{{ $l['repere'] ?? 'Repère non disponible' }} @include('livewire.dossier.saisie.partiels.source', ['source' => $l['source']])
                                @if ($p->commentaire) <span class="block text-xs">{{ $p->commentaire }}</span> @endif
                            </td>
                            <td class="py-1">
                                {{ Etats::statut($p->statut)['libelle'] }}
                                @if ($l['peutValider']) <button type="button" wire:click="valider({{ $p->id }})" class="lien block">Valider</button> @endif
                                @if ($l['peutSupprimer']) <button type="button" wire:click="supprimer({{ $p->id }})" wire:confirm="Supprimer ce résultat ?" class="lien block">Supprimer</button> @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <p class="mt-2 text-sm text-texte-doux">Aucun profil sanguin saisi.</p>
    @endif

    @if ($peutAjouter)
        <form wire:submit="ajouter" class="mt-4 grid gap-3 sm:grid-cols-3">
            <h4 class="font-medium sm:col-span-3">Saisir un résultat</h4>
            <div>
                <label for="p-date" class="etiquette">Date de prélèvement</label>
                <input id="p-date" type="date" wire:model="date" class="champ w-full">
            </div>
            <div>
                <label for="p-espece" class="etiquette">Espèce</label>
                <select id="p-espece" wire:model="espece" class="champ w-full">
                    @foreach ($especes as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="p-matrice" class="etiquette">Matrice</label>
                <select id="p-matrice" wire:model="matrice" class="champ w-full">
                    @foreach ($matrices as $m) <option value="{{ $m }}">{{ $m }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="p-element" class="etiquette">Élément</label>
                <input id="p-element" type="text" list="p-elements" wire:model="element" class="champ w-full" maxlength="60">
                <datalist id="p-elements">
                    @foreach ($elements as $code => $matrice) <option value="{{ $code }}">{{ $code }} ({{ $matrice }})</option> @endforeach
                </datalist>
                @error('element') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="p-moyenne" class="etiquette">Valeur moyenne</label>
                <input id="p-moyenne" type="number" step="any" min="0" inputmode="decimal" wire:model="moyenne" class="champ w-full">
                @error('moyenne') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="p-unite" class="etiquette">Unité</label>
                <input id="p-unite" type="text" wire:model="unite" class="champ w-full" maxlength="20">
            </div>
            <div>
                <label for="p-min" class="etiquette">Minimum (facultatif)</label>
                <input id="p-min" type="number" step="any" min="0" inputmode="decimal" wire:model="minimum" class="champ w-full">
            </div>
            <div>
                <label for="p-max" class="etiquette">Maximum (facultatif)</label>
                <input id="p-max" type="number" step="any" min="0" inputmode="decimal" wire:model="maximum" class="champ w-full">
            </div>
            <div>
                <label for="p-nb" class="etiquette">Nombre d'animaux</label>
                <input id="p-nb" type="number" min="1" inputmode="numeric" wire:model="nbAnimaux" class="champ w-full">
            </div>
            <div>
                <label for="p-labo" class="etiquette">Laboratoire</label>
                <select id="p-labo" wire:model="laboratoire" class="champ w-full">
                    <option value="">Non précisé</option>
                    @foreach ($laboratoires as $id => $nom) <option value="{{ $id }}">{{ $nom }}</option> @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label for="p-commentaire" class="etiquette">Commentaire (sérum hémolysé, animal…)</label>
                <input id="p-commentaire" type="text" wire:model="commentaire" class="champ w-full" maxlength="1000">
            </div>
            <div class="sm:col-span-3"><button type="submit" class="bouton-principal">Enregistrer le résultat</button></div>
        </form>
    @endif
</div>
