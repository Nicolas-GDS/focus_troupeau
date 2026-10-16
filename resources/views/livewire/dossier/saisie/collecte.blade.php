@php
    use App\Livewire\Dossier\Etats;
    use App\Services\Dossier\Affichage;
@endphp
<div class="space-y-4">
    @error('saisie')
        <div class="rounded-md border border-etat-critique bg-etat-critique-fond p-3 text-sm" role="alert">{{ $message }}</div>
    @enderror

    <div class="carte">
        <h3 class="font-semibold">Entretien et observations</h3>
        @if ($modifiable)
            <form wire:submit="enregistrerVisite" class="mt-3 grid gap-4 sm:grid-cols-3">
                <div>
                    <label for="c-date" class="etiquette">Date de la visite</label>
                    <input id="c-date" type="date" wire:model="dateVisite" class="champ w-full" required>
                    @error('dateVisite') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="c-motif" class="etiquette">Motif</label>
                    <select id="c-motif" wire:model="motif" class="champ w-full">
                        @foreach ($motifs as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label for="c-detail" class="etiquette">Précision du motif</label>
                    <input id="c-detail" type="text" wire:model="motifDetail" class="champ w-full" maxlength="500">
                </div>
                <div class="sm:col-span-3">
                    <label for="c-compte-rendu" class="etiquette">Compte rendu de l'entretien (troubles constatés, conduite, ration déclarée)</label>
                    <textarea id="c-compte-rendu" wire:model="compteRendu" rows="5" class="champ w-full"></textarea>
                    @error('compteRendu') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-3"><button type="submit" class="bouton-principal">Enregistrer la visite</button></div>
            </form>
        @else
            <p class="mt-2 whitespace-pre-line text-sm">{{ $visite->compte_rendu ?: 'Aucun compte rendu.' }}</p>
        @endif
    </div>

    <div class="carte">
        <h3 class="font-semibold">Bilan sanitaire de la campagne {{ $visite->campagne_code ?? '' }}</h3>
        <p class="mt-1 text-sm text-texte-doux">Taux = nombre de cas ÷ nombre de vêlages. Au-delà du seuil 2, le trouble signale un problème d'élevage.</p>
        @if ($bilan === [])
            <p class="mt-3 text-sm text-texte-doux">La visite n'est rattachée à aucune campagne.</p>
        @else
            <div class="mt-3 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <caption class="sr-only">Indicateurs du bilan sanitaire, valeur, situation par rapport aux seuils S1 et S2, source</caption>
                    <thead class="text-left text-texte-doux">
                        <tr>
                            <th scope="col" class="py-2 pr-4">Indicateur</th>
                            <th scope="col" class="py-2 pr-4">Valeur</th>
                            <th scope="col" class="py-2 pr-4">Situation</th>
                            <th scope="col" class="py-2 pr-4">Seuils et source</th>
                            @if ($peutSaisirBilan) <th scope="col" class="py-2">Saisie</th> @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-filet">
                        @foreach ($bilan as $ligne)
                            @php($etat = Etats::bilan($ligne['classe']))
                            <tr wire:key="bilan-{{ $ligne['code'] }}" class="align-top">
                                <th scope="row" class="py-2 pr-4 text-left font-medium">{{ $ligne['libelle'] }}</th>
                                <td class="py-2 pr-4 whitespace-nowrap">
                                    {{ $ligne['valeur'] === null ? '—' : Affichage::nombre($ligne['valeur'], $ligne['mode'] === 'taux' ? 1 : 0).' '.$ligne['unite'] }}
                                    @if ($ligne['indicateur']?->nb_cas !== null)
                                        <span class="block text-xs text-texte-doux">{{ $ligne['indicateur']->nb_cas }} cas / {{ $ligne['indicateur']->denominateur }} vêlages</span>
                                    @endif
                                    @if ($ligne['precedente'] !== null)
                                        <span class="block text-xs text-texte-doux">campagne précédente : {{ Affichage::nombre($ligne['precedente'], $ligne['mode'] === 'taux' ? 1 : 0) }} {{ $ligne['unite'] }}</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4">@if ($ligne['valeur'] !== null) <x-pastille :etat="$etat['etat']" :libelle="$etat['libelle']" /> @endif</td>
                                <td class="py-2 pr-4">
                                    @if ($ligne['seuils'])
                                        {{ $ligne['seuils'] }}
                                        @include('livewire.dossier.saisie.partiels.source', ['source' => $ligne['source']])
                                    @else
                                        <span class="text-texte-doux">Repère non disponible</span>
                                    @endif
                                </td>
                                @if ($peutSaisirBilan)
                                    <td class="py-2">
                                        <form wire:submit="enregistrerIndicateur('{{ $ligne['code'] }}')" class="flex flex-wrap items-end gap-2">
                                            @if ($ligne['mode'] === 'taux')
                                                <label class="sr-only" for="cas-{{ $ligne['code'] }}">Nombre de cas</label>
                                                <input id="cas-{{ $ligne['code'] }}" type="number" min="0" inputmode="numeric" placeholder="cas" wire:model="indicateurs.{{ $ligne['code'] }}.nb_cas" class="champ w-20">
                                                <label class="sr-only" for="vel-{{ $ligne['code'] }}">Nombre de vêlages</label>
                                                <input id="vel-{{ $ligne['code'] }}" type="number" min="1" inputmode="numeric" placeholder="vêlages" wire:model="indicateurs.{{ $ligne['code'] }}.denominateur" class="champ w-24">
                                            @else
                                                <label class="sr-only" for="val-{{ $ligne['code'] }}">Valeur</label>
                                                <input id="val-{{ $ligne['code'] }}" type="number" step="any" inputmode="decimal" placeholder="{{ $ligne['unite'] }}" wire:model="indicateurs.{{ $ligne['code'] }}.valeur" class="champ w-24">
                                            @endif
                                            <button type="submit" class="bouton-secondaire">Enregistrer</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <div class="carte">
        <h3 class="font-semibold">Événements sanitaires déclarés</h3>
        @if ($evenements->isEmpty())
            <p class="mt-2 text-sm text-texte-doux">Aucun événement enregistré.</p>
        @else
            <ul class="mt-2 divide-y divide-filet text-sm">
                @foreach ($evenements as $e)
                    <li wire:key="evenement-{{ $e->id }}" class="py-2">
                        {{ $e->date_evenement->format('d/m/Y') }} · {{ $troubles[$e->type_evenement] ?? $e->type_evenement }} · {{ $especes[$e->espece_code] ?? $e->espece_code }}
                        @if ($e->nb_animaux) · {{ $e->nb_animaux }} animaux @endif
                        @if ($e->description) <span class="block text-texte-doux">{{ $e->description }}</span> @endif
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($peutSaisirBilan)
            <form wire:submit="declarerEvenement" class="mt-4 grid gap-3 sm:grid-cols-3">
                <div>
                    <label for="ev-type" class="etiquette">Trouble</label>
                    <select id="ev-type" wire:model="evenement.type_evenement" class="champ w-full">
                        <option value="">Choisir…</option>
                        @foreach ($troubles as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                    </select>
                    @error('evenement.type_evenement') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ev-espece" class="etiquette">Espèce</label>
                    <select id="ev-espece" wire:model="evenement.espece_code" class="champ w-full">
                        @foreach ($especes as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                    </select>
                </div>
                <div>
                    <label for="ev-date" class="etiquette">Date</label>
                    <input id="ev-date" type="date" wire:model="evenement.date_evenement" class="champ w-full">
                    @error('evenement.date_evenement') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="ev-nb" class="etiquette">Nombre d'animaux</label>
                    <input id="ev-nb" type="number" min="1" inputmode="numeric" wire:model="evenement.nb_animaux" class="champ w-full">
                </div>
                <div>
                    <label for="ev-gravite" class="etiquette">Gravité (1 à 3)</label>
                    <select id="ev-gravite" wire:model="evenement.gravite" class="champ w-full">
                        <option value="">Non précisée</option>
                        <option value="1">1</option><option value="2">2</option><option value="3">3</option>
                    </select>
                </div>
                <div>
                    <label for="ev-description" class="etiquette">Description</label>
                    <input id="ev-description" type="text" wire:model="evenement.description" class="champ w-full" maxlength="2000">
                </div>
                <div class="sm:col-span-3"><button type="submit" class="bouton-secondaire">Déclarer l'événement</button></div>
            </form>
        @endif
    </div>
</div>
