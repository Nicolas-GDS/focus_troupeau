@php($actions = ['cree' => ['Créé', 'couvert'], 'mis_a_jour' => ['Mis à jour', 'vigilance'], 'inchange' => ['Inchangé', 'information'], 'rejete' => ['Rejeté', 'critique']])
<div class="space-y-6">
    @if (session('status')) <p class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</p> @endif
    <section class="carte" aria-labelledby="titre-depot">
        <h2 id="titre-depot" class="text-lg font-semibold">Fichier d'export SAGEMA</h2>
        <p class="text-sm text-texte-doux">CSV séparé par « ; », avec l'en-tête : <code class="text-xs">{{ implode(';', $colonnes) }}</code>. Rapprochement par numéro EDE ; une valeur vide ne remplace pas une valeur existante ; aucune écriture vers SAGEMA.</p>
        @error('fichier') <p class="mt-2 text-sm text-etat-critique" role="alert">{{ $message }}</p> @enderror
        <form wire:submit="apercu" class="mt-3 flex flex-wrap items-end gap-3">
            <fieldset><legend class="etiquette">Fichier</legend>
                <div class="flex gap-3 text-sm"><label class="flex items-center gap-1"><input type="radio" value="elevages" wire:model.live="type"> Élevages adhérents</label>
                    <label class="flex items-center gap-1"><input type="radio" value="indicateurs" wire:model.live="type"> Bilan sanitaire</label></div></fieldset>
            @if ($type === 'elevages' && $listeGds->count() > 1)
                <div><label for="gds" class="etiquette">GDS des élevages créés</label><select id="gds" wire:model.live="gds" class="champ">@foreach ($listeGds as $id => $nom) <option value="{{ $id }}">{{ $nom }}</option> @endforeach</select></div>
            @endif
            <div><label for="fichier" class="etiquette">Fichier CSV</label><input id="fichier" type="file" accept=".csv,text/csv" wire:model="fichier" class="champ"></div>
            <button type="submit" class="bouton-secondaire">Voir ce qui sera importé</button>
        </form>
    </section>

    @if ($rapport)
        <section class="carte overflow-x-auto" aria-labelledby="titre-rapport">
            <h2 id="titre-rapport" class="text-lg font-semibold">{{ $importe ? 'Rapport d\'import' : 'Aperçu' }}</h2>
            <p class="mt-1 flex flex-wrap gap-2 text-sm">@foreach ($actions as $code => [$libelle, $etat]) <x-pastille :etat="$etat" :libelle="$libelle.' : '.$rapport['totaux'][$code]" /> @endforeach</p>
            <table class="mt-3 w-full text-sm">
                <thead><tr class="text-left text-texte-doux"><th scope="col" class="py-1 pr-3">Ligne</th><th scope="col" class="pr-3">EDE</th><th scope="col" class="pr-3">Élevage</th><th scope="col" class="pr-3">Résultat</th><th scope="col">Détail</th></tr></thead>
                <tbody>
                @foreach ($rapport['lignes'] as $l)
                    <tr wire:key="l-{{ $l['numero'] }}" class="border-t border-filet">
                        <td class="py-1 pr-3">{{ $l['numero'] }}</td><td class="pr-3">{{ $l['ede'] }}</td><td class="pr-3">{{ $l['elevage'] ?? '—' }}</td>
                        <td class="pr-3">{{ $actions[$l['action']][0] }}</td>
                        <td class="text-xs">{{ $l['motif'] }}{{ $l['changements'] !== [] && $l['action'] === 'mis_a_jour' ? ' '.implode(', ', $l['changements']) : '' }}{{ $type === 'indicateurs' ? ' '.implode(', ', $l['changements']) : '' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            @unless ($importe)
                <button type="button" wire:click="importer" wire:confirm="Importer ces lignes ? Les lignes rejetées sont ignorées." class="bouton-primaire mt-3">Importer</button>
            @endunless
        </section>
    @endif
</div>
