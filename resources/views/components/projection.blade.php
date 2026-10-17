@php($id = 'proj-'.substr(md5(json_encode($points)), 0, 8))
{{-- Projection des stocks (EF-M6-02) : styles en ligne, pour l'écran comme pour le PDF ; aucune couleur d'état --}}
<figure style="margin: 2mm 0 4mm; break-inside: avoid;">
    <svg viewBox="0 0 {{ $largeur }} {{ $hauteur }}" width="100%" role="img" aria-labelledby="{{ $id }}-titre" style="max-width: 720px; display: block; font-family: var(--ft-police-titre, sans-serif);">
        <title id="{{ $id }}-titre">Stock projeté et besoin cumulé sur la période (t MS)</title>
        @foreach ($graduations() as $g)
            <line x1="{{ $gauche }}" y1="{{ $y($g) }}" x2="{{ $largeur - $droite }}" y2="{{ $y($g) }}" style="stroke: var(--ft-filet, #DCE4EC); stroke-width: 1;" />
            <text x="{{ $gauche - 6 }}" y="{{ $y($g) + 4 }}" text-anchor="end" style="font-size: 10px; fill: var(--ft-texte-doux, #4A5568);">{{ $nombre($g) }}</text>
        @endforeach
        @if (count($points) > 1)
            <polygon points="{{ $bande() }}" style="fill: var(--ft-entite-fond-clair, #E6F4FB); stroke: none;" />
            <line x1="{{ $gauche }}" y1="{{ $y($securite) }}" x2="{{ $largeur - $droite }}" y2="{{ $y($securite) }}" style="stroke: var(--ft-gris-territoire, #939598); stroke-width: 1; stroke-dasharray: 4 4;" />
            <text x="{{ $largeur - $droite }}" y="{{ $y($securite) - 4 }}" text-anchor="end" style="font-size: 10px; fill: var(--ft-texte-doux, #4A5568);">Sécurité {{ $nombre($securite) }}</text>
            @foreach (array_keys($familles) as $k => $code)
                <polyline points="{{ $trace(fn ($p) => $p['familles'][$code] ?? 0.0) }}" style="fill: none; stroke: var(--ft-bleu-gds, #0083CA); stroke-width: 1.3; stroke-dasharray: {{ $trait($k) }};" />
            @endforeach
            <polyline points="{{ $trace(fn ($p) => $p['besoin']) }}" style="fill: none; stroke: var(--ft-quasi-noir, #231F20); stroke-width: 1.6; stroke-dasharray: 7 4;" />
            <polyline points="{{ $trace(fn ($p) => $p['stock']) }}" style="fill: none; stroke: var(--ft-entite-primaire, #006BA0); stroke-width: 2.5;" />
        @endif
        <line x1="{{ $gauche }}" y1="{{ $bas }}" x2="{{ $largeur - $droite }}" y2="{{ $bas }}" style="stroke: var(--ft-gris-territoire, #939598); stroke-width: 1;" />
        @foreach ($points as $i => $p)
            <text x="{{ $x($i) }}" y="{{ $bas + 16 }}" text-anchor="middle" style="font-size: 10px; fill: var(--ft-texte-doux, #4A5568);">{{ $p['libelle'] }}</text>
        @endforeach
    </svg>
    <ul style="display: flex; flex-wrap: wrap; gap: 4px 16px; margin: 2mm 0; padding: 0; list-style: none; font-size: 0.85em;">
        <li><svg width="28" height="8" aria-hidden="true"><line x1="0" y1="4" x2="28" y2="4" style="stroke: var(--ft-entite-primaire, #006BA0); stroke-width: 2.5;" /></svg> Stock total projeté</li>
        <li><svg width="28" height="10" aria-hidden="true"><rect x="0" y="0" width="28" height="10" style="fill: var(--ft-entite-fond-clair, #E6F4FB);" /></svg> Fourchette d'incertitude</li>
        <li><svg width="28" height="8" aria-hidden="true"><line x1="0" y1="4" x2="28" y2="4" style="stroke: var(--ft-quasi-noir, #231F20); stroke-width: 1.6; stroke-dasharray: 7 4;" /></svg> Besoin cumulé</li>
        @foreach (array_values($familles) as $k => $libelle)
            <li><svg width="28" height="8" aria-hidden="true"><line x1="0" y1="4" x2="28" y2="4" style="stroke: var(--ft-bleu-gds, #0083CA); stroke-width: 1.3; stroke-dasharray: {{ $trait($k) }};" /></svg> {{ $libelle }}</li>
        @endforeach
    </ul>
    <figcaption style="font-size: 0.85em; color: var(--ft-texte-doux, #4A5568);">{{ $legende() }}</figcaption>
</figure>
