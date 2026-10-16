<x-layouts.espace titre="Mon compte">
    <dl class="carte grid gap-3 sm:grid-cols-2">
        <div><dt class="text-sm text-slate-600">Nom</dt><dd class="font-medium">{{ auth()->user()->nomComplet() }}</dd></div>
        <div><dt class="text-sm text-slate-600">Adresse e-mail</dt><dd class="font-medium">{{ auth()->user()->email }}</dd></div>
    </dl>
    <div class="mt-6">
        <livewire:compte.preferences-notification />
    </div>
    @if (auth()->user()->password !== null)
        {{-- ENF-SEC-02 : 12 caractères minimum, vérification contre les mots de passe compromis (Fortify) --}}
        <section class="carte mt-6" aria-labelledby="titre-mot-de-passe">
            <h2 id="titre-mot-de-passe" class="text-lg font-semibold">Mot de passe</h2>
            @if (session('status') === 'password-updated')
                <p class="mt-2 text-sm text-etat-couvert" role="status">Mot de passe modifié.</p>
            @endif
            <form method="POST" action="{{ route('user-password.update') }}" class="mt-3 grid gap-3 sm:max-w-md">
                @csrf
                @method('PUT')
                <div>
                    <label for="mdp-actuel" class="etiquette">Mot de passe actuel</label>
                    <input id="mdp-actuel" name="current_password" type="password" autocomplete="current-password" class="champ w-full" required>
                    @error('current_password', 'updatePassword') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="mdp-nouveau" class="etiquette">Nouveau mot de passe (12 caractères au moins)</label>
                    <input id="mdp-nouveau" name="password" type="password" autocomplete="new-password" class="champ w-full" required minlength="12">
                    @error('password', 'updatePassword') <p class="mt-1 text-sm text-etat-critique">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="mdp-confirmation" class="etiquette">Confirmation</label>
                    <input id="mdp-confirmation" name="password_confirmation" type="password" autocomplete="new-password" class="champ w-full" required>
                </div>
                <div><button type="submit" class="bouton-secondaire">Changer le mot de passe</button></div>
            </form>
        </section>
    @endif
    <div class="mt-6">
        <x-ecran-a-venir ecran="E17" lot="L3">Activation de la double authentification par code e-mail depuis cet écran.</x-ecran-a-venir>
    </div>
</x-layouts.espace>
