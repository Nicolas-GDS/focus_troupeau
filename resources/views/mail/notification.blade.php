<x-mail::message>
Bonjour,

@foreach ($lignes as $ligne)
{{ $ligne }}

@endforeach
@if ($lien)
<x-mail::button :url="$lien">
Ouvrir {{ config('brand.nom') }}
</x-mail::button>
@endif

{{ config('brand.nom') }}, {{ config('brand.baseline') }}
</x-mail::message>
