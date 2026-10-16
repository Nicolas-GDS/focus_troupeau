@php($id = 'pos-'.substr(md5($parametre.$population.json_encode($lots)), 0, 8))
{{-- Graphique de positionnement (x-positionnement, § 8.3) : styles en ligne, pour l'écran comme pour la fiche figée --}}
<figure style="margin: 2mm 0 4mm; break-inside: avoid;">
    <svg viewBox="0 0 {{ $largeur }} 96" width="100%" role="img" aria-labelledby="{{ $id }}-titre" style="max-width: 640px; display: block; font-family: var(--ft-police-titre, sans-serif);">
        <title id="{{ $id }}-titre">{{ $parametre }} : position de vos lots dans la population de référence</title>
        <rect x="{{ $x($reference->q1) }}" y="30" width="{{ max(1, $x($reference->q3) - $x($reference->q1)) }}" height="24" style="fill: var(--ft-entite-fond-clair, #E6F4FB);" />
        <line x1="{{ $marge }}" y1="42" x2="{{ $largeur - $marge }}" y2="42" style="stroke: var(--ft-gris-territoire, #939598); stroke-width: 1;" />
        <line x1="{{ $x($reference->mediane) }}" y1="26" x2="{{ $x($reference->mediane) }}" y2="58" style="stroke: var(--ft-quasi-noir, #231F20); stroke-width: 1.2; stroke-dasharray: 3 2;" />
        @php($quartiles = $etiquettesQuartiles())
        <text x="{{ $quartiles['q1']['x'] }}" y="22" text-anchor="{{ $quartiles['q1']['ancre'] }}" style="font-size: 11px; fill: var(--ft-texte-doux, #4A5568);">Q1 {{ $nombre($reference->q1) }}</text>
        <text x="{{ $quartiles['q3']['x'] }}" y="22" text-anchor="{{ $quartiles['q3']['ancre'] }}" style="font-size: 11px; fill: var(--ft-texte-doux, #4A5568);">Q3 {{ $nombre($reference->q3) }}</text>
        @foreach ($valeurs as $v)
            <circle cx="{{ $x($v) }}" cy="42" r="4" style="fill: #fff; stroke: var(--ft-gris-territoire, #939598); stroke-width: 1.2;" />
        @endforeach
        @foreach ($rappels as $r)
            <circle cx="{{ $x($r->valeur) }}" cy="42" r="5.5" style="fill: none; stroke: var(--ft-entite-primaire, #006BA0); stroke-width: 1.5; stroke-dasharray: 2 1.5;" />
        @endforeach
        @foreach ($lots as $lot)
            <circle cx="{{ $x($lot['valeur']) }}" cy="42" r="6.5" style="fill: var(--ft-entite-primaire, #006BA0); stroke: #fff; stroke-width: 1.5;" />
        @endforeach
        @foreach ($etiquettesLots() as $e)
            <text x="{{ $e['x'] }}" y="{{ $e['y'] }}" text-anchor="middle" style="font-size: 12px; font-weight: 700; fill: var(--ft-quasi-noir, #231F20);">{{ $e['texte'] }}</text>
        @endforeach
        @if ($borneLisible($minimum))
            <text x="{{ $marge }}" y="76" text-anchor="start" style="font-size: 10px; fill: var(--ft-texte-doux, #4A5568);">{{ $nombre($minimum) }}</text>
        @endif
        @if ($borneLisible($maximum))
            <text x="{{ $largeur - $marge }}" y="76" text-anchor="end" style="font-size: 10px; fill: var(--ft-texte-doux, #4A5568);">{{ $nombre($maximum) }}</text>
        @endif
    </svg>
    <figcaption style="font-size: 0.85em; color: var(--ft-texte-doux, #4A5568);">
        <strong style="color: var(--ft-texte, #231F20);">{{ $parametre }}{{ $uniteAffichee() !== '' ? ' ('.trim($uniteAffichee()).')' : '' }}</strong> — {{ $legende() }}
    </figcaption>
</figure>
