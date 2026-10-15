@props(['titre' => 'Connexion'])
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titre }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    {{-- Mise en page provisoire : remplacée par la mise en page bi-entité à l'étape 6 du lot L1 --}}
    <main class="mx-auto max-w-md px-4 py-12">
        <p class="text-sm text-slate-600">{{ config('app.name') }}, l'approche globale de la santé du troupeau</p>
        <h1 class="mt-2 text-2xl font-semibold">{{ $titre }}</h1>

        @if (session('status'))
            <div class="mt-6 rounded border border-green-700 bg-green-50 p-3 text-sm" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="mt-6 rounded border border-red-700 bg-red-50 p-3 text-sm" role="alert">
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
    </main>
</body>
</html>
