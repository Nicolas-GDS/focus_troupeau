@php
    $marque = $ressources['images']['focus-troupeau-horizontal-gds'.($gds?->id ?? '')] ?? $ressources['images']['focus-troupeau-horizontal'];
    $logo = $ressources['images']['gds-'.($gds?->id ?? '')] ?? null;
@endphp
<header class="entete">
    @if ($logo) <img class="logo" src="{{ $logo }}" alt="{{ $gds?->nom }}"> @else <span class="titre-police">{{ $gds?->nom }}</span> @endif
    <img class="marque" src="{{ $marque }}" alt="{{ config('brand.nom') }}">
</header>
