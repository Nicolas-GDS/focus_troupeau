{{-- Données AGDS (EF-M9-03, § 6.4) : identité de l'éleveur et composition du troupeau bovin lues dans SAGEMA à l'ouverture --}}
@php
    $sexes = ['F' => 'Femelles', 'M' => 'Mâles', '?' => 'Sexe non renseigné'];
    $date = $agds['date']->format('d/m/Y');
@endphp
<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'dossier.agds'])

    @switch($agds['etat'])
        @case(\App\Services\Agds\LectureAgdsService::INACTIF)
            <p class="carte text-texte-doux">La lecture des données AGDS n'est pas configurée sur cette instance.</p>
            @break
        @case(\App\Services\Agds\LectureAgdsService::SANS_EDE)
            <p class="carte text-texte-doux">Le numéro EDE de l'élevage n'est pas renseigné (ou mal formé) dans son identité : les données AGDS ne peuvent pas être lues.</p>
            @break
        @case(\App\Services\Agds\LectureAgdsService::INDISPONIBLE)
            <p class="carte" role="alert">Les données AGDS sont momentanément indisponibles (SAGEMA ne répond pas). Réessayez plus tard ; l'incident est journalisé.</p>
            @break
        @case(\App\Services\Agds\LectureAgdsService::INTROUVABLE)
            <p class="carte text-texte-doux">Aucun cheptel FR{{ $agds['ede'] }} n'est connu d'AGDS dans le département {{ $elevage->departement }}.</p>
            @break
        @default
            @php($i = $agds['identite'])
            <section class="carte mb-6" aria-labelledby="titre-identite">
                <h2 id="titre-identite" class="text-lg font-semibold">Éleveur — cheptel FR{{ $agds['ede'] }}</h2>
                @if ($i === null)
                    <p class="mt-2 text-sm text-texte-doux">Identité absente d'AGDS pour ce cheptel.</p>
                @else
                    @if ($i->cesse($agds['date']))
                        <p class="mt-2 text-sm font-medium">Activité cessée le {{ $i->dateCessation->format('d/m/Y') }}.</p>
                    @endif
                    <dl class="mt-2 grid gap-x-6 gap-y-2 text-sm sm:grid-cols-2">
                        <div><dt class="text-texte-doux">Exploitation</dt><dd class="font-medium">{{ trim(($i->civilite ?? '').' '.($i->nom ?? '')) ?: '—' }}</dd></div>
                        <div><dt class="text-texte-doux">Lieu</dt><dd>@foreach ($i->adresse as $l){{ $l }}<br>@endforeach{{ $i->codePostal }} {{ $i->commune }}</dd></div>
                        <div><dt class="text-texte-doux">Correspondance</dt><dd>{{ $i->nomCorrespondance }}<br>@foreach ($i->adresseCorrespondance as $l){{ $l }}<br>@endforeach{{ $i->codePostalCorrespondance }} {{ $i->communeCorrespondance }}</dd></div>
                        <div><dt class="text-texte-doux">Contact</dt><dd>
                            @if ($i->telephone) Téléphone {{ $i->telephone }}<br>@endif
                            @if ($i->mobile) Mobile {{ $i->mobile }}<br>@endif
                            @if ($i->courriel) <a href="mailto:{{ $i->courriel }}" class="lien">{{ $i->courriel }}</a>@endif
                            @if (! $i->telephone && ! $i->mobile && ! $i->courriel) — @endif
                        </dd></div>
                    </dl>
                @endif
            </section>

            @php($c = $agds['composition'])
            <section class="carte mb-6" aria-labelledby="titre-troupeau">
                <h2 id="titre-troupeau" class="text-lg font-semibold">Troupeau bovin présent au {{ $date }} : {{ $c['total'] }} bovin(s)</h2>
                @if ($c['total'] === 0)
                    <p class="mt-2 text-sm text-texte-doux">Aucun bovin présent dans AGDS.</p>
                @else
                    <div class="mt-2 overflow-x-auto" tabindex="0">
                        <table class="min-w-full text-sm">
                            <caption class="sr-only">Bovins présents par sexe et par tranche d'âge</caption>
                            <thead class="text-left text-texte-doux"><tr>
                                <th scope="col" class="py-1 pr-4">Sexe</th>
                                @foreach ($c['tranches'] as $t) <th scope="col" class="py-1 pr-4 text-right">{{ $t }}</th> @endforeach
                                <th scope="col" class="py-1 text-right">Total</th>
                            </tr></thead>
                            <tbody class="divide-y divide-filet">
                                @foreach ($sexes as $code => $libelle)
                                    @continue(! isset($c['effectifs'][$code]))
                                    <tr>
                                        <th scope="row" class="py-1 pr-4 text-left font-medium">{{ $libelle }}</th>
                                        @foreach ($c['tranches'] as $t) <td class="py-1 pr-4 text-right">{{ $c['effectifs'][$code][$t] ?? 0 }}</td> @endforeach
                                        <td class="py-1 text-right font-medium">{{ array_sum($c['effectifs'][$code]) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @if ($c['sans_date_naissance'] > 0)
                        <p class="mt-1 text-sm text-texte-doux">{{ $c['sans_date_naissance'] }} bovin(s) sans date de naissance, non répartis par âge.</p>
                    @endif
                    <p class="mt-3 text-sm"><span class="text-texte-doux">Races (code AGDS) :</span>
                        @foreach ($c['races'] as $race => $n){{ $race }} : {{ $n }}@if (! $loop->last) · @endif @endforeach
                    </p>
                    <details class="mt-3 text-sm">
                        <summary class="cursor-pointer text-texte-doux">Liste des bovins présents</summary>
                        <div class="mt-2 overflow-x-auto" tabindex="0">
                            <table class="min-w-full text-sm">
                                <caption class="sr-only">Bovins présents : numéro national, sexe, race, naissance, entrée</caption>
                                <thead class="text-left text-texte-doux"><tr><th scope="col" class="py-1 pr-4">Numéro national</th><th scope="col" class="py-1 pr-4">Sexe</th><th scope="col" class="py-1 pr-4">Race</th><th scope="col" class="py-1 pr-4">Naissance</th><th scope="col" class="py-1">Entrée</th></tr></thead>
                                <tbody class="divide-y divide-filet">
                                    @foreach ($agds['bovins'] as $b)
                                        <tr wire:key="bovin-{{ $loop->index }}">
                                            <td class="py-1 pr-4">{{ $b->numeroNational ?? 'non renseigné' }}</td>
                                            <td class="py-1 pr-4">{{ $b->sexe ?? '—' }}</td>
                                            <td class="py-1 pr-4">{{ $b->raceCode ?? '—' }}</td>
                                            <td class="py-1 pr-4">{{ $b->dateNaissance?->format('d/m/Y') ?? '—' }}</td>
                                            <td class="py-1">{{ $b->dateEntree?->format('d/m/Y') ?? '—' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </details>
                @endif
            </section>
            <p class="text-sm text-texte-doux">Source : AGDS, lu par SAGEMA le {{ $date }}. Lecture seule : ces données ne sont pas recopiées dans {{ config('brand.nom') }}.</p>
    @endswitch
</div>
