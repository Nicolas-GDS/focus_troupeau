@php
    $nombre = fn ($v) => $v === null ? '—' : \App\Services\Dossier\Affichage::nombre((float) $v);
@endphp
<div class="space-y-6">
    @include('livewire.admin.partiels.entete-referentiels', ['table' => $d->cle])

    @if ($formulaireOuvert)
        <section class="carte" aria-labelledby="titre-edition">
            <h2 id="titre-edition" class="text-lg font-semibold">{{ $editionId ? 'Modifier la ligne' : 'Nouvelle ligne' }} — {{ $d->libelle }}</h2>
            @error('saisie') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
            <form wire:submit="enregistrer" class="mt-3 grid gap-2 sm:grid-cols-3">
                @foreach ($d->colonnes as $nom => $c)
                    <div @class(['sm:col-span-3' => $nom === 'commentaire'])>
                        @if ($c['type'] === 'booleen')
                            <label class="mt-5 flex items-center gap-2 text-sm"><input type="checkbox" wire:model="saisie.{{ $nom }}"> {{ $c['libelle'] }}</label>
                        @else
                            <label for="c-{{ $nom }}" class="etiquette">{{ $c['libelle'] }}@if ($c['obligatoire'] ?? false) <span aria-hidden="true">*</span>@endif</label>
                            @if ($c['type'] === 'choix')
                                <select id="c-{{ $nom }}" wire:model="saisie.{{ $nom }}" class="champ w-full">
                                    <option value="">—</option>
                                    @if ($nom === 'statut_epistemique')
                                        @foreach ($statuts as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                                    @elseif ($nom === 'reference_id')
                                        @foreach ($references as $id => $cle) <option value="{{ $id }}">{{ $cle }}</option> @endforeach
                                    @else
                                        @foreach (is_array($c['choix']) ? $c['choix'] : ($choix[$nom] ?? []) as $v) <option value="{{ $v }}">{{ $v }}</option> @endforeach
                                    @endif
                                </select>
                            @else
                                <input id="c-{{ $nom }}" type="text" @if ($c['type'] === 'nombre') inputmode="decimal" @endif wire:model="saisie.{{ $nom }}" class="champ w-full">
                            @endif
                        @endif
                        @error('saisie.'.$nom) <span class="text-xs text-etat-critique" role="alert">{{ $message }}</span> @enderror
                    </div>
                @endforeach
                <div class="sm:col-span-3"><label for="c-decision" class="etiquette">Décision du comité technique</label><input id="c-decision" type="text" wire:model="decision" class="champ w-full">@error('decision') <span class="text-xs text-etat-critique" role="alert">{{ $message }}</span> @enderror</div>
                <div class="flex gap-2 sm:col-span-3">
                    <button type="submit" class="bouton-primaire">Enregistrer une nouvelle version</button>
                    @if ($editionId) <button type="button" wire:click="retirer({{ $editionId }})" wire:confirm="Retirer cette ligne ? Elle reste dans l'historique." class="bouton-secondaire">Retirer</button> @endif
                    <button type="button" wire:click="fermer" class="bouton-secondaire">Annuler</button>
                </div>
            </form>
        </section>
    @endif

    <section class="carte overflow-x-auto" tabindex="0" aria-labelledby="titre-lignes">
        <div class="flex flex-wrap items-end justify-between gap-2">
            <h2 id="titre-lignes" class="text-lg font-semibold">{{ $d->libelle }} ({{ $lignes->count() }} en vigueur, {{ $retirees }} dans l'historique)</h2>
            @if ($peutEcrire) <button type="button" wire:click="nouveau" class="bouton-secondaire">Nouvelle ligne</button> @endif
        </div>
        <div class="mt-2"><label for="t-filtre" class="etiquette">Filtrer</label><input id="t-filtre" type="search" wire:model.live.debounce.300ms="filtre" class="champ"></div>
        <table class="mt-3 w-full text-sm">
            <thead><tr class="text-left text-texte-doux">
                @foreach ($d->colonnes as $nom => $c) @continue(in_array($nom, ['commentaire', 'reference_id'], true)) <th scope="col" class="py-1 pr-3">{{ $c['libelle'] }}</th> @endforeach
                <th scope="col">v</th><th scope="col"><span class="sr-only">Actions</span></th>
            </tr></thead>
            <tbody>
            @foreach ($lignes as $l)
                <tr wire:key="l-{{ $l->id }}" class="border-t border-filet align-top">
                    @foreach ($d->colonnes as $nom => $c)
                        @continue(in_array($nom, ['commentaire', 'reference_id'], true))
                        <td class="py-1 pr-3">@if ($c['type'] === 'nombre') {{ $nombre($l->{$nom}) }} @elseif ($c['type'] === 'booleen') {{ $l->{$nom} ? 'oui' : 'à valider' }} @elseif ($nom === 'statut_epistemique') {{ $statuts[$l->{$nom}] ?? $l->{$nom} }} @else {{ $l->{$nom} ?? '—' }} @endif</td>
                    @endforeach
                    <td class="text-xs text-texte-doux">{{ $l->version_creation }}</td>
                    <td>@if ($peutEcrire) <button type="button" wire:click="modifier({{ $l->id }})" class="lien text-xs">modifier</button> @endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>
