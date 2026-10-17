<section class="limites">
    <p>{{ $limites }}</p>
    <p>{{ config('brand.nom') }} {{ config('app.version') }} · référentiel version {{ $version }} · édité le {{ now()->format('d/m/Y') }}.</p>
    <p style="display:flex;justify-content:space-between;align-items:center"><span><span class="titre-police">{{ config('brand.nom') }}</span>, {{ config('brand.baseline') }}</span><img src="{{ $ressources['images']['gds-france'] }}" alt="Réseau GDS France" style="height:8mm"></p>
</section>
