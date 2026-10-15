<x-layouts.invite titre="Nouveau mot de passe">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label for="email" class="etiquette">Adresse e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->query('email')) }}" required autocomplete="username"
                   class="champ">
        </div>
        <div>
            <label for="password" class="etiquette">Nouveau mot de passe (12 caractères minimum)</label>
            <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password"
                   class="champ">
        </div>
        <div>
            <label for="password_confirmation" class="etiquette">Confirmation</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"
                   class="champ">
        </div>
        <button type="submit" class="bouton-principal w-full">Enregistrer</button>
    </form>
</x-layouts.invite>
