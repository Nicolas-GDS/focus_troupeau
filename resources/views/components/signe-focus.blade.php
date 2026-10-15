@props(['taille' => 32, 'variante' => null])
{{-- Signe de mise au point seul (kit graphique v1) : quadrichrome sur fond clair ; « sombre » ou « mono » sinon (§ 8.1). --}}
<img src="{{ asset('img/marque/signe-focus'.($variante ? '-'.$variante : '').'.svg') }}" alt="{{ config('brand.nom') }}"
     width="{{ $taille }}" height="{{ $taille }}" {{ $attributes }}>
