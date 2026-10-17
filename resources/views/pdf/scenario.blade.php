@php
    use App\Services\Dossier\Affichage;
    $t = fn ($v) => Affichage::nombre((float) $v, 1);
    $eur = fn ($v) => number_format(round((float) $v), 0, ',', ' ').' €';
    $pct = fn ($v) => Affichage::nombre(round(100 * (float) $v), 0).' %';
    $b = $evaluation['bilan'];
@endphp
<!DOCTYPE html>
<html lang="fr" @if ($gds) data-gds="{{ $gds->id }}" @endif>
<head>
<meta charset="utf-8">
<title>Scénario d'approvisionnement — {{ $libelleElevage }}</title>
@include('pdf.partiels.styles')
</head>
<body>
@include('pdf.partiels.entete-autonomie')

<h1>Scénario d'approvisionnement : {{ $scenario->libelle }}</h1>
<p class="identite">{{ $libelleElevage }} ({{ $elevage->code_pseudo }}) · inventaire du {{ $inventaire->date_inventaire->format('d/m/Y') }} · période du {{ $inventaire->date_debut->format('d/m/Y') }} au {{ $inventaire->date_fin->format('d/m/Y') }}
    · {{ $scenario->statut === 'retenu' ? 'scénario retenu le '.$scenario->retenu_le?->format('d/m/Y') : 'scénario à l\'étude' }}</p>

<h2>Effet sur le bilan</h2>
<table>
    <thead><tr><th></th><th>Sans levier</th><th>Avec le scénario</th></tr></thead>
    <tbody>
        <tr><td>Coût total</td><td>—</td><td>{{ $eur($evaluation['cout']) }}</td></tr>
        <tr><td>Couverture des besoins</td><td>{{ $pct($sansScenario->couverture) }}</td><td>{{ $pct($b->couverture) }}</td></tr>
        <tr><td>Bilan (sécurité déduite)</td><td>{{ $t($sansScenario->bilan) }} t MS</td><td>{{ $t($b->bilan_t_ms) }} t MS</td></tr>
        <tr><td>Autonomie</td><td>{{ $sansScenario->autonomieJours }} jours</td><td>{{ $b->autonomie_jours }} jours</td></tr>
        <tr><td>Rupture prévue</td><td>{{ $sansScenario->dateRupture?->format('d/m/Y') ?? 'aucune' }}</td><td>{{ $b->date_rupture?->format('d/m/Y') ?? 'aucune' }}@if ($b->date_rupture) <span class="doux">(du {{ $b->date_rupture_min?->format('d/m/Y') ?? '—' }} au {{ $b->date_rupture_max?->format('d/m/Y') ?? 'la fin de période' }})</span>@endif</td></tr>
        <tr><td>Lots d'animaux en déficit nutritionnel</td><td colspan="2">{{ $evaluation['lots_deficit'] }}</td></tr>
    </tbody>
</table>

<h2>Leviers</h2>
@if ($evaluation['lignes']->isEmpty())
    <p class="doux">Aucun levier.</p>
@else
    <table>
        <thead><tr><th>Levier</th><th>Quantité</th><th>Gain</th><th>Coût</th><th>Conséquence sur la ration</th></tr></thead>
        <tbody>
        @foreach ($evaluation['lignes'] as $l)
            <tr>
                <td>{{ $libelles[$l->levier_code] ?? $l->levier_code }}@if ($l->famille_code)<br><span class="doux">@if ($l->famille_source_code){{ $familles[$l->famille_source_code] ?? '' }} → @endif{{ $familles[$l->famille_code] ?? $l->famille_code }}</span>@endif</td>
                <td>{{ Affichage::nombre((float) $l->quantite, 2) }} {{ $l->unite }}</td>
                <td>{{ $t($l->gain_t_ms) }} t MS<br><span class="doux">au {{ $l->date_effet?->format('d/m/Y') ?? '—' }}</span></td>
                <td>{{ $eur($l->cout_eur) }}@if ($l->prix_unitaire !== null)<br><span class="doux">{{ $eur($l->prix_unitaire) }}/t MS</span>@endif</td>
                <td>{{ $l->commentaire }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

@if ($evaluation['vigilance'] !== [])
    <h2>Points de vigilance</h2>
    <ul>@foreach ($evaluation['vigilance'] as $v)<li>{{ $v }}</li>@endforeach</ul>
@endif

<h2>Valeurs par défaut des leviers et sources</h2>
<table>
    <tbody>@foreach ($sources as $p)<tr><td>{{ $p['libelle'] }}</td><td>{{ Affichage::nombre($p['valeur']) }} {{ $p['unite'] }}@if ($p['propre']) <span class="doux">(valeur de l'élevage)</span>@endif</td><td class="doux">{{ $p['source'] }}{{ $p['statut'] ? ' · '.$p['statut'] : '' }}</td></tr>@endforeach</tbody>
</table>

@include('pdf.partiels.limites-autonomie')
</body>
</html>
