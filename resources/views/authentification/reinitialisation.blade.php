<x-layouts.invite titre="Nouveau mot de passe">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-4">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <div>
            <label for="email" class="block text-sm font-medium">Adresse e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email', $request->query('email')) }}" required autocomplete="username"
                   class="mt-1 w-full rounded border border-slate-400 px-3 py-2">
        </div>
        <div>
            <label for="password" class="block text-sm font-medium">Nouveau mot de passe (12 caractères minimum)</label>
            <input id="password" name="password" type="password" required minlength="12" autocomplete="new-password"
                   class="mt-1 w-full rounded border border-slate-400 px-3 py-2">
        </div>
        <div>
            <label for="password_confirmation" class="block text-sm font-medium">Confirmation</label>
            <input id="password_confirmation" name="password_confirmation" type="password" required minlength="12" autocomplete="new-password"
                   class="mt-1 w-full rounded border border-slate-400 px-3 py-2">
        </div>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white">Enregistrer</button>
    </form>
</x-layouts.invite>
