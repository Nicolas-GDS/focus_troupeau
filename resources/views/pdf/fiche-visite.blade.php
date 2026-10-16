@php
    use App\Livewire\Dossier\Etats;
    use App\Livewire\Dossier\Interpretation\EcranVisite;
    use App\Livewire\Dossier\Interpretation\PlanActions;
    use App\Livewire\Interpretation\InterpretationBulletin;
    use App\Services\Dossier\Affichage;
    use App\Services\Dossier\FicheVisiteService as Fiche;
    $marque = $ressources['images']['focus-troupeau-horizontal-gds'.($gds?->id ?? '')] ?? $ressources['images']['focus-troupeau-horizontal'];
    $logo = $ressources['images']['gds-'.($gds?->id ?? '')] ?? null;
    $niveau = InterpretationBulletin::niveau($interpretation->niveau_global);
    $responsables = ['eleveur' => 'Éleveur', 'technicien' => 'Technicien', 'veterinaire' => 'Vétérinaire'];
    $priorites = [1 => 'Priorité 1 — tout de suite', 2 => 'Priorité 2', 3 => 'Priorité 3'];
    $jalons = PlanActions::jalons();
    $familles = EcranVisite::familles();
    $piliers = EcranVisite::piliers();
    $natures = ['mineral' => 'Minéral', 'bolus' => 'Bolus', 'seau' => 'Seau à lécher', 'sel' => 'Sel', 'concentre' => 'Aliment concentré', 'autre' => 'Produit'];
@endphp
<!DOCTYPE html>
<html lang="fr" @if ($gds) data-gds="{{ $gds->id }}" @endif>
<head>
<meta charset="utf-8">
<title>{{ $interpretation->titre ?: 'Fiche de visite' }} — {{ config('brand.nom') }}</title>
@include('pdf.partiels.styles')
</head>
<body>
<header class="entete">
    @if ($logo) <img class="logo" src="{{ $logo }}" alt="{{ $gds?->nom }}"> @else <span class="titre-police">{{ $gds?->nom }}</span> @endif
    <img class="marque" src="{{ $marque }}" alt="{{ config('brand.nom') }}">
</header>

<p class="identite">{{ $libelleElevage }} ({{ $elevage->code_pseudo }}) · visite du {{ $visite->date_visite->format('d/m/Y') }}@if ($technicien) · {{ $technicien }}@endif · campagne {{ $visite->campagne_code ?? 'non déterminée' }}</p>
<h1>{{ $interpretation->titre ?: 'Votre visite d\'approche globale' }}</h1>

