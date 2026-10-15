<x-layouts.invite titre="Code de connexion">
    <p class="text-sm">Un code à six chiffres vient de vous être envoyé par e-mail. Saisissez-le pour terminer la connexion.</p>

    <form method="POST" action="{{ route('connexion.code.verifier') }}" class="mt-4 space-y-4">
        @csrf
        <div>
            <label for="code" class="block text-sm font-medium">Code reçu par e-mail</label>
            <input id="code" name="code" type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6" required autofocus autocomplete="one-time-code"
                   class="mt-1 w-full rounded border border-slate-400 px-3 py-2 tracking-widest">
        </div>
        <button type="submit" class="w-full rounded bg-slate-900 px-4 py-2 font-medium text-white">Valider</button>
    </form>

    <form method="POST" action="{{ route('connexion.code.renvoyer') }}" class="mt-4">
        @csrf
        <button type="submit" class="text-sm underline">Recevoir un nouveau code</button>
    </form>
</x-layouts.invite>
