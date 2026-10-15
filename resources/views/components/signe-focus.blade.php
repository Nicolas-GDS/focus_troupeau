@props(['taille' => 32])
{{--
    Signe de mise au point (§ 8.1) : quatre équerres et un point central.
    PROVISOIRE : tracé d'après la description du cahier d'identité ; à remplacer par le fichier officiel du signe
    (couleurs exactes du logogramme non précisées dans les spécifications, couleurs nationales de la charte en attendant).
--}}
<svg {{ $attributes->merge(['class' => 'shrink-0']) }} width="{{ $taille }}" height="{{ $taille }}" viewBox="0 0 32 32" role="img" aria-label="{{ config('brand.nom') }}">
    <path d="M3 11V3h8" fill="none" stroke="#0083CA" stroke-width="3" stroke-linecap="square"/>
    <path d="M21 3h8v8" fill="none" stroke="#9D3293" stroke-width="3" stroke-linecap="square"/>
    <path d="M29 21v8h-8" fill="none" stroke="#54B948" stroke-width="3" stroke-linecap="square"/>
    <path d="M11 29H3v-8" fill="none" stroke="#939598" stroke-width="3" stroke-linecap="square"/>
    <circle cx="16" cy="16" r="3" fill="#231F20"/>
</svg>
