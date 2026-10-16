<div>
    @include('livewire.dossier.lecture.partiels.entete', ['courant' => 'dossier.plan'])

    @forelse ($plans as $plan)
        @php($v = $plan['visite'])
        @if ($loop->first)
            <section class="mb-6" aria-labelledby="plan-{{ $v->id }}">
                <h2 id="plan-{{ $v->id }}" class="mb-3 text-lg font-semibold">Plan de la visite du {{ $v->date_visite->format('d/m/Y') }}</h2>
                <livewire:dossier.interpretation.plan-actions :visite-id="$v->id" :publiee="true" :key="'plan-'.$v->id" />
            </section>
        @else
            <details class="carte mb-4">
                <summary class="cursor-pointer font-medium">Plan de la visite du {{ $v->date_visite->format('d/m/Y') }}</summary>
                <div class="mt-3"><livewire:dossier.interpretation.plan-actions :visite-id="$v->id" :publiee="true" :key="'plan-'.$v->id" /></div>
            </details>
        @endif
    @empty
        <p class="carte text-texte-doux">Aucun plan d'action publié.</p>
    @endforelse

    <x-limites :limites="$limites" />
</div>
