@props(['position', 'ressources' => null])
{{-- Pastille de position dans la campagne (RC-3) : pictogramme, libellé et couleur (EF-GEN-03) ; $ressources : fiche figée --}}
@php($code = $position->value)
@if ($code === 'insuffisante')
    <span {{ $attributes->merge(['style' => 'font-size: 0.85em; color: var(--ft-texte-doux, #4A5568);']) }}>référence insuffisante</span>
@else
    <span {{ $attributes->merge(['style' => 'display: inline-flex; align-items: center; gap: 3px; font-size: 0.85em; font-weight: 600; white-space: nowrap; color: var(--ft-texte, #231F20);']) }}>
        <img src="{{ $ressources ? $ressources['images']['position-'.$code] : asset('img/puces/position-'.$code.'.svg') }}" alt="" width="10" height="10">{{ $position->libelle() }}
    </span>
@endif
