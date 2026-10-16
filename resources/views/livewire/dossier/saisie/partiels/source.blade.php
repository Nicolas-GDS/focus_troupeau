{{-- Source d'un repère, statut épistémique et mention « repère à valider » (EF-GEN-02, § 9.5) --}}
@if ($source)
    @if ($source['a_valider'])
        <span class="mt-1 block text-xs font-medium text-etat-vigilance">{{ \App\Services\Dossier\Affichage::A_VALIDER }}</span>
    @endif
    <details class="mt-1 text-xs text-texte-doux">
        <summary class="cursor-pointer">Source et statut</summary>
        <p>{{ $source['source'] ?? 'Source non renseignée' }}</p>
        <p>{{ $source['statut'] }}</p>
    </details>
@endif
