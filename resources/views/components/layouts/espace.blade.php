@props(['titre'])
<!DOCTYPE html>
<html lang="fr" @if ($gds) data-gds="{{ $gds->id }}" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $titre }} — {{ config('brand.nom') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="flex min-h-screen flex-col">
    <a href="#contenu" class="sr-only focus:not-sr-only focus:absolute focus:left-2 focus:top-2 focus:z-50 focus:rounded-md focus:bg-white focus:px-3 focus:py-2">Aller au contenu</a>

    {{-- En-tête co-brandé (§ 8.1) : logo du GDS de l'élevage à gauche, marque de l'application à droite ; filet à la couleur de l'entité --}}
    <header class="border-b-4 border-entite bg-white">
        <div class="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3 px-4 py-3">
            <div class="flex items-center gap-3">
                @if ($gds)
                    <x-logo-gds :gds="$gds" />
                @endif
            </div>
            <a href="{{ route('tableau-de-bord') }}" class="rounded-md">
                <x-marque />
            </a>
        </div>

        <div class="border-t border-slate-200">
            <div class="mx-auto flex max-w-6xl flex-col gap-2 px-4 py-2 sm:flex-row sm:items-center sm:justify-between">
                <nav aria-label="Navigation principale" class="flex flex-col gap-1 sm:flex-row sm:items-center">
                    @foreach ($navigation as $lien)
                        <a href="{{ route($lien['route']) }}" class="lien-navigation"
                           @if (request()->routeIs($lien['route'])) aria-current="page" @endif>{{ $lien['libelle'] }}</a>
                    @endforeach
                </nav>

                <div class="flex flex-col gap-1 sm:flex-row sm:items-center">
                    {{-- EF-M0-08 : sélecteur d'élevage en tête d'écran --}}
                    @if ($elevages->count() > 1)
                        <details class="menu-deroulant">
                            <summary>
                                <span class="text-slate-500">Élevage :</span>
                                <span>{{ \App\Services\ElevageCourant::libelle($elevageCourant) }}</span>
                            </summary>
                            <div>
                                @foreach ($elevages as $elevage)
                                    <form method="POST" action="{{ route('elevage-courant') }}">
                                        @csrf
                                        <input type="hidden" name="elevage" value="{{ $elevage->id }}">
                                        <button type="submit" class="option" @if ($elevage->is($elevageCourant)) aria-current="true" @endif>
                                            <span class="font-medium">{{ \App\Services\ElevageCourant::libelle($elevage) }}</span>
                                            <span class="block text-xs text-slate-500">{{ $elevage->code_pseudo }} · GDS {{ $elevage->gds_id }}</span>
                                        </button>
                                    </form>
                                @endforeach
                            </div>
                        </details>
                    @elseif ($elevageCourant)
                        <p class="px-3 py-2 text-sm"><span class="text-slate-500">Élevage :</span> {{ \App\Services\ElevageCourant::libelle($elevageCourant) }}</p>
                    @endif

                    <details class="menu-deroulant">
                        <summary>{{ $utilisateur->nomComplet() }}</summary>
                        <div>
                            @if ($utilisateur->hasAnyRole(['eleveur', 'veterinaire']))
                                <a href="{{ route('compte') }}" class="option">Mon compte</a>
                                <a href="{{ route('compte.consentements') }}" class="option">Mes consentements</a>
                            @endif
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="option">Se déconnecter</button>
                            </form>
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </header>

    <main id="contenu" class="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
        <h1 class="text-2xl font-semibold text-quasi-noir">{{ $titre }}</h1>

        @if (session('status'))
            <div class="mt-4 rounded-md border border-etat-couvert bg-etat-couvert-fond p-3 text-sm" role="status">{{ session('status') }}</div>
        @endif

        <div class="mt-6">
            {{ $slot }}
        </div>
    </main>

    <x-pied-de-page />
</body>
</html>
