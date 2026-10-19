@php
    use App\Services\Dossier\Affichage;
    $pct = fn (?float $v) => $v === null ? '—' : Affichage::nombre(round(100 * $v), 0).' %';
@endphp
<div class="space-y-6">
    <section class="carte" aria-label="Choix">
        <div class="flex flex-wrap items-end gap-3">
            @if (count($listeGds) > 1)
                <div><label for="p-gds" class="etiquette">GDS</label><select id="p-gds" wire:model.live="gds" class="champ">@foreach ($listeGds as $id => $nom) <option value="{{ $id }}">{{ $nom }}</option> @endforeach</select></div>
            @endif
            <div><label for="p-campagne" class="etiquette">Campagne</label><select id="p-campagne" wire:model.live="campagne" class="champ">@foreach ($campagnes as $c) <option value="{{ $c }}">{{ $c }}</option> @endforeach</select></div>
            <a href="{{ route('pilotage.indicateurs', ['campagne' => $campagne]) }}" class="lien text-sm">indicateurs de mes GDS (CSV)</a>
            @if ($gds !== '') <a href="{{ route('pilotage.references', ['gds' => $gds, 'campagne' => $campagne]) }}" class="lien text-sm">références agrégées (CSV)</a> @endif
        </div>
    </section>

    @if ($indicateurs)
        @php($i = $indicateurs)
        <section class="carte" aria-labelledby="titre-activite">
            <h2 id="titre-activite" class="text-lg font-semibold">Activité de la campagne {{ $campagne }}</h2>
            <dl class="mt-3 grid gap-3 sm:grid-cols-4">
                <div><dt class="text-sm text-texte-doux">Élevages avec prélèvement</dt><dd class="text-2xl font-semibold">{{ $i['elevages'] }}</dd></div>
                <div><dt class="text-sm text-texte-doux">Visites</dt><dd class="text-2xl font-semibold">{{ $i['visites'] }}</dd></div>
                <div><dt class="text-sm text-texte-doux">Bulletins</dt><dd class="text-2xl font-semibold">{{ $i['bulletins'] }}</dd></div>
                <div><dt class="text-sm text-texte-doux">Fiches publiées</dt><dd class="text-2xl font-semibold">{{ $i['fiches_publiees'] }}</dd></div>
            </dl>
        </section>

        <section class="carte" aria-labelledby="titre-qualite">
            <h2 id="titre-qualite" class="text-lg font-semibold">Délais et qualité</h2>
            <dl class="mt-3 grid gap-3 sm:grid-cols-3">
                <div><dt class="text-sm text-texte-doux">Délai médian prélèvement → fiche publiée</dt><dd class="text-2xl font-semibold">{{ $i['delai_median_jours'] === null ? '—' : Affichage::nombre(round($i['delai_median_jours']), 0).' jours' }}</dd>
                    <dd class="text-xs text-texte-doux">premier prélèvement de la visite → première publication de la fiche</dd></div>
                <div><dt class="text-sm text-texte-doux">Bulletins passés en anomalie</dt><dd class="text-2xl font-semibold">{{ $pct($i['part_anomalies']) }}</dd>
                    <dd class="text-xs text-texte-doux">contrôle de plausibilité (RC-2), anomalie levée comprise</dd></div>
                <div><dt class="text-sm text-texte-doux">Alertes automatiques retenues</dt><dd class="text-2xl font-semibold">{{ $pct($i['part_alertes_retenues']) }}</dd>
                    <dd class="text-xs text-texte-doux">sur {{ $i['alertes'] }} alertes de la grille (RC-1) ; le reste est écarté avec motif</dd></div>
            </dl>
        </section>

        <section class="carte" aria-labelledby="titre-veto">
            <h2 id="titre-veto" class="text-lg font-semibold">Orientations vétérinaires</h2>
            <p class="mt-2 text-sm">{{ $i['notes_liaison'] }} notes de liaison envoyées · {{ $i['interpretations_niveau3'] }} interprétations de niveau 3 (visa du vétérinaire conseil)</p>
        </section>

        <section class="carte overflow-x-auto" aria-labelledby="titre-references" tabindex="0">
            <h2 id="titre-references" class="text-lg font-semibold">Couverture des références</h2>
            <p class="mt-1 text-sm">{{ $i['types_couverts'] }} types de fourrage sur {{ $i['types_total'] }} atteignent n ≥ {{ $i['n_min'] }} (bulletins validés, consentement « références », hors exclusions ; RC-3).</p>
            @if ($i['couverture'] !== [])
                <table class="mt-2 w-full text-sm">
                    <thead><tr class="text-left text-texte-doux"><th scope="col" class="py-1 pr-3">Type de fourrage</th><th scope="col" class="pr-3 text-right">n</th><th scope="col">Référence</th></tr></thead>
                    <tbody>@foreach ($i['couverture'] as $c) <tr class="border-t border-filet"><td class="py-1 pr-3">{{ $c['type'] }}</td><td class="pr-3 text-right">{{ $c['n'] }}</td><td>@if ($c['couvert']) <x-pastille etat="couvert" libelle="Disponible" /> @else <x-pastille etat="information" libelle="Effectif insuffisant" /> @endif</td></tr> @endforeach</tbody>
                </table>
            @endif
        </section>
    @else
        <p class="carte text-texte-doux">Aucun GDS ou aucune campagne à afficher.</p>
    @endif
</div>
