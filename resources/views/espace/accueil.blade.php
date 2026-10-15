<x-layouts.espace titre="Accueil">
    @if ($elevageCourant)
        <p class="mb-6 text-slate-700">Élevage : <span class="font-medium">{{ \App\Services\ElevageCourant::libelle($elevageCourant) }}</span></p>
    @else
        <p class="mb-6 text-slate-700">Aucun élevage n'est encore rattaché à votre compte. Contactez votre technicien GDS.</p>
    @endif
    <x-ecran-a-venir ecran="E02" lot="L3">Messages de la dernière visite, autonomie fourragère, actions et contrôles à venir.</x-ecran-a-venir>
</x-layouts.espace>
