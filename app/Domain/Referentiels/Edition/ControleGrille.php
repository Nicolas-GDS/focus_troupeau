<?php

namespace App\Domain\Referentiels\Edition;

use App\Domain\Interpretation\PlagePlausibilite;
use App\Domain\Interpretation\Seuil;
use App\Domain\Referentiels\StatutEpistemique;

/**
 * Contrôles de cohérence avant l'enregistrement d'une ligne de référentiel (E24, EF-M10-02) :
 * - seuils (RC-1) : paramètre et nomenclatures existants, type rattaché à sa famille, espèce cohérente avec la
 *   catégorie, intervalle [borne_min ; borne_max[ non vide, gravité 0-3, sens, nature et statut épistémique
 *   dans leurs listes, aucun chevauchement avec un seuil actif au même niveau de précision (« Départage et
 *   cohérence de la grille », décision du 15/10/2026) ;
 * - plages de plausibilité (RC-2) : borne_min ≤ borne_max, une seule plage active par paramètre et famille ;
 * - toute modification porte la décision du comité technique (EF-M10-02).
 *
 * Les listes fermées (nature, sens, gravité) reprennent les contraintes CHECK du schéma (document 02, § 5.3).
 */
final class ControleGrille
{
    public const NATURES = ['nutritionnel', 'sanitaire', 'reglementaire', 'statistique_local'];

    public const SENS = [Seuil::SENS_BAS, Seuil::SENS_HAUT];

    public const GRAVITE_MIN = 0;

    public const GRAVITE_MAX = 3;

    /**
     * @return array<string, string>
     */
    public function controlerDecision(?string $decision): array
    {
        return Valeurs::texte($decision) === null
            ? ['decision' => 'La décision du comité technique (commentaire de version) est obligatoire.']
            : [];
    }

    /**
     * @param  SaisieSeuil  $saisie  saisie normalisée (famille et espèce déduites, voir SaisieSeuil::normalisee)
     * @param  list<Seuil>  $autresActifs  seuils actifs de la grille, hors celui que la saisie remplace
     * @return array<string, string> erreurs par champ
     */
    public function controlerSeuil(SaisieSeuil $saisie, array $autresActifs, Nomenclatures $nomenclatures): array
    {
        $erreurs = [];

        if (! in_array($saisie->parametreCode, $nomenclatures->parametres, true)) {
            $erreurs['parametre_code'] = 'Paramètre inconnu.';
        }

        if ($saisie->familleFourrageCode !== null && ! in_array($saisie->familleFourrageCode, $nomenclatures->familles, true)) {
            $erreurs['famille_fourrage_code'] = 'Famille de fourrage inconnue.';
        }

        if ($saisie->typeFourrageCode !== null) {
            $familleDuType = $nomenclatures->familleParType[$saisie->typeFourrageCode] ?? null;

            if ($familleDuType === null) {
                $erreurs['type_fourrage_code'] = 'Type de fourrage inconnu.';
            } elseif ($saisie->familleFourrageCode !== null && $saisie->familleFourrageCode !== $familleDuType) {
                $erreurs['type_fourrage_code'] = "Ce type de fourrage appartient à la famille {$familleDuType}, pas à {$saisie->familleFourrageCode}.";
            }
        }

        if ($saisie->especeCode !== null && ! in_array($saisie->especeCode, $nomenclatures->especes, true)) {
            $erreurs['espece_code'] = 'Espèce inconnue.';
        }

        if ($saisie->categorieCode !== null) {
            $especeDeLaCategorie = $nomenclatures->especeParCategorie[$saisie->categorieCode] ?? null;

            if ($especeDeLaCategorie === null) {
                $erreurs['categorie_code'] = 'Catégorie animale inconnue.';
            } elseif ($saisie->especeCode !== null && $saisie->especeCode !== $especeDeLaCategorie) {
                $erreurs['categorie_code'] = "Cette catégorie relève de l'espèce {$especeDeLaCategorie}, pas de {$saisie->especeCode}.";
            }
        }

        if (! in_array($saisie->nature, self::NATURES, true)) {
            $erreurs['nature'] = 'Nature inconnue (nutritionnel, sanitaire, réglementaire ou statistique locale).';
        }

        if ($saisie->classe === '') {
            $erreurs['classe'] = 'La classe est obligatoire.';
        }

        if ($saisie->gravite === null || $saisie->gravite < self::GRAVITE_MIN || $saisie->gravite > self::GRAVITE_MAX) {
            $erreurs['gravite'] = 'La gravité est comprise entre 0 (information) et 3 (critique).';
        }

        if (! in_array($saisie->sens, self::SENS, true)) {
            $erreurs['sens'] = 'Le sens est obligatoire : bas (carence, valeur faible) ou haut (excès, valeur élevée).';
        }

        if (StatutEpistemique::tryFrom($saisie->statutEpistemique) === null) {
            $erreurs['statut_epistemique'] = 'Statut épistémique inconnu.';
        }

        if ($saisie->referenceId !== null && ! in_array($saisie->referenceId, $nomenclatures->references, true)) {
            $erreurs['reference_id'] = 'Référence bibliographique inconnue.';
        }

        if ($saisie->borneMin === null && $saisie->borneMax === null) {
            $erreurs['borne_min'] = 'Au moins une borne est obligatoire.';
        } elseif ($saisie->borneMin !== null && $saisie->borneMax !== null && $saisie->borneMin >= $saisie->borneMax) {
            $erreurs['borne_max'] = 'La borne haute doit être strictement supérieure à la borne basse (intervalle [min ; max[).';
        }

        // Le chevauchement n'a de sens que sur un intervalle valide.
        if (! isset($erreurs['borne_min']) && ! isset($erreurs['borne_max'])) {
            $candidat = $saisie->versSeuil();

            foreach ($autresActifs as $actif) {
                if ($candidat->chevauche(self::normaliser($actif, $nomenclatures))) {
                    $erreurs['chevauchement'] = sprintf(
                        'Chevauchement avec le seuil actif n° %d (%s, %s) au même niveau de précision : modifiez ou retirez-le d\'abord.',
                        $actif->id, $actif->classe, self::intervalle($actif->borneMin, $actif->borneMax, true),
                    );
                    break;
                }
            }
        }

        return $erreurs;
    }

