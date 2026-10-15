<div class="space-y-8">
    {{-- Dépôt (EF-M9-01) --}}
    <section class="carte" aria-labelledby="titre-depot">
        <h2 id="titre-depot" class="text-lg font-semibold">Déposer des bulletins LANO</h2>
        <p class="mt-1 text-sm text-texte-doux">Fichiers PDF du laboratoire, 20 Mo au plus chacun. L'extraction, le rapprochement avec l'élevage
            et le contrôle de plausibilité se font automatiquement.</p>

        <form wire:submit="deposer" class="mt-4 grid gap-4 sm:grid-cols-[1fr_auto_auto] sm:items-end">
            <div>
                <label for="fichiers" class="etiquette">Bulletins (PDF)</label>
                <input id="fichiers" type="file" wire:model="fichiers" multiple accept="application/pdf,.pdf" class="champ">
                @error('fichiers') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                @error('fichiers.*') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
            </div>

            @if ($gdsDisponibles->count() > 1)
                <div>
                    <label for="gds" class="etiquette">GDS</label>
                    <select id="gds" wire:model="gdsId" class="champ">
                        <option value="">Choisir…</option>
                        @foreach ($gdsDisponibles as $gds)
                            <option value="{{ $gds->id }}">{{ $gds->nom }}</option>
                        @endforeach
                    </select>
                    @error('gdsId') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
            @endif

            <button type="submit" class="bouton-principal" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="deposer,fichiers">Déposer</span>
                <span wire:loading wire:target="deposer,fichiers">Envoi…</span>
            </button>
        </form>
    </section>

    {{-- Suivi des imports --}}
    <section aria-labelledby="titre-suivi">
        <h2 id="titre-suivi" class="text-lg font-semibold">Derniers dépôts</h2>

        @if ($imports->isEmpty())
            <p class="carte mt-3 text-texte-doux">Aucun bulletin déposé pour l'instant.</p>
        @else
            <div class="mt-3 overflow-x-auto rounded-lg border border-filet bg-surface">
                <table class="min-w-full text-sm">
                    <caption class="sr-only">Bulletins déposés, du plus récent au plus ancien</caption>
                    <thead class="bg-fond font-titre text-left text-texte-doux">
                        <tr>
                            <th scope="col" class="px-4 py-3">Bulletin</th>
                            <th scope="col" class="px-4 py-3">GDS</th>
                            <th scope="col" class="px-4 py-3">Déposé le</th>
                            <th scope="col" class="px-4 py-3">Statut</th>
                            <th scope="col" class="px-4 py-3">Suite à donner</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-filet">
                        @foreach ($imports as $import)
                            @php($statut = \App\Livewire\Import\ImportBulletins::libelleStatut($import->statut))
                            <tr wire:key="import-{{ $import->id }}" class="align-top">
                                <td class="px-4 py-3">
                                    <span class="font-medium">{{ $import->donnees_extraites['entete']['num_echantillon'] ?? '—' }}</span>
                                    <span class="block text-xs text-texte-doux">{{ $import->nom_fichier }}</span>
                                    @if ($import->chemin)
                                        <a class="lien text-xs" href="{{ URL::temporarySignedRoute('import.bulletins.pdf', now()->addMinutes(30), ['import' => $import->id]) }}">Ouvrir le PDF</a>
                                    @endif
                                </td>
                                <td class="px-4 py-3">{{ $import->gds_id }}</td>
                                <td class="px-4 py-3 whitespace-nowrap">{{ $import->cree_le->format('d/m/Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    <x-pastille :etat="$statut['etat']" :libelle="$statut['libelle']" />
                                    @if ($import->erreur)
                                        <p class="mt-1 whitespace-pre-line text-xs text-texte-doux">{{ $import->erreur }}</p>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    @if ($import->statut === 'a_rapprocher')
                                        <form wire:submit="rapprocher({{ $import->id }})" class="flex flex-wrap items-end gap-2">
                                            <div>
                                                <label for="elevage-{{ $import->id }}" class="etiquette">Élevage</label>
                                                <select id="elevage-{{ $import->id }}" wire:model="elevagesChoisis.{{ $import->id }}" class="champ">
                                                    <option value="">Choisir…</option>
                                                    @foreach ($elevagesParGds->get($import->gds_id, collect()) as $elevage)
                                                        <option value="{{ $elevage->id }}">{{ \App\Services\ElevageCourant::libelle($elevage) }} ({{ $elevage->code_pseudo }})</option>
                                                    @endforeach
                                                </select>
                                                @error('elevagesChoisis.'.$import->id) <p class="mt-1 text-xs text-etat-critique">{{ $message }}</p> @enderror
                                            </div>
                                            <button type="submit" class="bouton-secondaire">Rapprocher</button>
                                        </form>
                                    @elseif ($import->statut === 'anomalie' && $import->analyse?->statut === 'anomalie')
                                        <p class="text-xs">{{ $import->analyse->anomalie_detail }}</p>
                                        <div class="mt-2 flex flex-wrap items-end gap-2">
                                            <div class="min-w-64 flex-1">
                                                <label for="motif-{{ $import->analyse->id }}" class="etiquette">Motif (vérification du laboratoire, nouveau prélèvement…)</label>
                                                <input id="motif-{{ $import->analyse->id }}" type="text" wire:model="motifs.{{ $import->analyse->id }}" class="champ">
                                                @error('motifs.'.$import->analyse->id) <p class="mt-1 text-xs text-etat-critique">{{ $message }}</p> @enderror
                                            </div>
                                            <button type="button" wire:click="leverAnomalie({{ $import->analyse->id }})" class="bouton-secondaire">Lever l'anomalie</button>
                                            <button type="button" wire:click="annuler({{ $import->analyse->id }})" class="bouton-secondaire">Annuler le bulletin</button>
                                        </div>
                                    @elseif ($import->analyse)
                                        <span class="text-xs text-texte-doux">Analyse n° {{ $import->analyse->id }} — {{ $import->analyse->statut }}</span>
                                        {{-- E20 — Interprétation d'un bulletin contrôlé (EF-M2-02) --}}
                                        @if (in_array($import->analyse->statut, ['controle', 'valide'], true))
                                            <a class="lien block text-sm" href="{{ route('analyses.interpretation', $import->analyse->id) }}">{{ $import->analyse->statut === 'controle' ? 'Interpréter' : 'Voir l\'interprétation' }}</a>
                                        @endif
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
