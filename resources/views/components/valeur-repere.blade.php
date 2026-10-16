@props(['valeur', 'etat', 'repere' => null, 'source' => null, 'sansRepere' => 'Repère non disponible'])
{{-- Valeur et repère (§ 8.3, EF-GEN-02, EF-GEN-03) : valeur, classe (pictogramme, libellé, couleur), repère, source
     et statut épistémique en dépliant, mention « repère à valider » --}}
<div {{ $attributes }}>
    <span class="whitespace-nowrap font-medium">{{ $valeur }}</span>
    <x-pastille :etat="$etat['etat']" :libelle="$etat['libelle']" class="ml-1" />
    <span class="block text-xs text-texte-doux">{{ $repere ? 'Repère : '.$repere : $sansRepere }}</span>
    @include('livewire.dossier.saisie.partiels.source', ['source' => $source])
</div>
