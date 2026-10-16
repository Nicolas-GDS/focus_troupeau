@php
    use App\Livewire\Dossier\Etats;
    use App\Services\Dossier\Affichage;
@endphp
<div class="carte">
    <h3 class="font-semibold">Minéraux et aliments distribués</h3>
    <p class="mt-1 text-sm text-texte-doux">Apport par animal et par jour = teneur par kg × dose distribuée. La comparaison aux besoins viendra avec les rations (lot L5).</p>
    @error('saisie') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror

    @foreach ($etiquettes as $item)
        @php($e = $item['etiquette'])
        @php($statut = Etats::statut($e->statut))
        <div wire:key="etiquette-{{ $e->id }}" class="mt-4 rounded-md border border-filet p-3">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <p class="font-medium">{{ $natures[$e->nature] ?? $e->nature }} — « {{ $e->libelle_eleveur }} »</p>
                <x-pastille :etat="$statut['etat']" :libelle="$statut['libelle']" />
            </div>
            <p class="mt-1 text-sm text-texte-doux">
                Dose : {{ $e->dose_g_par_animal_jour === null ? 'non déclarée' : Affichage::nombre((float) $e->dose_g_par_animal_jour).' g par animal et par jour' }}
                @if ($e->mode_distribution) · {{ $e->mode_distribution }} @endif
                @if ($e->date_limite) · date limite {{ $e->date_limite->format('d/m/Y') }} @endif
            </p>
            @if ($item['lecture']['perimee'])
                <p class="mt-1"><x-pastille etat="alerte" libelle="Date limite dépassée à la date de la visite" /></p>
            @endif
            @if ($item['lecture']['composition'] !== [])
                <table class="mt-2 min-w-full text-sm">
                    <caption class="sr-only">Composition par kg de produit et apport journalier</caption>
                    <thead class="text-left text-texte-doux"><tr><th scope="col" class="py-1 pr-4">Élément</th><th scope="col" class="py-1 pr-4">Teneur</th><th scope="col" class="py-1">Apport par animal et par jour</th></tr></thead>
                    <tbody class="divide-y divide-filet">
                        @foreach ($item['lecture']['composition'] as $c)
                            <tr>
                                <th scope="row" class="py-1 pr-4 text-left font-medium">{{ $c['libelle'] }}</th>
                                <td class="py-1 pr-4">{{ Affichage::nombre($c['teneur']) }} {{ $c['unite'] }}</td>
                                <td class="py-1">{{ $c['apport'] === null ? '—' : Affichage::nombre(round($c['apport']['valeur'], 3)).' '.$c['apport']['unite'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
            <div class="mt-2 flex flex-wrap gap-2">
                @if ($item['peutValider']) <button type="button" wire:click="valider({{ $e->id }})" class="bouton-secondaire">Valider l'étiquette</button> @endif
                @if ($item['peutSupprimer']) <button type="button" wire:click="supprimer({{ $e->id }})" wire:confirm="Supprimer cette étiquette ?" class="bouton-secondaire">Supprimer</button> @endif
            </div>
        </div>
    @endforeach

    @if ($etiquettes->isEmpty()) <p class="mt-2 text-sm text-texte-doux">Aucune étiquette saisie.</p> @endif

    @if ($peutAjouter)
        <form wire:submit="ajouter" class="mt-4 space-y-3">
            <h4 class="font-medium">Saisir une étiquette</h4>
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label for="et-nature" class="etiquette">Nature</label>
                    <select id="et-nature" wire:model="nature" class="champ w-full">
                        @foreach ($natures as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                    </select>
                </div>
                <div class="sm:col-span-2">
                    <label for="et-libelle" class="etiquette">Nom inscrit sur l'étiquette</label>
                    <input id="et-libelle" type="text" wire:model="libelle" class="champ w-full" maxlength="200">
                    @error('libelle') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="et-dose" class="etiquette">Dose (g par animal et par jour)</label>
                    <input id="et-dose" type="number" step="any" min="0" inputmode="decimal" wire:model="dose" class="champ w-full">
                </div>
                <div>
                    <label for="et-date" class="etiquette">Date limite</label>
                    <input id="et-date" type="date" wire:model="dateLimite" class="champ w-full">
                </div>
                <div>
                    <label for="et-mode" class="etiquette">Mode de distribution</label>
                    <input id="et-mode" type="text" wire:model="modeDistribution" class="champ w-full" placeholder="sur la ration, libre-service…" maxlength="200">
                </div>
            </div>
            <fieldset class="space-y-2">
                <legend class="etiquette">Composition par kg de produit</legend>
                @foreach ($composition as $i => $ligne)
                    <div wire:key="compo-{{ $i }}" class="flex flex-wrap items-end gap-2">
                        <label class="sr-only" for="compo-code-{{ $i }}">Élément</label>
                        <select id="compo-code-{{ $i }}" wire:model="composition.{{ $i }}.code" class="champ">
                            <option value="">Élément…</option>
                            @foreach ($elements as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach
                        </select>
                        <label class="sr-only" for="compo-valeur-{{ $i }}">Teneur</label>
                        <input id="compo-valeur-{{ $i }}" type="number" step="any" min="0" inputmode="decimal" wire:model="composition.{{ $i }}.valeur" class="champ w-28" placeholder="teneur">
                        <label class="sr-only" for="compo-unite-{{ $i }}">Unité</label>
                        <select id="compo-unite-{{ $i }}" wire:model="composition.{{ $i }}.unite" class="champ">
                            @foreach ($unites as $u) <option value="{{ $u }}">{{ $u }}</option> @endforeach
                        </select>
                        <button type="button" wire:click="retirerLigne({{ $i }})" class="lien text-sm">Retirer</button>
                    </div>
                @endforeach
                <button type="button" wire:click="ajouterLigne" class="lien text-sm">Ajouter un élément</button>
                @error('composition.*.code') <p class="text-sm text-etat-critique">{{ $message }}</p> @enderror
            </fieldset>
            <button type="submit" class="bouton-principal">Enregistrer l'étiquette</button>
        </form>
    @endif
</div>
