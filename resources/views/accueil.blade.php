<x-layouts.invite titre="Accueil">
    {{-- E02 provisoire : mise en page bi-entité, sélecteur d'élevage et navigation à l'étape 6 du lot L1 --}}
    <p>Bonjour {{ auth()->user()->nomComplet() }}.</p>
    <form method="POST" action="{{ route('logout') }}" class="mt-6">
        @csrf
        <button type="submit" class="underline">Se déconnecter</button>
    </form>
</x-layouts.invite>
