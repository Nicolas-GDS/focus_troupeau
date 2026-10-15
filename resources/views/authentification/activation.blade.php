<x-layouts.invite titre="Activation de votre compte">
    @if ($valide)
        <p class="text-sm">Choisissez votre mot de passe : au moins 12 caractères. Les mots de passe connus pour avoir fuité sont refusés.</p>

        <form method="POST" action="{{ request()->fullUrl() }}" class="mt-4 space-y-4">
            @csrf
            <div>
                <label for="password" class="etiquette">Mot de passe</label>
                <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password"
                       class="champ">
            </div>
            <div>
                <label for="password_confirmation" class="etiquette">Confirmation du mot de passe</label>
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"
                       class="champ">
            </div>
            <button type="submit" class="bouton-principal w-full">Activer mon compte</button>
        </form>
    @else
        <p class="rounded-md border border-etat-critique bg-etat-critique-fond p-3 text-sm" role="alert">
            <span class="font-semibold">Lien d'activation expiré ou déjà utilisé.</span>
            Demandez une nouvelle invitation à votre technicien GDS.
        </p>
    @endif
</x-layouts.invite>
