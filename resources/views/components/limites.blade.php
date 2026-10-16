@props(['limites', 'nonDoses' => null, 'version' => null])
{{-- Mentions de limites (§ 8.3) : ce qui n'est pas dosé, ce que la page ne remplace pas, version de la grille.
     Obligatoire sur toute page de résultat et tout PDF. --}}
<aside class="mt-8 border-t border-filet pt-4 text-xs text-texte-doux" aria-label="Limites de lecture">
    @if ($slot->isNotEmpty()) <div class="mb-1">{{ $slot }}</div> @endif
    @if ($nonDoses) <p>{{ $limites['textes']['fiche.limites.non_doses'] ?? '' }} {{ $nonDoses }}.</p> @endif
    <p>{{ $limites['textes']['fiche.limites.ne_remplace_pas'] ?? '' }}</p>
    <p>{{ $limites['textes']['fiche.limites.repere_a_valider'] ?? '' }}</p>
    <p>Grille d'interprétation version {{ $version ?? $limites['version_grille'] }}.</p>
</aside>
