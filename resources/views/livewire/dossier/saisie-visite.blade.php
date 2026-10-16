@php use App\Livewire\Dossier\Etats; @endphp
<div class="space-y-8">
    <section class="carte" aria-labelledby="titre-visite">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 id="titre-visite" class="text-lg font-semibold">{{ $libelleElevage }} ({{ $visite->elevage->code_pseudo }})</h2>
                <p class="mt-1 text-sm text-texte-doux">{{ $motif }} · campagne {{ $visite->campagne_code ?? 'non déterminée' }}</p>
            </div>
            @php($statut = Etats::statut($visite->statut))
            <x-pastille :etat="$statut['etat']" :libelle="$statut['libelle']" />
        </div>
        @unless ($modifiable)
            <p class="mt-3 text-sm text-texte-doux">Lecture seule : la visite n'accepte plus de saisie, ou votre rôle ne le permet pas.</p>
        @endunless
        <nav aria-label="Étapes de la visite" class="mt-4 flex flex-wrap gap-2 text-sm">
            <a href="#etape-collecter" class="lien">1. Collecter</a>
            <a href="#etape-analyser" class="lien">2. Analyser</a>
            <a href="#etape-mesurer" class="lien">3. Mesurer</a>
            <a href="{{ route('visites.interpretation', $visite->id) }}" class="lien">4. Croiser, 5. Agir, 6. Suivre : interprétation de visite</a>
        </nav>
    </section>

    @if (session('status'))
        <div class="rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</div>
    @endif

    <section id="etape-collecter" aria-labelledby="titre-collecter" class="space-y-4">
        <h2 id="titre-collecter" class="text-xl font-semibold">1. Collecter</h2>
        <livewire:dossier.saisie.collecte :visite-id="$visite->id" />
    </section>

    <section id="etape-analyser" aria-labelledby="titre-analyser" class="space-y-4">
        <h2 id="titre-analyser" class="text-xl font-semibold">2. Analyser</h2>
        <livewire:dossier.saisie.prelevements :visite-id="$visite->id" />
        <livewire:dossier.saisie.eau :visite-id="$visite->id" />
        <livewire:dossier.saisie.etiquettes :visite-id="$visite->id" />
    </section>

    <section id="etape-mesurer" aria-labelledby="titre-mesurer" class="space-y-4">
        <h2 id="titre-mesurer" class="text-xl font-semibold">3. Mesurer</h2>
        <livewire:dossier.saisie.mesures :visite-id="$visite->id" />
        <livewire:dossier.saisie.profils :visite-id="$visite->id" />
    </section>
</div>
