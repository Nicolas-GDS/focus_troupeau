<x-layouts.espace titre="Mon compte">
    <dl class="carte grid gap-3 sm:grid-cols-2">
        <div><dt class="text-sm text-slate-600">Nom</dt><dd class="font-medium">{{ auth()->user()->nomComplet() }}</dd></div>
        <div><dt class="text-sm text-slate-600">Adresse e-mail</dt><dd class="font-medium">{{ auth()->user()->email }}</dd></div>
    </dl>
    <div class="mt-6">
        <x-ecran-a-venir ecran="E17" lot="L3">Préférences de notification, changement de mot de passe, double authentification.</x-ecran-a-venir>
    </div>
</x-layouts.espace>
