{{-- Marque de l'application, en haut à droite de l'en-tête (§ 8.1) : signe horizontal et nom (jeton brand.nom). --}}
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-2']) }}>
    <x-signe-focus :taille="28" />
    <span class="font-titre text-lg font-semibold tracking-tight text-quasi-noir">{{ config('brand.nom') }}</span>
</span>
