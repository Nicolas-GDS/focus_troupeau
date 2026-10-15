<x-layouts.invite titre="Lien de connexion">
    @if ($valide)
        <form method="POST" action="{{ request()->fullUrl() }}">
            @csrf
            <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white">Me connecter</button>
        </form>
    @else
        <p class="rounded border border-red-700 bg-red-50 p-3 text-sm" role="alert">
            <span class="font-semibold">Lien expiré ou déjà utilisé.</span> Ce lien n'est plus valable.
        </p>
        <p class="mt-4 text-sm"><a class="underline" href="{{ route('connexion.lien') }}">Demander un nouveau lien</a></p>
    @endif
</x-layouts.invite>
