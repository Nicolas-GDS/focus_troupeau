@php
    use App\Services\Dossier\Affichage;
    $nombre = fn ($v) => Affichage::nombre((float) $v);
@endphp
<div class="space-y-6">
    @include('livewire.admin.partiels.entete-referentiels', ['table' => 'parametres'])

    @if ($cle)
        <section class="carte" aria-labelledby="titre-edition">
            <h2 id="titre-edition" class="text-lg font-semibold">Modifier {{ $cle }}</h2>
            <form wire:submit="enregistrer" class="mt-3 grid gap-2 sm:grid-cols-2">
                <div><label for="p-gds" class="etiquette">Portée</label>
                    <select id="p-gds" wire:model="saisie.gds" class="champ w-full">@if ($global) <option value="">Valeur nationale</option> @endif @foreach ($listeGds as $id => $nom) <option value="{{ $id }}">{{ $nom }}</option> @endforeach</select>
                    @error('saisie.gds') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div><label for="p-valeur" class="etiquette">Valeur</label><input id="p-valeur" type="text" inputmode="decimal" wire:model="saisie.valeur" class="champ w-full">@error('saisie.valeur') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div class="sm:col-span-2"><label for="p-source" class="etiquette">Source</label><input id="p-source" type="text" wire:model="saisie.source" class="champ w-full"></div>
                <div><label for="p-statut" class="etiquette">Statut épistémique</label><select id="p-statut" wire:model="saisie.statut" class="champ w-full">@foreach ($statuts as $code => $libelle) <option value="{{ $code }}">{{ $libelle }}</option> @endforeach</select></div>
                <div><label for="p-decision" class="etiquette">Décision du comité technique</label><input id="p-decision" type="text" wire:model="saisie.decision" class="champ w-full">@error('saisie.decision') <span class="text-xs text-etat-critique">{{ $message }}</span> @enderror</div>
                <div class="flex gap-2 sm:col-span-2"><button type="submit" class="bouton-primaire">Enregistrer une nouvelle version</button><button type="button" wire:click="$set('cle', null)" class="bouton-secondaire">Annuler</button></div>
            </form>
            @if ($historique->isNotEmpty())
                <h3 class="mt-4 font-semibold">Historique daté</h3>
                <ul class="mt-1 space-y-1 text-xs">@foreach ($historique as $h) <li>{{ $h->gds_id ? 'GDS '.$h->gds_id : 'National' }} : {{ $nombre($h->valeur) }} {{ $h->unite }} — du {{ \Illuminate\Support\Carbon::parse($h->valide_du)->format('d/m/Y') }}{{ $h->valide_au ? ' au '.\Illuminate\Support\Carbon::parse($h->valide_au)->format('d/m/Y') : '' }} · {{ $h->source }}</li> @endforeach</ul>
            @endif
        </section>
    @endif

    <section class="carte overflow-x-auto" aria-labelledby="titre-parametres" tabindex="0">
        <h2 id="titre-parametres" class="text-lg font-semibold">Paramètres par défaut</h2>
        <div class="mt-2"><label for="p-filtre" class="etiquette">Filtrer (clé ou libellé)</label><input id="p-filtre" type="search" wire:model.live.debounce.300ms="filtre" class="champ"></div>
        <table class="mt-3 w-full text-sm">
            <thead><tr class="text-left text-texte-doux"><th scope="col" class="py-1 pr-3">Paramètre</th><th scope="col" class="pr-3">Valeur</th><th scope="col" class="pr-3">Source</th><th scope="col"></th></tr></thead>
            <tbody>
            @foreach ($lignes as $cleLigne => $versions)
                <tr wire:key="p-{{ $cleLigne }}" class="border-t border-filet align-top">
                    <td class="py-1 pr-3">{{ $versions->first()->libelle }}<span class="block text-xs text-texte-doux">{{ $cleLigne }}</span></td>
                    <td class="pr-3">@foreach ($versions as $v) <span class="block">{{ $v->gds_id ? 'GDS '.$v->gds_id.' : ' : '' }}{{ $nombre($v->valeur) }} {{ $v->unite }}</span> @endforeach</td>
                    <td class="pr-3 text-xs text-texte-doux">{{ $versions->first()->source }} · {{ $statuts[$versions->first()->statut_epistemique] ?? $versions->first()->statut_epistemique }}</td>
                    <td>@if ($peutEcrire) <button type="button" wire:click="choisir('{{ $cleLigne }}')" class="lien text-xs">modifier</button> @endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
</div>
