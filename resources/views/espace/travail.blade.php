<x-layouts.espace titre="Mon travail">
    {{-- E18 (EF-M2-08) : chaque ligne mène à l'objet à traiter ; état rendu par la forme, le libellé et la couleur --}}
    {{-- § 8.3 : quatre tuiles au plus en tête d'écran — les files non vides, dans l'ordre de priorité --}}
    @php($tuiles = collect($files)->filter(fn ($file) => $file['total'] > 0)->take(4))
    @if ($tuiles->isEmpty())
        <p class="carte">Rien en attente : toutes les files de travail sont vides.</p>
    @endif
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Synthèse des files de travail">
        @foreach ($tuiles as $file)
            <a href="#file-{{ $file['cle'] }}" class="carte block hover:border-entite">
                <span class="block font-titre text-3xl font-semibold text-entite-texte">{{ $file['total'] }}</span>
                <span class="block text-sm text-texte-doux">{{ $file['titre'] }}</span>
            </a>
        @endforeach
    </div>

    <div class="mt-8 space-y-8">
        @foreach ($files as $file)
            <section id="file-{{ $file['cle'] }}" aria-labelledby="titre-{{ $file['cle'] }}">
                <h2 id="titre-{{ $file['cle'] }}" class="text-lg font-semibold">{{ $file['titre'] }} <span class="text-texte-doux">({{ $file['total'] }})</span></h2>

                @if ($file['lignes'] === [])
                    <p class="mt-2 text-sm text-texte-doux">Rien en attente.</p>
                @else
                    <ul class="mt-3 divide-y divide-filet rounded-lg border border-filet bg-surface">
                        @foreach ($file['lignes'] as $ligne)
                            <li class="flex flex-col gap-1 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                                <div>
                                    @if ($ligne['route'] !== '')
                                        <a class="lien font-medium" href="{{ route($ligne['route'], $ligne['parametres']) }}">{{ $ligne['libelle'] }}</a>
                                    @else
                                        <span class="font-medium">{{ $ligne['libelle'] }}</span>
                                    @endif
                                    <p class="text-sm text-texte-doux">{{ $ligne['detail'] }}</p>
                                </div>
                                <x-pastille :etat="$ligne['etat']" :libelle="match ($ligne['etat']) {
                                    'critique' => 'Prioritaire', 'alerte' => 'À reprendre', 'vigilance' => 'À traiter',
                                    'couvert' => 'Prêt', default => 'En attente',
                                }" />
                            </li>
                        @endforeach
                    </ul>
                    @if ($file['total'] > count($file['lignes']))
                        <p class="mt-2 text-sm text-texte-doux">{{ count($file['lignes']) }} premiers éléments sur {{ $file['total'] }}.</p>
                    @endif
                @endif
            </section>
        @endforeach
    </div>
</x-layouts.espace>
