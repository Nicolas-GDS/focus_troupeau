@php use App\Livewire\Dossier\Etats; @endphp
<div class="space-y-4">
    @error('plan') <div class="rounded-md border border-etat-critique bg-etat-critique-fond p-3 text-sm" role="alert">{{ $message }}</div> @enderror

    @forelse ($priorites as $priorite => $libelle)
        @continue(! $parPriorite->has($priorite))
        <div class="carte">
            <h3 class="font-semibold">{{ $libelle }}</h3>
            <ul class="mt-2 space-y-3">
                @foreach ($parPriorite[$priorite] as $action)
                    <li wire:key="action-{{ $action->id }}" class="rounded-md border border-filet p-3 text-sm">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <p class="font-medium">{{ $action->titre }}</p>
                            <span class="flex flex-wrap items-center gap-2">
                                <span class="rounded bg-fond px-2 py-0.5 text-xs">{{ $responsables[$action->responsable] }}</span>
                                <x-pastille :etat="$action->statut === 'faite' ? 'couvert' : 'information'" :libelle="$action->statut === 'faite' ? 'Faite le '.$action->fait_le?->format('d/m/Y') : ($action->statut === 'abandonnee' ? 'Abandonnée' : 'À faire')" />
                            </span>
                        </div>
                        @if ($action->comment) <p class="mt-1"><span class="font-medium">Comment :</span> {{ $action->comment }}</p> @endif
                        <p class="mt-1"><span class="font-medium">Quand :</span>
                            {{ collect([$action->quand, $action->jalon ? ($jalons[$action->jalon] ?? $action->jalon) : null, $action->echeance?->format('d/m/Y')])->filter()->implode(' · ') ?: 'non précisé' }}</p>
                        @if ($action->responsable === 'veterinaire')
                            <p class="mt-1 text-xs">{{ $action->valide_le ? 'Validée par le vétérinaire conseil ('.($auteurs[$action->valide_par] ?? '').') le '.$action->valide_le->format('d/m/Y') : 'Action vétérinaire à valider par le vétérinaire conseil' }}</p>
                            @can('validerVeterinaire', $action) <button type="button" wire:click="validerVeterinaire({{ $action->id }})" class="lien text-sm">Valider l'action vétérinaire</button> @endcan
                        @endif
                        @if ($action->commentaire_eleveur) <p class="mt-1 text-xs">Commentaire de l'éleveur : {{ $action->commentaire_eleveur }}</p> @endif

                        @if ($action->controles->isNotEmpty())
                            <p class="mt-2 font-medium">Contrôles</p>
                            <ul class="mt-1 space-y-1">
                                @foreach ($action->controles as $c)
                                    @php($s = Etats::statut($c->statut === 'realise' ? 'publiee' : 'saisie'))
                                    <li wire:key="controle-{{ $c->id }}">
                                        {{ $c->mesure_code ? $c->mesure_code.' — ' : '' }}cible : {{ $c->cible }} · échéance {{ $c->echeance->format('d/m/Y') }}
                                        <x-pastille :etat="$c->statut === 'realise' ? 'couvert' : ($c->echeance->isPast() ? 'vigilance' : 'information')" :libelle="$c->statut === 'realise' ? 'Réalisé' : ($c->echeance->isPast() ? 'Échu' : 'Planifié')" />
                                        @if ($c->resultat) <span class="block text-xs">Résultat : {{ $c->resultat }}</span> @endif
                                        @can('delete', $c) <button type="button" wire:click="supprimerControle({{ $c->id }})" class="lien text-xs">retirer</button> @endcan
                                        @can('saisirResultat', $c)
                                            <form wire:submit="saisirResultat({{ $c->id }})" class="mt-1 flex flex-wrap items-end gap-2">
                                                <label class="sr-only" for="resultat-{{ $c->id }}">Résultat du contrôle</label>
                                                <input id="resultat-{{ $c->id }}" type="text" wire:model="resultats.{{ $c->id }}" class="champ" placeholder="résultat" maxlength="1000">
                                                <button type="submit" class="bouton-secondaire">Enregistrer le résultat</button>
                                            </form>
                                        @endcan
                                    </li>
                                @endforeach
                            </ul>
                        @endif

                        @can('marquerFaite', $action)
                            <form wire:submit="marquerFaite({{ $action->id }})" class="mt-2 flex flex-wrap items-end gap-2">
                                <label class="sr-only" for="commentaire-{{ $action->id }}">Commentaire</label>
                                <input id="commentaire-{{ $action->id }}" type="text" wire:model="commentaires.{{ $action->id }}" class="champ" placeholder="commentaire (facultatif)" maxlength="1000">
                                <button type="submit" class="bouton-principal">J'ai fait cette action</button>
                            </form>
                        @endcan

                        @if ($peutRediger)
                            <form wire:submit="ajouterControle({{ $action->id }})" class="mt-2 grid gap-2 sm:grid-cols-4">
                                <label class="sr-only" for="ctl-mesure-{{ $action->id }}">Mesure de contrôle</label>
                                <input id="ctl-mesure-{{ $action->id }}" type="text" wire:model="controles.{{ $action->id }}.mesure" class="champ" placeholder="mesure (pH urinaire…)">
                                <label class="sr-only" for="ctl-cible-{{ $action->id }}">Cible</label>
                                <input id="ctl-cible-{{ $action->id }}" type="text" wire:model="controles.{{ $action->id }}.cible" class="champ" placeholder="cible">
                                <label class="sr-only" for="ctl-echeance-{{ $action->id }}">Échéance</label>
                                <input id="ctl-echeance-{{ $action->id }}" type="date" wire:model="controles.{{ $action->id }}.echeance" class="champ">
                                <button type="submit" class="bouton-secondaire">Ajouter le contrôle</button>
                            </form>
                            @error("controles.{$action->id}.cible") <p class="text-sm text-etat-critique">{{ $message }}</p> @enderror
                            @error("controles.{$action->id}.echeance") <p class="text-sm text-etat-critique">{{ $message }}</p> @enderror
                            <button type="button" wire:click="supprimerAction({{ $action->id }})" wire:confirm="Retirer cette action et ses contrôles ?" class="lien mt-1 text-sm">Retirer l'action</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </div>
    @empty
    @endforelse

    @if ($parPriorite->isEmpty()) <p class="carte text-sm text-texte-doux">Aucune action au plan.</p> @endif

    @if ($peutRediger)
        <form wire:submit="ajouterAction" class="carte grid gap-3 sm:grid-cols-3">
            <h3 class="font-semibold sm:col-span-3">Ajouter une action</h3>
            <div>
                <label for="a-priorite" class="etiquette">Priorité</label>
                <select id="a-priorite" wire:model="action.priorite" class="champ w-full">
                    @foreach ($priorites as $n => $libelle) <option value="{{ $n }}">{{ $libelle }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="a-responsable" class="etiquette">Responsable</label>
                <select id="a-responsable" wire:model="action.responsable" class="champ w-full">
                    @foreach ($responsables as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                </select>
            </div>
            <div>
                <label for="a-jalon" class="etiquette">Jalon du calendrier</label>
                <select id="a-jalon" wire:model="action.jalon" class="champ w-full">
                    <option value="">Aucun</option>
                    @foreach ($jalons as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                </select>
            </div>
            <div class="sm:col-span-3">
                <label for="a-titre" class="etiquette">Action</label>
                <input id="a-titre" type="text" wire:model="action.titre" class="champ w-full" maxlength="300">
                @error('action.titre') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label for="a-comment" class="etiquette">Comment</label>
                <input id="a-comment" type="text" wire:model="action.comment" class="champ w-full" maxlength="2000">
            </div>
            <div>
                <label for="a-echeance" class="etiquette">Échéance (facultatif)</label>
                <input id="a-echeance" type="date" wire:model="action.echeance" class="champ w-full">
            </div>
            <div class="sm:col-span-3">
                <label for="a-quand" class="etiquette">Quand (en clair)</label>
                <input id="a-quand" type="text" wire:model="action.quand" class="champ w-full" placeholder="immédiat, toute l'année…" maxlength="300">
            </div>
            <div class="sm:col-span-3"><button type="submit" class="bouton-secondaire">Ajouter l'action</button></div>
        </form>
    @endif
</div>
