@php
    use App\Livewire\Dossier\Etats;
    use App\Livewire\Interpretation\InterpretationBulletin;
    use App\Services\Dossier\Affichage;
    use App\Services\Interpretation\InterpretationService;
    $marque = $ressources['images']['focus-troupeau-horizontal-gds'.($gds?->id ?? '')] ?? $ressources['images']['focus-troupeau-horizontal'];
    $logo = $ressources['images']['gds-'.($gds?->id ?? '')] ?? null;
@endphp
<!DOCTYPE html>
<html lang="fr" @if ($gds) data-gds="{{ $gds->id }}" @endif>
<head>
<meta charset="utf-8">
<title>Note de liaison — {{ $libelleElevage }}</title>
@include('pdf.partiels.styles')
</head>
<body>
<header class="entete">
    @if ($logo) <img class="logo" src="{{ $logo }}" alt="{{ $gds?->nom }}"> @else <span class="titre-police">{{ $gds?->nom }}</span> @endif
    <img class="marque" src="{{ $marque }}" alt="{{ config('brand.nom') }}">
</header>

<h1>Note de liaison au vétérinaire</h1>
<p class="identite">Adressée à {{ $veterinaire }} · le {{ ($note->envoyee_le ?? now())->format('d/m/Y') }} · par {{ $technicien ?? 'le technicien du GDS' }}</p>

<h2>Objet</h2>
<table>
    <tbody>
        <tr><th>Élevage</th><td>{{ $libelleElevage }} ({{ $elevage->code_pseudo }})@if ($ede) · EDE {{ $ede }}@endif</td></tr>
        <tr><th>Animaux concernés</th><td>{{ $note->lot_animaux ?? 'non précisés' }}</td></tr>
        <tr><th>Visite</th><td>{{ $visite->date_visite->format('d/m/Y') }} · @include('pdf.partiels.etat', ['etat' => InterpretationBulletin::niveau($interpretation->niveau_global)])</td></tr>
    </tbody>
</table>
@if ($interpretation->synthese)<div class="encadre"><p style="white-space: pre-line">{{ $interpretation->synthese }}</p></div>@endif

<h2>Ce qui a été observé</h2>
@if ($evenements->isEmpty() && $bilan === [])
    <p class="doux">Aucun trouble déclaré ni indicateur dégradé du bilan sanitaire.</p>
@else
    @if ($evenements->isNotEmpty())
        <table>
            <thead><tr><th>Trouble déclaré</th><th>Date</th><th>Animaux</th><th>Précisions</th></tr></thead>
            <tbody>@foreach ($evenements as $e)<tr><td>{{ $troubles[$e->type_evenement] ?? $e->type_evenement }}</td><td>{{ $e->date_evenement->format('d/m/Y') }}</td><td>{{ $e->nb_animaux ?? '—' }}</td><td>{{ $e->description }}</td></tr>@endforeach</tbody>
        </table>
    @endif
    @if ($bilan !== [])
        <table>
            <thead><tr><th>Bilan sanitaire</th><th>Valeur</th><th>Situation</th><th>Seuils</th></tr></thead>
            <tbody>@foreach ($bilan as $l)<tr><td>{{ $l['libelle'] }}</td><td>{{ Affichage::nombre($l['valeur'], $l['mode'] === 'taux' ? 1 : 0) }} {{ $l['unite'] }}</td><td>@include('pdf.partiels.etat', ['etat' => Etats::bilan($l['classe']), 'aValider' => $l['source']['a_valider'] ?? false])</td><td>{{ $l['seuils'] }}</td></tr>@endforeach</tbody>
        </table>
    @endif
@endif

<h2>Ce que montrent les fourrages</h2>
@if ($alertes === [])
    <p class="doux">Aucune alerte de gravité 2 ou 3 dans les fourrages de la visite.</p>
@else
    <table>
        <thead><tr><th>Bulletin</th><th>Alerte</th><th>Valeur</th><th>Seuil et source</th></tr></thead>
        <tbody>
        @foreach ($alertes as $l)
            @php($a = $l['alerte'])
            <tr>
                <td>{{ $l['bulletin'] }}</td>
                <td>@include('pdf.partiels.etat', ['etat' => InterpretationBulletin::classe($a->classe, $a->gravite)])<br>{{ $a->message }}</td>
                <td>{{ $a->parametre_code }} {{ $a->valeur === null ? '' : $a->censure.InterpretationService::nombre((float) $a->valeur) }}</td>
                <td>{{ $l['repere'] ?? '—' }}<br><span class="doux">{{ $l['source'] ?? 'source non renseignée' }}</span></td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif
@if ($nonDoses !== [])<p class="doux">Non dosés dans les fourrages : {{ implode(', ', $nonDoses) }}.</p>@endif

<h2>Question posée</h2>
<div class="encadre"><p style="white-space: pre-line">{{ $note->question }}</p></div>

<h2>Retour attendu</h2>
<p>{{ $retourAttendu }}</p>

<section class="limites">
    <p>Données transmises avec le consentement de l'éleveur (vétérinaire désigné). {{ config('brand.nom') }} {{ config('app.version') }} · grille v{{ $interpretation->version_grille }}.</p>
    <p style="display:flex;justify-content:space-between;align-items:center"><span><span class="titre-police">{{ config('brand.nom') }}</span>, {{ config('brand.baseline') }}</span><img src="{{ $ressources['images']['gds-france'] }}" alt="Réseau GDS France" style="height:8mm"></p>
</section>
</body>
</html>
