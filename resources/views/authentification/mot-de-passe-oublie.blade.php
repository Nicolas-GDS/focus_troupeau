<x-layouts.invite titre="Mot de passe oublié">
    <p class="text-sm">Indiquez votre adresse e-mail : vous recevrez un lien pour choisir un nouveau mot de passe.</p>

    <form method="POST" action="{{ route('password.email') }}" class="mt-4 space-y-4">
        @csrf
        <div>
            <label for="email" class="etiquette">Adresse e-mail</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="champ">
        </div>
        <button type="submit" class="bouton-principal w-full">Recevoir le lien</button>
    </form>

    <p class="mt-6 text-sm"><a class="lien" href="{{ route('login') }}">Retour à la connexion</a></p>
</x-layouts.invite>
