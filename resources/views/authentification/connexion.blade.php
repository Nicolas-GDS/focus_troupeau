<x-layouts.invite titre="Connexion">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="etiquette">Adresse e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="champ">
        </div>
        <div>
            <label for="password" class="etiquette">Mot de passe</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="champ">
        </div>
        <div class="flex items-center gap-2">
            <input id="remember" name="remember" type="checkbox" value="1">
            <label for="remember" class="text-sm">Rester connecté sur cet appareil</label>
        </div>
        <button type="submit" class="bouton-principal w-full">Se connecter</button>
    </form>

    <ul class="mt-6 space-y-2 text-sm">
        <li><a class="lien" href="{{ route('connexion.lien') }}">Recevoir un lien de connexion par e-mail</a></li>
        <li><a class="lien" href="{{ route('password.request') }}">Mot de passe oublié</a></li>
    </ul>

    @if (config('entra.actif'))
        <div class="mt-8 border-t border-slate-300 pt-6">
            <p class="text-sm text-slate-700">Agents des GDS</p>
            <a href="{{ route('entra') }}" class="bouton-secondaire mt-2 w-full">Se connecter avec le compte GDS (Microsoft)</a>
        </div>
    @endif
</x-layouts.invite>