    /**
     * @param  list<PlagePlausibilite>  $autresActives  plages actives, hors celle que la saisie remplace
     * @return array<string, string> erreurs par champ
     */
    public function controlerPlage(SaisiePlage $saisie, array $autresActives, Nomenclatures $nomenclatures): array
    {
        $erreurs = [];

        if (! in_array($saisie->parametreCode, $nomenclatures->parametres, true)) {
            $erreurs['parametre_code'] = 'Paramètre inconnu.';
        }

        if ($saisie->familleFourrageCode !== null && ! in_array($saisie->familleFourrageCode, $nomenclatures->familles, true)) {
            $erreurs['famille_fourrage_code'] = 'Famille de fourrage inconnue.';
        }

        if (StatutEpistemique::tryFrom($saisie->statutEpistemique) === null) {
            $erreurs['statut_epistemique'] = 'Statut épistémique inconnu.';
        }

        // Proposition, à valider : une plage sans aucune borne ne contrôle rien ; elle est refusée.
        if ($saisie->borneMin === null && $saisie->borneMax === null) {
            $erreurs['borne_min'] = 'Au moins une borne est obligatoire.';
        } elseif ($saisie->borneMin !== null && $saisie->borneMax !== null && $saisie->borneMin > $saisie->borneMax) {
            $erreurs['borne_max'] = 'La borne haute doit être supérieure ou égale à la borne basse (plage [min ; max]).';
        }

        foreach ($autresActives as $active) {
            if ($active->parametreCode === $saisie->parametreCode && $active->familleFourrageCode === $saisie->familleFourrageCode) {
                $erreurs['chevauchement'] = 'Une plage active existe déjà pour ce paramètre et cette famille : modifiez-la plutôt que d\'en créer une seconde.';
                break;
            }
        }

        return $erreurs;
    }

    /**
     * Seuil existant ramené au même niveau de précision qu'une saisie normalisée (famille déduite du type,
     * espèce déduite de la catégorie), pour que le contrôle des chevauchements ne dépende pas de la façon dont
     * la ligne a été chargée.
     */
    private static function normaliser(Seuil $seuil, Nomenclatures $nomenclatures): Seuil
    {
        return SaisieSeuil::depuis([
            'parametre_code' => $seuil->parametreCode,
            'famille_fourrage_code' => $seuil->familleFourrageCode,
            'type_fourrage_code' => $seuil->typeFourrageCode,
            'espece_code' => $seuil->especeCode,
            'categorie_code' => $seuil->categorieCode,
            'nature' => $seuil->nature,
            'borne_min' => $seuil->borneMin,
            'borne_max' => $seuil->borneMax,
            'classe' => $seuil->classe,
            'gravite' => $seuil->gravite,
            'sens' => $seuil->sens,
            'statut_epistemique' => $seuil->statutEpistemique->value,
        ])->normalisee($nomenclatures)->versSeuil($seuil->id);
    }

    /** Intervalle lisible : [min ; max[ pour un seuil, [min ; max] pour une plage ; bornes absentes « −∞ » / « +∞ ». */
    public static function intervalle(?float $min, ?float $max, bool $semiOuvert): string
    {
        $nombre = fn (float $v) => rtrim(rtrim(number_format($v, 3, ',', ' '), '0'), ',');

        return '['.($min === null ? '−∞' : $nombre($min)).' ; '.($max === null ? '+∞' : $nombre($max)).($semiOuvert ? '[' : ']');
    }
}
