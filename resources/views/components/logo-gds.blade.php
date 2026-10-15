@props(['gds'])
{{--
    Logo du GDS de l'élevage (co-branding, § 8.1), en haut à gauche.
    Fichier attendu : public/img/logos/gds-{id}.jpg ; le logo contient déjà le nom du GDS (texte alternatif pour
    les lecteurs d'écran). Sans fichier (nouveau GDS), un cartouche texte aux couleurs de l'entité le remplace.
--}}
@php($fichier = 'img/logos/gds-'.$gds->id.'.jpg')
@if (file_exists(public_path($fichier)))
    <img src="{{ asset($fichier) }}" alt="{{ $gds->nom }}" {{ $attributes->merge(['class' => 'h-10 w-auto']) }}>
@else
    <span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
        <span class="inline-flex h-9 min-w-9 items-center justify-center rounded-md bg-entite px-1.5 font-titre text-sm font-bold text-white" aria-hidden="true">{{ $gds->code }}</span>
        <span class="font-titre text-sm font-semibold leading-tight text-entite-texte">{{ $gds->nom }}</span>
    </span>
@endif
