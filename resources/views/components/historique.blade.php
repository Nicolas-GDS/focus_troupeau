@php($id = 'hist-'.substr(md5($parametre.json_encode(array_map(fn ($p) => [$p->campagne, $p->valeur], $points))), 0, 8))
{{-- Courbe pluriannuelle (RC-4) : pas de couleur d'état, une variation n'est ni bonne ni mauvaise --}}
<figure style="margin: 2mm 0 4mm; break-inside: avoid;">
    <svg viewBox="0 0 {{ $largeur }} {{ $hauteur }}" width="100%" role="img" aria-labelledby="{{ $id }}-titre" style="max-width: 640px; display: block; font-family: var(--ft-police-titre, sans-serif);">
        <title id="{{ $id }}-titre">{{ $parametre }} : évolution par campagne</title>
        <line x1="40" y1="122" x2="{{ $largeur - 40 }}" y2="122" style="stroke: var(--ft-gris-territoire, #939598); stroke-width: 1;" />
        @if (count($points) > 1)
            <polyline points="{{ $trace() }}" style="fill: none; stroke: var(--ft-entite-primaire, #006BA0); stroke-width: 2;" />
        @endif
        @foreach ($points as $i => $p)
            <circle cx="{{ $x($i) }}" cy="{{ $y($p->valeur) }}" r="5" style="fill: var(--ft-entite-primaire, #006BA0); stroke: #fff; stroke-width: 1.5;" />
            <text x="{{ $x($i) }}" y="{{ $y($p->valeur) - 10 }}" text-anchor="middle" style="font-size: 12px; font-weight: 700; fill: var(--ft-quasi-noir, #231F20);">{{ $nombre($p->valeur) }}</text>
            <text x="{{ $x($i) }}" y="140" text-anchor="middle" style="font-size: 11px; fill: var(--ft-texte-doux, #4A5568);">{{ $p->campagne }}</text>
        @endforeach
    </svg>
    <figcaption style="font-size: 0.85em; color: var(--ft-texte-doux, #4A5568);">
        <strong style="color: var(--ft-texte, #231F20);">{{ $parametre }}{{ $unite !== '-' ? ' ('.$unite.')' : '' }}</strong> — {{ $legende() }}
    </figcaption>
</figure>
