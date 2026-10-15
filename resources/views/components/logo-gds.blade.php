@props(['gds'])
{{--
    Logo du GDS de l'élevage (co-branding, § 8.1), en haut à gauche.
    PROVISOIRE : en attendant les fichiers des logos GDS 24 et GDS 47, un cartouche texte aux couleurs de l'entité.
--}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-md bg-entite px-1.5 font-titre text-sm font-bold text-white" aria-hidden="true">{{ $gds->code }}</span>
    <span class="font-titre text-sm font-semibold leading-tight text-entite-texte">{{ $gds->nom }}</span>
</span>
