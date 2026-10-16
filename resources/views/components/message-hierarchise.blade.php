@props(['message'])
@php
    use App\Livewire\Dossier\Interpretation\EcranVisite;
    use App\Livewire\Interpretation\InterpretationBulletin;
    $niveau = InterpretationBulletin::niveau($message->gravite);
@endphp
{{-- Message hiérarchisé (§ 8.3) : gravité (pictogramme, libellé, couleur), pilier, phrase complète --}}
<li {{ $attributes->merge(['class' => 'flex flex-wrap items-start gap-2']) }}>
    <x-pastille :etat="$niveau['etat']" :libelle="'Gravité '.$message->gravite" />
    <span>
        @if ($message->pilier) <span class="text-texte-doux">{{ EcranVisite::piliers()[$message->pilier] ?? $message->pilier }} · </span> @endif
        {{ $message->texte }}
    </span>
</li>
