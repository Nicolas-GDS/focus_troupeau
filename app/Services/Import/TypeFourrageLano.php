<?php

namespace App\Services\Import;

/**
 * Type de fourrage (ref.type_fourrage) déduit d'un bulletin LANO, pour le prélèvement créé à l'import faute de
 * rapprochement (EF-M9-01). Proposition du lot L2 (règles absentes des spécifications) : le prélèvement créé reste
 * « à compléter » et le technicien confirme ou corrige le type ; « certain » est faux quand la déduction est fragile.
 */
final class TypeFourrageLano
{
    /**
     * @param  array<string, mixed>  $entete
     * @return array{type: string, certain: bool}
     */
    public function deduire(string $familleRapport, array $entete): array
    {
        $texte = mb_strtoupper(trim(((string) ($entete['espece_fourrage'] ?? '')).' '.((string) ($entete['nom_echantillon'] ?? ''))));
        $luzerne = str_contains($texte, 'LUZERNE');
        $legumineuseSeule = $luzerne && ! preg_match('/DACTYLE|RAY|FETUQUE|FÉTUQUE|FLEOLE|FLÉOLE|BROME|GRAMIN|PRAIRIE/u', $texte);

        return match ($familleRapport) {
            'FOIN' => match (true) {
                $legumineuseSeule => ['type' => 'FOIN_LUZ', 'certain' => true],
                $luzerne || str_contains($texte, 'TREFLE') || str_contains($texte, 'TRÈFLE') => ['type' => 'FOIN_ASSOC', 'certain' => true],
                str_contains($texte, 'PRAIRIE NATURELLE') => ['type' => 'FOIN_PP', 'certain' => true],
                str_contains($texte, 'PRAIRIE TEMPORAIRE') => ['type' => 'FOIN_PT', 'certain' => true],
                default => ['type' => 'FOIN_PP', 'certain' => false],
            },
            'ENSILAGE_ENRUBANNAGE_HERBE' => str_contains($texte, 'ENRUBAN')
                ? ['type' => $legumineuseSeule ? 'ENRUB_LUZ' : 'ENRUB_HERBE', 'certain' => true]
                : ['type' => 'ENSIL_HERBE', 'certain' => str_contains($texte, 'ENSILAGE')],
            'ENSILAGE_MAIS' => ['type' => 'ENSIL_MAIS', 'certain' => true],
            'FOURRAGE_VERT' => ['type' => 'HERBE_VERTE', 'certain' => true],
            'CONCENTRE' => ['type' => 'CONCENTRE', 'certain' => true],
            'FOURRAGE_DIVERS' => match (true) {
                str_contains($texte, 'SORGHO') => ['type' => 'ENSIL_SORGHO', 'certain' => true],
                str_contains($texte, 'METEIL') || str_contains($texte, 'MÉTEIL') => ['type' => 'ENSIL_METEIL', 'certain' => true],
                str_contains($texte, 'CEREALE') || str_contains($texte, 'CÉRÉALE') => ['type' => 'CEREALE_IMM', 'certain' => true],
                default => ['type' => 'ENSIL_METEIL', 'certain' => false],
            },
            default => ['type' => 'FOIN_PP', 'certain' => false],
        };
    }
}
