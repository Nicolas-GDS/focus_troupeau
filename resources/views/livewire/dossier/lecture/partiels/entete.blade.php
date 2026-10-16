{{-- En-tête du dossier (E03 à E11) : élevage et volets ($courant : route du volet affiché, stable lors des mises à jour Livewire) ; un agent est prévenu que l'éleveur ne lit que le publié --}}
@php
    $volets = [
        'visites' => 'Visites', 'dossier.fourrages' => 'Fourrages', 'dossier.matrice' => 'Matrice minérale', 'dossier.eau' => 'Eau',
        'dossier.animaux' => 'Animaux', 'dossier.aliments' => 'Minéraux et aliments', 'dossier.sanitaire' => 'Bilan sanitaire',
        'dossier.historique' => 'Historique', 'dossier.plan' => 'Plan d\'action',
    ];
@endphp
<div class="mb-6 space-y-3">
    <p class="text-texte-doux">{{ $libelleElevage }}@if ($libelleElevage !== $elevage->code_pseudo) ({{ $elevage->code_pseudo }})@endif</p>
    <nav aria-label="Volets du dossier" class="flex flex-wrap gap-1 border-b border-filet pb-2">
        @foreach ($volets as $route => $libelle)
            <a href="{{ $route === 'visites' ? route('visites') : route($route, $elevage->id) }}" class="lien-navigation"
               @if ($route === $courant) aria-current="page" @endif>{{ $libelle }}</a>
        @endforeach
    </nav>
    @if ($agent)
        <p class="text-sm text-texte-doux">Vue du dossier : l'éleveur et son vétérinaire n'y lisent que les objets publiés ; les autres sont marqués « non publié ».</p>
    @endif
</div>
