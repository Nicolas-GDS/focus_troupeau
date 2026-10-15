@props(['gds' => null, 'grand' => false])
{{--
    Logotype de la marque (kit graphique v1), en haut à droite de l'en-tête (§ 8.1).
    Variante co-brandée du GDS de l'élevage courant (« GDS 47 · Lot-et-Garonne ») ; sans GDS connu (pages de
    connexion), variante générale « Approche globale · GDS 24 · GDS 47 ». Le nom est vectorisé dans les fichiers :
    un changement de nom (point ouvert n° 12) ne change que ces fichiers et le jeton brand.nom, repris en texte alternatif.
--}}
@php($variante = $gds && file_exists(public_path('img/marque/focus-troupeau-horizontal-gds'.$gds->id.'.svg')) ? '-gds'.$gds->id : '')
<img src="{{ asset('img/marque/focus-troupeau-horizontal'.$variante.'.svg') }}" alt="{{ config('brand.nom') }}"
     {{ $attributes->merge(['class' => ($grand ? 'h-16' : 'h-10').' w-auto']) }}>
