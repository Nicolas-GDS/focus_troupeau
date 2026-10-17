{{-- En-tête commun des référentiels versionnés (E24) : version courante et choix du référentiel --}}
<section class="carte" aria-labelledby="titre-version">
    <h2 id="titre-version" class="text-lg font-semibold">Version courante des référentiels : {{ $versionCourante?->numero ?? '—' }}</h2>
    @if ($versionCourante) <p class="mt-1 text-sm text-texte-doux">Du {{ $versionCourante->date_version->format('d/m/Y') }}. Décision : {{ $versionCourante->commentaire ?? '—' }}</p> @endif
    <nav aria-label="Référentiel édité" class="mt-4 flex flex-wrap gap-2">
        @foreach (\App\Livewire\Admin\Referentiels::TABLES as $cle => $libelle)
            <a href="{{ route('admin.referentiels', ['table' => $cle]) }}" class="lien-navigation" @if ($table === $cle) aria-current="page" @endif>{{ $libelle }}</a>
        @endforeach
    </nav>
</section>
@if (session('status')) <p class="carte" role="status"><x-pastille etat="couvert" libelle="Enregistré" /> {{ session('status') }}</p> @endif
