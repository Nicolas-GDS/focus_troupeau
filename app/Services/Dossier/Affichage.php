<?php

namespace App\Services\Dossier;

use App\Domain\Dossier\Repere;
use App\Services\Interpretation\InterpretationService;
use Illuminate\Support\Facades\DB;

/**
 * Mise en forme commune des repères du dossier (EF-GEN-02) : bornes en clair, source, statut épistémique et mention
 * « repère à valider » (§ 9.5, décision du 16/10/2026).
 */
final class Affichage
{
    public const A_VALIDER = 'repère à valider';

    /** Bornes incluses en clair (repères des mesures terrain, des profils sanguins, seuils S1/S2). */
    public static function bornes(?float $basse, ?float $haute, string $unite = ''): string
    {
        $unite = $unite === '' || $unite === '-' ? '' : ' '.$unite;

        return match (true) {
            $basse === null && $haute === null => 'sans borne',
            $basse === null => 'jusqu\'à '.self::nombre($haute).$unite,
            $haute === null => self::nombre($basse).$unite.' et plus',
            $basse === $haute => self::nombre($basse).$unite,
            default => 'de '.self::nombre($basse).' à '.self::nombre($haute).$unite,
        };
    }

    public static function nombre(?float $valeur, ?int $decimales = null): string
    {
        return $valeur === null ? '—' : InterpretationService::nombre($valeur, $decimales);
    }

    /**
     * Source d'un repère, prête à afficher.
     *
     * @return array{source: string|null, statut: string, a_valider: bool}
     */
    public static function source(Repere $repere): array
    {
        return [
            'source' => self::sources([$repere->referenceId])[$repere->referenceId] ?? null,
            'statut' => $repere->statutEpistemique->libelle().' ['.$repere->statutEpistemique->codeSpecifications().']',
            'a_valider' => $repere->aValider(),
        ];
    }

    /**
     * Libellés des références bibliographiques.
     *
     * @param  array<int|null>  $ids
     * @return array<int, string>
     */
    public static function sources(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id !== null)));

        if ($ids === []) {
            return [];
        }

        $sources = [];
        foreach (DB::table('ref.reference_biblio')->whereIn('id', $ids)->get() as $ref) {
            $auteur = trim(($ref->auteurs ?? '').' '.($ref->annee ?? ''));
            $sources[(int) $ref->id] = ($auteur === '' ? '' : $auteur.' — ').$ref->titre;
        }

        return $sources;
    }
}
