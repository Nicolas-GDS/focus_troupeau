@props(['titre' => 'Connexion'])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titre }} — {{ config('brand.nom') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    {{-- Pages publiques (E01) : avant la connexion, le GDS n'est pas connu — couleurs nationales --}}
    <main class="mx-auto w-full max-w-md flex-1 px-4 py-12">
        <x-marque />
        {{-- Première mention dans l'application (§ 8.1) --}}
        <p class="mt-2 text-sm text-slate-600">{{ config('brand.nom') }}, {{ config('brand.baseline') }}</p>

        <div class="carte mt-8">
            <h1 class="text-xl font-semibold">{{ $titre }}</h1>

            @if (session('status'))
                <div class="mt-4 rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</div>
            @endif

            @if ($errors->any())
                <div class="mt-4 rounded-md border border-etat-critique bg-etat-critique-fond p-3 text-sm" role="alert">
                    <p class="font-semibold">Erreur</p>
                    <ul class="mt-1 list-disc pl-5">
                        @foreach ($errors->all() as $erreur)
                            <li>{{ $erreur }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="mt-6">
                {{ $slot }}
            </div>
        </div>
    </main>

    <x-pied-de-page />
</body>
</html>
