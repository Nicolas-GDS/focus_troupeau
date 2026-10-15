<x-layouts.invite titre="Activation de votre compte">
    @if ($valide)
        <p class="text-sm">Choisissez votre mot de passe : au moins 12 caractères. Les mots de passe connus pour avoir fuité sont refusés.</p>

        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label for="password" class="block text-sm font-medium">Mot de passe</label>
                <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password"
                       class="mt-1 w-full rounded border border-slate-400 px-3 py-2">
            </div>
            <div>
                <label for="password_confirmation" class="block text-sm font-medium">Confirmation du mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"
                       class="mt-1 w-full rounded border border-slate-400 px-3 py-2">
            </div>
            <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white">Activer mon compte</button>
        </form>
    @else
        <p class="rounded border border-red-700 bg-red-50 p-3 text-sm" role="alert">
            <span class="font-semibold">Lien d'activation expiré ou déjà utilisé.</span>
            Demandez une nouvelle invitation à votre technicien GDS.
        </p>
    @endif
</x-layouts.invite>
