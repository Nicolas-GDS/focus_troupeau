@php
    use App\Services\Dossier\Affichage;
    $t = fn ($v) => Affichage::nombre((float) $v, 1);
    $pct = fn ($v) => Affichage::nombre(round(100 * (float) $v), 0).' %';
    $date = fn ($d) => $d?->format('d/m/Y') ?? '—';
    $i = $ecran['indicateurs'];
    $c = $ecran['calcul']['centrale'];
@endphp
<!DOCTYPE html>
<html lang="fr" @if ($gds) data-gds="{{ $gds->id }}" @endif>
<head>
<meta charset="utf-8">
<title>Bilan d'autonomie fourragère — {{ $libelleElevage }}</title>
@include('pdf.partiels.styles')
</head>
<body>
@include('pdf.partiels.entete-autonomie')

<h1>Bilan d'autonomie fourragère</h1>
<p class="identite">{{ $libelleElevage }} ({{ $elevage->code_pseudo }}) · inventaire du {{ $inventaire->date_inventaire->format('d/m/Y') }} · période du {{ $inventaire->date_debut->format('d/m/Y') }} au {{ $inventaire->date_fin->format('d/m/Y') }} ({{ $c->jours }} jours)</p>

<h2>Bilan de la période</h2>
<table>
    <thead><tr><th>Indicateur</th><th>Valeur</th><th>Fourchette (incertitude des stocks)</th></tr></thead>
    <tbody>
        <tr><td>Couverture des besoins</td><td>{{ $pct($i['couverture']['valeur']) }}</td><td>{{ $pct($i['couverture']['basse']) }} à {{ $pct($i['couverture']['haute']) }}</td></tr>
        <tr><td>Autonomie</td><td>{{ $i['autonomie']['jours'] }} jours</td><td>{{ $i['autonomie']['basse'] }} à {{ $i['autonomie']['haute'] }} jours</td></tr>
        <tr><td>Rupture prévue</td><td>{{ $ecran['rupture']['date'] ? $date($ecran['rupture']['date']) : 'aucune sur la période' }}</td><td>@if ($ecran['rupture']['date']) du {{ $date($ecran['rupture']['min']) }} au {{ $ecran['rupture']['max'] ? $date($ecran['rupture']['max']) : 'la fin de période' }} @endif</td></tr>
        <tr><td>{{ $i['bilan']['valeur'] < 0 ? 'Déficit' : 'Excédent' }} (sécurité déduite)</td><td>{{ $t($i['bilan']['valeur']) }} t MS</td><td>{{ $t($i['bilan']['basse']) }} à {{ $t($i['bilan']['haute']) }} t MS</td></tr>
        <tr><td>Stock disponible (achats et ventes compris)</td><td>{{ $t($i['stock']['valeur']) }} t MS</td><td>{{ $t($i['stock']['basse']) }} à {{ $t($i['stock']['haute']) }} t MS</td></tr>
        <tr><td>Besoin prélevé sur les stocks</td><td>{{ $t($c->besoinStock) }} t MS</td><td></td></tr>
        <tr><td>Stock de sécurité ({{ $pct($inventaire->marge_securite) }})</td><td>{{ $t($c->securite) }} t MS</td><td>@if ($ecran['entame_securite']) entamé à partir du {{ $date($ecran['entame_securite']) }} @endif</td></tr>
    </tbody>
</table>
@if ($ecran['repere'])
    <div class="encadre"><p>@include('pdf.partiels.etat', ['etat' => ['etat' => $i['couverture']['valeur'] >= $ecran['repere']['valeur'] ? 'couvert' : 'alerte', 'libelle' => $i['couverture']['valeur'] >= $ecran['repere']['valeur'] ? 'Au-dessus du repère' : 'En dessous du repère']]) {{ $ecran['repere']['texte'] }}</p>
    <p class="doux">Repère : {{ $pct($ecran['repere']['valeur']) }} de couverture — {{ $ecran['repere']['source'] }}{{ $ecran['repere']['statut'] ? ' · '.$ecran['repere']['statut'] : '' }}</p></div>
@endif

<h2>Projection des stocks</h2>
<x-projection :points="$ecran['graphique']['points']" :familles="$ecran['graphique']['familles']" :securite="$ecran['graphique']['securite']" />

<h2>Par famille de fourrage</h2>
<table>
    <thead><tr><th>Famille</th><th>Stock (mouvements compris)</th><th>Besoin</th><th>Sécurité</th><th>Rupture</th></tr></thead>
    <tbody>@foreach ($ecran['ruptures_famille'] as $f)<tr><td>{{ $f['libelle'] }}</td><td>{{ $t($f['stock']) }}</td><td>{{ $t($f['besoin']) }}</td><td>{{ $t($f['securite']) }}</td><td>{{ $f['rupture'] ? 'le '.$date($f['rupture']) : 'aucune' }}</td></tr>@endforeach</tbody>
</table>
@if ($ecran['lots'] !== [])
    <p class="doux">@foreach ($ecran['lots'] as $lot){{ $lot['libelle'] }} : {{ $lot['epuisement'] ? 'épuisé le '.$date($lot['epuisement']) : 'non épuisé' }}{{ $loop->last ? '.' : ' ; ' }}@endforeach</p>
@endif

<h2>Projection mois par mois</h2>
<table>
    <thead><tr><th>Mois</th><th>Famille</th><th>Stock début</th><th>Consommation</th><th>Mouvements</th><th>Stock fin</th></tr></thead>
    <tbody>
    @foreach ($ecran['mensuel'] as $m)
        @foreach ($m['familles'] as $code => $f)
            @continue(! isset($ecran['familles'][$code]))
            <tr><td>{{ $loop->first ? $m['libelle'] : '' }}</td><td>{{ $ecran['familles'][$code] }}</td><td>{{ $t($f['stock_debut']) }}</td><td>{{ $t($f['conso']) }}</td><td>{{ $t($f['mvts']) }}</td><td>{{ $t($f['stock_fin']) }}</td></tr>
        @endforeach
        <tr><td></td><th>Total</th><th>{{ $t($m['stock_debut']) }}</th><th>{{ $t($m['conso']) }}</th><th>{{ $t($m['mvts']) }}</th><th>{{ $t($m['stock_fin']) }}</th></tr>
    @endforeach
    </tbody>
</table>
<p class="doux">t MS ; calcul journalier, affichage mensuel (RC-11).</p>

<h2>Hypothèses et sources</h2>
<table>
    <tbody>@foreach ($ecran['hypotheses'] as $h)<tr><td>{{ $h['libelle'] }}</td><td>{{ $h['valeur'] }}</td><td class="doux">{{ $h['source'] }}{{ $h['statut'] ? ' · '.$h['statut'] : '' }}</td></tr>@endforeach</tbody>
</table>
<p class="doux">{{ $ecran['reperes_periode'] }}</p>

@include('pdf.partiels.limites-autonomie')
</body>
</html>
