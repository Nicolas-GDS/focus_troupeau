<x-layouts.espace titre="Information sur vos données personnelles">
    <article class="carte max-w-3xl space-y-4 font-serif text-base leading-relaxed">
        <p class="rounded-md border border-etat-alerte bg-etat-alerte-fond p-3 font-sans text-sm" role="note">
            <span class="font-semibold">Texte provisoire</span> — à valider par la direction et le délégué à la protection des données
            (point ouvert n° 9 : durées de conservation et registre des traitements).
        </p>

        @include('rgpd.notice-contenu')

        @if (auth()->user()->notice_rgpd_acceptee_le === null)
            <form method="POST" action="{{ route('notice-rgpd.accepter') }}" class="pt-2 font-sans">
                @csrf
                <button type="submit" class="bouton-principal">J'ai pris connaissance de ces informations</button>
            </form>
        @else
            <p class="font-sans text-sm text-slate-600">Prise de connaissance enregistrée le {{ auth()->user()->notice_rgpd_acceptee_le->format('d/m/Y à H:i') }}.</p>
        @endif
    </article>
</x-layouts.espace>
