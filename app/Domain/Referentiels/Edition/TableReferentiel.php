<?php

namespace App\Domain\Referentiels\Edition;

use App\Domain\Referentiels\StatutEpistemique;

/**
 * Description d'un référentiel versionné édité dans E24 (EF-M10-02, lot L8) : table, colonnes saisies avec leur type,
 * paires min ≤ max contrôlées, colonnes d'identification affichées. Les listes de choix d'une colonne viennent d'une
 * table de nomenclature (`ref.<table>.<code>`) ou d'une liste fermée (contraintes CHECK du schéma).
 */
final readonly class TableReferentiel
{
    /**
     * @param  array<string, array{libelle: string, type: 'texte'|'nombre'|'choix'|'booleen', obligatoire?: bool, choix?: string|list<string>}>  $colonnes
     * @param  list<array{0: string, 1: string}>  $paires  colonnes [min, max] à ordonner
     * @param  list<string>  $identification  colonnes affichées pour reconnaître une ligne
     */
    public function __construct(
        public string $cle,
        public string $table,
        public string $libelle,
        public array $colonnes,
        public array $paires,
        public array $identification,
        public bool $avecActif,
    ) {}

    /** @return array<string, self> référentiels du lot L8, par clé d'URL */
    public static function tous(): array
    {
        $statut = ['libelle' => 'Statut épistémique', 'type' => 'choix', 'obligatoire' => true, 'choix' => array_map(fn (StatutEpistemique $s) => $s->value, StatutEpistemique::cases())];
        $reference = ['libelle' => 'Référence bibliographique', 'type' => 'choix', 'choix' => 'ref.reference_biblio.id'];
        $commentaire = ['libelle' => 'Commentaire', 'type' => 'texte'];
        $valide = ['libelle' => 'Validé par le comité technique', 'type' => 'booleen'];

        return [
            'besoins' => new self('besoins', 'ref.besoin_journalier', 'Besoins journaliers', [
                'categorie_code' => ['libelle' => 'Catégorie animale', 'type' => 'choix', 'obligatoire' => true, 'choix' => 'ref.categorie_animale.code'],
                'stade' => ['libelle' => 'Stade', 'type' => 'texte', 'obligatoire' => true],
                'parametre_code' => ['libelle' => 'Élément', 'type' => 'choix', 'obligatoire' => true, 'choix' => 'ref.parametre.code'],
                'mode' => ['libelle' => 'Mode de comparaison', 'type' => 'choix', 'obligatoire' => true, 'choix' => ['par_jour', 'par_kg_ms']],
                'base' => ['libelle' => 'Base', 'type' => 'choix', 'obligatoire' => true, 'choix' => ['animal', 'par_kg_lait']],
                'poids_vif_kg' => ['libelle' => 'Poids vif (kg)', 'type' => 'nombre'],
                'production_valeur' => ['libelle' => 'Production', 'type' => 'nombre'],
                'production_unite' => ['libelle' => 'Unité de production', 'type' => 'texte'],
                'valeur' => ['libelle' => 'Valeur (par jour)', 'type' => 'nombre'],
                'valeur_min' => ['libelle' => 'Minimum (par kg MS)', 'type' => 'nombre'],
                'valeur_max' => ['libelle' => 'Maximum recommandé', 'type' => 'nombre'],
                'maximum' => ['libelle' => 'Maximum réglementaire ou de toxicité', 'type' => 'nombre'],
                'statut_epistemique' => $statut, 'reference_id' => $reference, 'commentaire' => $commentaire, 'valide' => $valide,
            ], [['valeur_min', 'valeur_max'], ['valeur_max', 'maximum']], ['categorie_code', 'stade', 'parametre_code'], false),
            'reperes_mesures' => new self('reperes_mesures', 'ref.repere_mesure_terrain', 'Repères des mesures terrain', [
                'mesure_code' => ['libelle' => 'Mesure', 'type' => 'choix', 'obligatoire' => true, 'choix' => 'ref.mesure_terrain.code'],
                'espece_code' => ['libelle' => 'Espèce', 'type' => 'choix', 'obligatoire' => true, 'choix' => 'ref.espece_animale.code'],
                'stade' => ['libelle' => 'Stade', 'type' => 'texte'],
                'borne_basse' => ['libelle' => 'Borne basse', 'type' => 'nombre'],
                'borne_haute' => ['libelle' => 'Borne haute', 'type' => 'nombre'],
                'statut_epistemique' => $statut, 'reference_id' => $reference, 'commentaire' => $commentaire, 'valide' => $valide,
            ], [['borne_basse', 'borne_haute']], ['mesure_code', 'espece_code', 'stade'], true),
            'reperes_animaux' => new self('reperes_animaux', 'ref.repere_analyse_animale', 'Repères des profils sanguins', [
                'element' => ['libelle' => 'Élément', 'type' => 'texte', 'obligatoire' => true],
                'matrice' => ['libelle' => 'Matrice', 'type' => 'choix', 'obligatoire' => true, 'choix' => ['sang', 'serum', 'plasma', 'foie', 'urine', 'lait', 'poils']],
                'espece_code' => ['libelle' => 'Espèce', 'type' => 'choix', 'obligatoire' => true, 'choix' => 'ref.espece_animale.code'],
                'unite' => ['libelle' => 'Unité', 'type' => 'texte', 'obligatoire' => true],
                'borne_basse' => ['libelle' => 'Borne basse', 'type' => 'nombre'],
                'borne_haute' => ['libelle' => 'Borne haute', 'type' => 'nombre'],
                'statut_epistemique' => $statut, 'reference_id' => $reference, 'commentaire' => $commentaire, 'valide' => $valide,
            ], [['borne_basse', 'borne_haute']], ['element', 'matrice', 'espece_code'], true),
            'bilan_sanitaire' => new self('bilan_sanitaire', 'ref.seuil_bilan_sanitaire', 'Seuils du bilan sanitaire', [
                'indicateur_code' => ['libelle' => 'Indicateur', 'type' => 'choix', 'obligatoire' => true, 'choix' => 'ref.indicateur_sanitaire.code'],
                'espece_code' => ['libelle' => 'Espèce', 'type' => 'choix', 'obligatoire' => true, 'choix' => 'ref.espece_animale.code'],
                'systeme' => ['libelle' => 'Système', 'type' => 'choix', 'choix' => ['lait', 'viande', 'mixte']],
                's1' => ['libelle' => 'Seuil S1 (dégradé)', 'type' => 'nombre', 'obligatoire' => true],
                's2' => ['libelle' => 'Seuil S2 (très dégradé)', 'type' => 'nombre', 'obligatoire' => true],
                'sens' => ['libelle' => 'Sens', 'type' => 'choix', 'obligatoire' => true, 'choix' => ['haut', 'bas']],
                'statut_epistemique' => $statut, 'reference_id' => $reference, 'commentaire' => $commentaire, 'valide' => $valide,
            ], [], ['indicateur_code', 'espece_code', 'systeme'], true),
        ];
    }

    /**
     * Normalise et contrôle une saisie : obligatoires, nombres (virgule acceptée), choix, paires min ≤ max ; renvoie
     * les colonnes à écrire.
     *
     * @param  array<string, mixed>  $saisie
     * @param  array<string, list<string>>  $choix  valeurs admises des colonnes dont la liste vient d'une table
     * @return array<string, mixed>
     */
    public function normaliser(array $saisie, array $choix): array
    {
        $erreurs = [];
        $valeurs = [];
        foreach ($this->colonnes as $nom => $c) {
            $brut = $saisie[$nom] ?? null;
            $vide = $brut === null || (is_string($brut) && trim($brut) === '');
            if ($c['type'] === 'booleen') {
                $valeurs[$nom] = (bool) $brut;

                continue;
            }
            if ($vide) {
                if ($c['obligatoire'] ?? false) {
                    $erreurs[$nom] = $c['libelle'].' : obligatoire.';
                }
                $valeurs[$nom] = null;

                continue;
            }
            $texte = trim((string) $brut);
            if ($c['type'] === 'nombre') {
                $nombre = str_replace([' ', ','], ['', '.'], $texte);
                if (! is_numeric($nombre)) {
                    $erreurs[$nom] = $c['libelle'].' : nombre attendu.';

                    continue;
                }
                $valeurs[$nom] = (float) $nombre;
            } elseif ($c['type'] === 'choix') {
                $admis = is_array($c['choix'] ?? null) ? $c['choix'] : ($choix[$nom] ?? []);
                if (! in_array($texte, $admis, true)) {
                    $erreurs[$nom] = $c['libelle'].' : valeur non admise.';

                    continue;
                }
                $valeurs[$nom] = $texte;
            } else {
                $valeurs[$nom] = $texte;
            }
        }
        foreach ($this->paires as [$min, $max]) {
            if (isset($valeurs[$min], $valeurs[$max]) && $valeurs[$min] > $valeurs[$max]) {
                $erreurs[$max] = $this->colonnes[$max]['libelle'].' : doit être au moins égal à « '.mb_strtolower($this->colonnes[$min]['libelle']).' ».';
            }
        }
        if ($erreurs !== []) {
            throw new EditionRefusee($erreurs);
        }
        if (isset($valeurs['reference_id'])) {
            $valeurs['reference_id'] = (int) $valeurs['reference_id'];
        }

        return $valeurs;
    }
}
