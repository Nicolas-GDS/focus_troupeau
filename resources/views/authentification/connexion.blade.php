<x-layouts.invite titre="Connexion">
    <form method="POST" action="{{ route('login.store') }}" class="space-y-4">
        @csrf
        <div>
            <label for="email" class="block text-sm font-medium">Adresse e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                   class="mt-1 w-full rounded border border-slate-400 px-3 py-2">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">Mot de passe</label>
            <input id="password" name="password" type="password" required autocomplete="current-password"
                   class="mt-1 w-full rounded border border-slate-400 px-3 py-2">
        </div>
        <div class="flex items-center gap-2">
            <input id="remember" name="remember" type="checkbox" value="1">
            <label for="remember" class="text-sm">Rester connecté sur cet appareil</label>
        </div>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white">Se connecter</button>
    </form>

    <ul class="mt-6 space-y-2 text-sm">
        <li><a class="underline" href="{{ route('connexion.lien') }}">Recevoir un lien de connexion par e-mail</a></li>
        <li><a class="underline" href="{{ route('password.request') }}">Mot de passe oublié</a></li>
    </ul>

    @if (config('entra.actif'))
        <div class="mt-8 border-t border-slate-300 pt-6">
            <p class="text-sm text-slate-700">Agents des GDS</p>
            <a href="{{ route('entra') }}" class="mt-2 block w-full rounded border border-slate-900 px-4 py-2 text-center font-medium">Se connecter avec le compte GDS (Microsoft)</a>
        </div>
    @endif
</x-layouts.invite>
