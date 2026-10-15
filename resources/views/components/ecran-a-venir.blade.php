@props(['ecran', 'lot'])
{{-- Écran prévu par les spécifications, construit dans un lot ultérieur (§ 9.3) --}}
<div class="carte border-dashed text-slate-700">
    <p class="font-medium">Cet écran ({{ $ecran }}) sera construit au lot {{ $lot }}.</p>
    @if ($slot->isNotEmpty())
        <div class="mt-2 text-sm">{{ $slot }}</div>
    @endif
</div>
