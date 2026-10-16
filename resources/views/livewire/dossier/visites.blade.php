@php use App\Livewire\Dossier\Etats; @endphp
<div class="space-y-8">
    @if (! $elevage)
        <p class="carte text-texte-doux">Aucun élevage n'est accessible.</p>
    @else
        <section class="carte" aria-labelledby="titre-elevage">
            <h2 id="titre-elevage" class="text-lg font-semibold">{{ $libelleElevage }} ({{ $elevage->code_pseudo }})</h2>
            <p class="mt-1 text-sm text-texte-doux">Visites d'approche globale : une date, un technicien, un motif, des prélèvements, des mesures et des observations.</p>
        </section>

        @if ($peutCreer)
            <section class="carte" aria-labelledby="titre-nouvelle">
                <h2 id="titre-nouvelle" class="text-lg font-semibold">Nouvelle visite</h2>
                <form wire:submit="creer" class="mt-4 grid gap-4 sm:grid-cols-3">
                    <div>
                        <label for="date-visite" class="etiquette">Date de la visite</label>
                        <input id="date-visite" type="date" wire:model="dateVisite" class="champ w-full" required>
                        @error('dateVisite') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="motif" class="etiquette">Motif</label>
                        <select id="motif" wire:model="motif" class="champ w-full">
                            @foreach ($motifs as $code => $libelle)
                                <option value="{{ $code }}">{{ $libelle }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="motif-detail" class="etiquette">Précision (facultatif)</label>
                        <input id="motif-detail" type="text" wire:model="motifDetail" class="champ w-full" maxlength="500">
                    </div>
                    <div class="sm:col-span-3">
                        <button type="submit" class="bouton-principal">Créer la visite</button>
                    </div>
                </form>
            </section>
        @endif

        <section aria-labelledby="titre-liste">
            <h2 id="titre-liste" class="text-lg font-semibold">Visites</h2>
            @if ($visites->isEmpty())
                <p class="carte mt-3 text-texte-doux">Aucune visite à afficher.</p>
            @else
                <ul class="mt-3 space-y-3">
                    @foreach ($visites as $visite)
                        @php($statut = Etats::statut($visite->statut))
                        <li wire:key="visite-{{ $visite->id }}" class="carte flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <p class="font-medium">Visite du {{ $visite->date_visite->format('d/m/Y') }} — {{ $motifs[$visite->motif] ?? $visite->motif }}</p>
                                <p class="text-sm text-texte-doux">Campagne {{ $visite->campagne_code ?? 'non déterminée' }}@if ($visite->motif_detail) · {{ $visite->motif_detail }} @endif</p>
                            </div>
                            <div class="flex items-center gap-3">
                                <x-pastille :etat="$statut['etat']" :libelle="$statut['libelle']" />
                                <a href="{{ route('visites.saisie', $visite->id) }}" class="lien">{{ $peutSaisir($visite) ? 'Saisir' : 'Consulter' }}</a>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>
    @endif
</div>
