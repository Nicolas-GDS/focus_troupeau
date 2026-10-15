@props(['etat' => 'information', 'libelle'])
{{-- Pastille d'état du kit graphique : forme, libellé et couleur, jamais la couleur seule (EF-GEN-03). --}}
<span {{ $attributes->merge(['class' => 'ft-etat ft-etat--'.$etat]) }}>
    <img src="{{ asset('img/puces/etat-'.$etat.'.svg') }}" alt="" width="12" height="12">
    {{ $libelle }}
</span>