{{-- L'essentiel --}}
<h2>L'essentiel</h2>
<div class="encadre">
    <p>@include('pdf.partiels.etat', ['etat' => $niveau])</p>
    @if ($interpretation->synthese) <p style="white-space: pre-line">{{ $interpretation->synthese }}</p> @endif
</div>
@if ($messages->isNotEmpty())
    <ol class="messages">
        @foreach ($messages as $m)
            @php($n = InterpretationBulletin::niveau($m->gravite))
            <li>@include('pdf.partiels.etat', ['etat' => ['etat' => $n['etat'], 'libelle' => 'Gravité '.$m->gravite]]) @if ($m->pilier)<span class="doux">{{ $piliers[$m->pilier] ?? '' }} · </span>@endif{{ $m->texte }}</li>
        @endforeach
    </ol>
@endif

{{-- Les lots --}}
@if ($lots !== [])
    <h2>Vos fourrages</h2>
    <table>
        <thead><tr><th>Lot</th>@foreach (Fiche::PARAMETRES_LOTS as $code)<th>{{ $code === 'UFL_2018' ? 'UFL' : $code }}<br><span class="doux">{{ $unitesParametres[$code] ?? '' }}</span></th>@endforeach<th>Niveau</th><th>Utilisation conseillée</th></tr></thead>
        <tbody>
        @foreach ($lots as $lot)
            <tr>
                <td><strong>{{ $lot['prelevement']->typeFourrage?->libelle ?? $lot['prelevement']->type_fourrage_code }}</strong>@if ($lot['prelevement']->nom_lot)<br>{{ $lot['prelevement']->nom_lot }}@endif<br><span class="doux">{{ $lot['analyse']->num_echantillon_labo }}</span></td>
                @foreach (Fiche::PARAMETRES_LOTS as $code)
                    @php($v = $lot['valeurs'][$code] ?? null)
                    <td>@if ($v && $v['valeur'] !== null){{ $v['censure'] }}{{ Affichage::nombre($v['valeur'], $v['decimales']) }}@if ($v['classe'])<br>@include('pdf.partiels.etat', ['etat' => InterpretationBulletin::classe($v['classe'], $v['gravite'])])@endif @else — @endif</td>
                @endforeach
                <td>@if ($lot['interpretation'])@include('pdf.partiels.etat', ['etat' => InterpretationBulletin::niveau($lot['interpretation']->niveau_global)])@endif</td>
                <td>{{ $lot['interpretation']?->utilisation_conseillee ?? '—' }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h3>Matrice de vigilance minérale</h3>
    <table class="matrice">
        <thead><tr><th>Lot</th>@foreach (Fiche::PARAMETRES_MATRICE as $code)<th>{{ $code }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($lots as $lot)
            <tr>
                <td>{{ $lot['prelevement']->nom_lot ?: ($lot['prelevement']->typeFourrage?->libelle ?? $lot['analyse']->num_echantillon_labo) }}</td>
                @foreach (Fiche::PARAMETRES_MATRICE as $code)
                    @php($v = $lot['valeurs'][$code] ?? null)
                    <td>@if ($v && $v['valeur'] !== null){{ $v['censure'] }}{{ Affichage::nombre($v['valeur'], $v['decimales']) }}@if ($v['classe'] && ($v['gravite'] ?? 0) >= 1)<br>@include('pdf.partiels.etat', ['etat' => InterpretationBulletin::classe($v['classe'], $v['gravite'])])@endif @else <span class="doux">nd</span> @endif</td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
    <p class="doux">Valeurs par kg de matière sèche (g pour les minéraux majeurs, mg pour les oligo-éléments, mEq pour le BACA) ; nd : non dosé.</p>
@endif

{{-- Eau et minéraux --}}
@if ($eau !== [] || $etiquettes !== [])
    <h2>L'eau et les minéraux distribués</h2>
    @foreach ($eau as $item)
        <h3>Eau — {{ $item['analyse']->point_prelevement }} ({{ $item['analyse']->date_prelevement->format('d/m/Y') }})</h3>
        <table>
            <thead><tr><th>Paramètre</th><th>Valeur</th><th>Lecture</th><th>Repère</th></tr></thead>
            <tbody>@foreach ($item['lignes'] as $l)<tr><td>{{ $l['libelle'] }}</td><td>{{ $l['censure'] }}{{ Affichage::nombre($l['valeur']) }} {{ $l['unite'] === '-' ? '' : $l['unite'] }}</td><td>@include('pdf.partiels.etat', ['etat' => Etats::classe($l['classe'], $l['gravite']), 'aValider' => $l['a_valider']])</td><td>{{ $l['repere'] ?? 'repère non disponible' }}</td></tr>@endforeach</tbody>
        </table>
    @endforeach
    @foreach ($etiquettes as $item)
        @php($e = $item['etiquette'])
        <h3>{{ $natures[$e->nature] ?? $e->nature }} « {{ $e->libelle_eleveur }} »</h3>
        <p class="doux">Dose déclarée : {{ $e->dose_g_par_animal_jour === null ? 'non déclarée' : Affichage::nombre((float) $e->dose_g_par_animal_jour).' g par animal et par jour' }}@if ($e->date_limite) · date limite {{ $e->date_limite->format('d/m/Y') }}@endif</p>
        @if ($item['lecture']['perimee']) <p>@include('pdf.partiels.etat', ['etat' => ['etat' => 'alerte', 'libelle' => 'Date limite dépassée à la date de la visite']])</p> @endif
        @if ($item['lecture']['composition'] !== [])
            <table><thead><tr><th>Élément</th><th>Teneur par kg</th><th>Apport par animal et par jour</th></tr></thead>
            <tbody>@foreach ($item['lecture']['composition'] as $c)<tr><td>{{ $c['libelle'] }}</td><td>{{ Affichage::nombre($c['teneur']) }} {{ $c['unite'] }}</td><td>{{ $c['apport'] === null ? '—' : Affichage::nombre(round($c['apport']['valeur'], 3)).' '.$c['apport']['unite'] }}</td></tr>@endforeach</tbody></table>
        @endif
    @endforeach
@endif

{{-- Animaux --}}
@if ($mesures !== [] || $profils !== [])
    <h2>Vos animaux</h2>
    @foreach ($mesures as $g)
        <h3>Mesures terrain — {{ $g['libelle'] }}</h3>
        <table>
            <thead><tr><th>Mesure</th><th>Médiane (valeurs)</th><th>Lecture</th><th>Repère du stade</th></tr></thead>
            <tbody>@foreach ($g['mesures'] as $m)<tr><td>{{ $m['libelle'] }}</td><td>{{ Affichage::nombre($m['mediane'], $m['decimales']) }} {{ $m['unite'] === '-' ? '' : $m['unite'] }}<br><span class="doux">{{ collect($m['mesures'])->map(fn ($x) => Affichage::nombre((float) $x->valeur, $m['decimales']))->implode(' · ') }}</span></td><td>@include('pdf.partiels.etat', ['etat' => Etats::mesure($m['lecture']?->value), 'aValider' => $m['source']['a_valider'] ?? false])</td><td>{{ $m['repere'] ?? 'repère non disponible' }}</td></tr>@endforeach</tbody>
        </table>
        @foreach ($g['lectures'] as $lecture)<p><strong>Lecture :</strong> {{ $lecture['libelle'] }}@if ($lecture['source']['a_valider'])<span class="a-valider">repère à valider</span>@endif</p>@endforeach
    @endforeach
    @if ($profils !== [])
        <h3>Prises de sang</h3>
        <table>
            <thead><tr><th>Élément</th><th>Résultat</th><th>Lecture</th><th>Référence</th></tr></thead>
            <tbody>@foreach ($profils as $l)<tr><td>{{ ucfirst($l['profil']->parametre) }} <span class="doux">({{ $l['profil']->matrice }})</span></td><td>{{ Affichage::nombre((float) $l['profil']->valeur_moyenne) }} {{ $l['profil']->unite }}@if ($l['profil']->nb_animaux)<br><span class="doux">{{ $l['profil']->nb_animaux }} animaux</span>@endif</td><td>@include('pdf.partiels.etat', ['etat' => Etats::profil($l['lecture']), 'aValider' => $l['source']['a_valider'] ?? false])</td><td>{{ $l['repere'] ?? 'repère non disponible' }}</td></tr>@endforeach</tbody>
        </table>
    @endif
@endif

{{-- Bilan sanitaire --}}
@if ($bilan !== [])
    <h2>Le bilan sanitaire de la campagne</h2>
    <table>
        <thead><tr><th>Indicateur</th><th>Valeur</th><th>Situation</th><th>Seuils</th></tr></thead>
        <tbody>@foreach ($bilan as $l)<tr><td>{{ $l['libelle'] }}</td><td>{{ Affichage::nombre($l['valeur'], $l['mode'] === 'taux' ? 1 : 0) }} {{ $l['unite'] }}@if ($l['precedente'] !== null)<br><span class="doux">campagne précédente : {{ Affichage::nombre($l['precedente'], $l['mode'] === 'taux' ? 1 : 0) }}</span>@endif</td><td>@include('pdf.partiels.etat', ['etat' => Etats::bilan($l['classe']), 'aValider' => $l['source']['a_valider'] ?? false])</td><td>{{ $l['seuils'] ?? 'repère non disponible' }}</td></tr>@endforeach</tbody>
    </table>
@endif

{{-- Causes communes --}}
@if ($causes->isNotEmpty())
    <h2>Ce qui relie les troubles</h2>
    @foreach ($causes as $c)
        <div class="encadre">
            <p><strong>{{ $c->titre }}</strong> <span class="doux">({{ $familles[$c->famille_cause] ?? $c->famille_cause }})</span></p>
            @if ($c->preuves)<p><strong>Preuves :</strong> {{ $c->preuves }}</p>@endif
            @if ($c->consequences)<p><strong>Conséquences :</strong> {{ $c->consequences }}</p>@endif
        </div>
    @endforeach
@endif

{{-- Plan d'action --}}
@if ($actions->isNotEmpty())
    <h2>Votre plan d'action</h2>
    @foreach ($priorites as $priorite => $libelle)
        @continue(! $actions->has($priorite))
        <h3>{{ $libelle }}</h3>
        @foreach ($actions[$priorite] as $a)
            <div class="action">
                <p><strong>{{ $a->titre }}</strong><span class="responsable">{{ $responsables[$a->responsable] ?? $a->responsable }}</span></p>
                @if ($a->comment)<p><strong>Comment :</strong> {{ $a->comment }}</p>@endif
                <p><strong>Quand :</strong> {{ collect([$a->quand, $a->jalon ? ($jalons[$a->jalon] ?? $a->jalon) : null, $a->echeance?->format('d/m/Y')])->filter()->implode(' · ') ?: 'non précisé' }}</p>
                @foreach ($a->controles as $ctl)<p class="doux">Contrôle : {{ $ctl->mesure_code ? $ctl->mesure_code.' — ' : '' }}cible {{ $ctl->cible }}, avant le {{ $ctl->echeance->format('d/m/Y') }}</p>@endforeach
            </div>
        @endforeach
    @endforeach
@endif

{{-- Limites (§ 8.3, obligatoire sur toute page de résultat et tout PDF) --}}
<section class="limites">
    @if ($interpretation->limites)<p>{{ $interpretation->limites }}</p>@endif
    @if ($nonDoses !== [] && $lots !== [])<p>{{ $textes['fiche.limites.non_doses'] ?? '' }} {{ implode(', ', array_map(fn ($c) => $libellesParametres[$c] ?? $c, $nonDoses)) }}.</p>@endif
    <p>{{ $textes['fiche.limites.ne_remplace_pas'] ?? '' }}</p>
    <p>{{ $textes['fiche.limites.repere_a_valider'] ?? '' }}</p>
    <p>Grille d'interprétation version {{ $interpretation->version_grille }} · fiche version {{ $interpretation->version }}@if ($interpretation->publie_le), publiée le {{ $interpretation->publie_le->format('d/m/Y') }}@endif · générée le {{ $genereLe->format('d/m/Y à H:i') }} · {{ config('brand.nom') }} {{ config('app.version') }}</p>
    <p style="display:flex;justify-content:space-between;align-items:center"><span><span class="titre-police">{{ config('brand.nom') }}</span>, {{ config('brand.baseline') }} — <span class="signature">{{ config('brand.signature') }}</span></span><img src="{{ $ressources['images']['gds-france'] }}" alt="Réseau GDS France" style="height:8mm"></p>
</section>
</body>
</html>
