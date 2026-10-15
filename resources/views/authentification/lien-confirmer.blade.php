<x-layouts.invite titre="Lien de connexion">
    @if ($valide)
        <form method="POST" action="{{ request()->fullUrl() }}">
            @csrf
            <button type="submit" class="bouton-principal w-full">Me connecter</button>
        </form>
    @else
        <p class="rounded-md border border-etat-critique bg-etat-critique-fond p-3 text-sm" role="alert">
            <span class="font-semibold">Lien expiré ou déjà utilisé.</span> Ce lien n'est plus valable.
        </p>
        <p class="mt-4 text-sm"><a class="lien" href="{{ route('connexion.lien') }}">Demander un nouveau lien</a></p>
    @endif
</x-layouts.invite>
