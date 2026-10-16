@php
    use App\Livewire\Dossier\Etats;
    use App\Services\Dossier\Affichage;
@endphp
<div class="carte">
    <h3 class="font-semibold">Mesures terrain</h3>
    <p class="mt-1 text-sm text-texte-doux">Cinq animaux par lot, le même jour ; chaque valeur est comparée au repère de son stade. Une mesure seule dit peu : c'est la combinaison qui parle.</p>
    @error('saisie') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror

    @if ($peutAjouter)
        <form wire:submit="ajouter" class="mt-3 grid gap-3 sm:grid-cols-3">
            <div>
                <label for="m-date" class="etiquette">Date</label>
                <input id="m-date" type="date" wire:model="date" class="champ w-full">
            </div>
            <div>
                <label for="m-espece" class="etiquette">Espèce</label>
                <select id="m-espece" wire:model="espece" class="champ w-full">
                    @foreach ($especes as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="m-stade" class="etiquette">Stade</label>
                <select id="m-stade" wire:model="stade" class="champ w-full">
                    <option value="">Non précisé</option>
                    @foreach ($stades as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="m-mesure" class="etiquette">Mesure</label>
                <select id="m-mesure" wire:model="mesure" class="champ w-full">
                    <option value="">Choisir…</option>
                    @foreach ($mesures as $m) <option value="{{ $m->code }}">{{ $m->libelle }} ({{ $m->unite }})</option> @endforeach
                </select>
                @error('mesure') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="m-animal" class="etiquette">N° de travail (facultatif)</label>
                <input id="m-animal" type="text" wire:model="animal" class="champ w-full" maxlength="40">
            </div>
            <div>
                <label for="m-valeur" class="etiquette">Valeur</label>
                <input id="m-valeur" type="number" step="any" inputmode="decimal" wire:model="valeur" class="champ w-full text-lg">
                @error('valeur') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-3"><button type="submit" class="bouton-principal w-full sm:w-auto">Ajouter la mesure</button></div>
        </form>
    @endif

    @forelse ($groupes as $groupe)
        <div wire:key="groupe-{{ $groupe['espece'] }}-{{ $groupe['stade'] }}" class="mt-4 rounded-md border border-filet p-3">
            <h4 class="font-medium">{{ $groupe['libelle'] }} · {{ $especes[$groupe['espece']] ?? 'espèce non précisée' }}</h4>
            <ul class="mt-2 space-y-3 text-sm">
                @foreach ($groupe['mesures'] as $m)
                    @php($etat = Etats::mesure($m['lecture']?->value))
                    <li>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-medium">{{ $m['libelle'] }}</span>
                            <span>médiane {{ Affichage::nombre($m['mediane'], $m['decimales']) }} {{ $m['unite'] === '-' ? '' : $m['unite'] }}</span>
                            <x-pastille :etat="$etat['etat']" :libelle="$etat['libelle']" />
                            <span class="text-texte-doux">repère : {{ $m['repere'] ?? 'non disponible' }}</span>
                        </div>
                        @include('livewire.dossier.saisie.partiels.source', ['source' => $m['source']])
                        <ul class="mt-1 flex flex-wrap gap-2 text-xs">
                            @foreach ($m['mesures'] as $ligne)
                                <li wire:key="mesure-{{ $ligne->id }}" class="rounded border border-filet px-2 py-1">
                                    {{ $ligne->identifiant_animal ? $ligne->identifiant_animal.' : ' : '' }}{{ Affichage::nombre((float) $ligne->valeur, $m['decimales']) }}
                                    · {{ Etats::mesure($ligne->lecture_code)['libelle'] }}
                                    @if ($ligne->statut === 'saisie' && $peutAjouter)
                                        <button type="button" wire:click="supprimer({{ $ligne->id }})" class="lien" aria-label="Supprimer la mesure {{ $ligne->identifiant_animal }}">retirer</button>
                                    @else
                                        · {{ Etats::statut($ligne->statut)['libelle'] }}
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    </li>
                @endforeach
            </ul>
            @if ($groupe['lectures'] !== [])
                <div class="mt-3">
                    <p class="text-sm font-medium">Lectures proposées (grille de lecture, à retenir ou écarter dans l'interprétation de visite)</p>
                    <ul class="mt-1 space-y-1 text-sm">
                        @foreach ($groupe['lectures'] as $lecture)
                            <li>{{ $lecture['libelle'] }} @include('livewire.dossier.saisie.partiels.source', ['source' => $lecture['source']])</li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @empty
        <p class="mt-3 text-sm text-texte-doux">Aucune mesure saisie.</p>
    @endforelse

    @if ($aValider)
        <button type="button" wire:click="validerTout" class="bouton-secondaire mt-3">Valider les mesures de la visite</button>
    @endif
</div>
