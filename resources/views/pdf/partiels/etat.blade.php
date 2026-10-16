{{-- État rendu par pictogramme, libellé et valeur, jamais la couleur seule (EF-GEN-03) ; « repère à valider » (§ 9.5) --}}
<span class="etat etat--{{ $etat['etat'] }}"><img src="{{ $ressources['images']['etat-'.$etat['etat']] }}" alt="" width="9" height="9">{{ $etat['libelle'] }}</span>@if (! empty($aValider))<span class="a-valider">repère à valider</span>@endif
