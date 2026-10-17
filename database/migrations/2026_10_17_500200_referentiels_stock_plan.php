<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lot L5 — référentiels des stocks et du troupeau (version 5 ; décisions du 17/10/2026).
 *
 * - app.parametre_defaut : valeurs par défaut de RC-6 (pertes), RC-7 (MS, densité du vrac), RC-8 (13 kg MS/UGB/j,
 *   durées), RC-9 (classes de couverture), RC-12 (marge de sécurité, copiée dans l'inventaire) et de la cohérence des
 *   deux voies, aux clés et valeurs de calculs/focus_troupeau_calculs.py (DEFAUTS, qui fait foi) ;
 * - ref.coefficient_ugb : coefficients de RC-8.1 (Idele 2020 [T] ; taureau hors référentiel Idele [C]) et
 *   coefficient proposé par défaut pour les catégories du socle que RC-8.1 désigne sans ambiguïté — les autres
 *   (veau, agneau, chevrette, mâles caprins et ovins) n'en reçoivent pas : le coefficient se saisit ;
 * - ref.besoin_journalier : seuls les repères écrits dans RC-9 (point ouvert 6), non validés (« repère à valider »).
 *
 * Correspondance des statuts (convention du lot L2) : [T] et [V] → publie ; [M] → publie ; [C] ordre de grandeur →
 * ordre_de_grandeur ; [C] → consensus_professionnel ; [CP] → hypothese_locale.
 */
return new class extends Migration
{
    private const VERSION = '5';

    /** @var list<array{0: string, 1: int|float, 2: string, 3: string, 4: string, 5: string}> clé, valeur, unité, libellé, source, statut */
    private const PARAMETRES = [
        ['pertes.conservation.silo_bien_conduit', 0.05, 'fraction', 'Pertes de conservation, silo bien conduit', 'RC-6 ; organismes techniques français [C]', 'consensus_professionnel'],
        ['pertes.conservation.silo_chauffe', 0.12, 'fraction', 'Pertes de conservation, silo qui chauffe, reprise estivale ou lente', 'RC-6 ; milieu de 10–15 % [C], 10 % mesurés en ferme [M]', 'consensus_professionnel'],
        ['pertes.stockage.batiment_ferme', 0.02, 'fraction', 'Pertes de stockage des balles, bâtiment fermé', 'RC-6 ; Huhnke 2017, Lemus 2023 [M]', 'publie'],
        ['pertes.stockage.sous_toit', 0.03, 'fraction', 'Pertes de stockage des balles, sous toit', 'RC-6 ; Huhnke 2017, Lemus 2023 [M]', 'publie'],
        ['pertes.stockage.bache_sureleve', 0.04, 'fraction', 'Pertes de stockage des balles, bâché surélevé', 'RC-6 ; Huhnke 2017, Lemus 2023 [M]', 'publie'],
        ['pertes.stockage.bache_sol', 0.08, 'fraction', 'Pertes de stockage des balles, bâché au sol', 'RC-6 ; Huhnke 2017, Lemus 2023 [M]', 'publie'],
        ['pertes.stockage.expose_sureleve', 0.10, 'fraction', 'Pertes de stockage des balles, exposé surélevé', 'RC-6 ; Huhnke 2017, Lemus 2023 [M]', 'publie'],
        ['pertes.stockage.expose_sol', 0.15, 'fraction', 'Pertes de stockage des balles, exposé au sol', 'RC-6 ; Huhnke 2017, Lemus 2023 [M]', 'publie'],
        ['pertes.distribution.auge_cone', 0.04, 'fraction', 'Pertes à la distribution, auge ou râtelier à cône', 'RC-6 ; Buskirk et al. 2003 [M]', 'publie'],
        ['pertes.distribution.ratelier_anneau', 0.061, 'fraction', 'Pertes à la distribution, râtelier à anneau', 'RC-6 ; Buskirk et al. 2003 [M] (valeur de calculs/)', 'publie'],
        ['pertes.distribution.remorque', 0.114, 'fraction', 'Pertes à la distribution, remorque distributrice', 'RC-6 ; Buskirk et al. 2003 [M] (valeur de calculs/)', 'publie'],
        ['pertes.distribution.deroule_sol', 0.13, 'fraction', 'Pertes à la distribution, déroulé au sol ou râtelier berceau (défaut)', 'RC-6 ; Buskirk et al. 2003, Lemus 2023 [M]', 'publie'],
        ['pertes.distribution.anneau_sans_panneau', 0.20, 'fraction', 'Pertes à la distribution, anneau sans panneau', 'RC-6 ; Buskirk et al. 2003 [M]', 'publie'],
        ['ms.defaut.foin', 85.0, '% brut', 'Matière sèche par défaut du foin', 'RC-7 [T]', 'publie'],
        ['ms.defaut.paille', 90.0, '% brut', 'Matière sèche par défaut de la paille', 'RC-7 [T]', 'publie'],
        ['densite.foin_vrac_kg_m3', 90.0, 'kg brut/m³', 'Densité du foin en vrac', 'RC-7 ; 80–100 kg/m³ [T]', 'publie'],
        ['ugb.kg_ms_par_ugb_jour', 13.0, 'kg MS/UGB/j', 'Ingestion de référence par UGB (voie UGB)', 'RC-8.1 ; Idele 2020 [T]', 'publie'],
        ['periode.jours_defaut', 130, 'jours', 'Durée par défaut de la période de référence (classeur Idele)', 'RC-8.1 ; Idele [T]', 'publie'],
        ['periode.jours_plaine', 160, 'jours', 'Durée de référence proposée pour la plaine', 'RC-8.1, RC-10 ; FRGDS AURA / Idele 2019 [V]', 'publie'],
        ['securite.marge', 0.15, 'fraction', 'Marge de sécurité sur le besoin prélevé', 'RC-12 ; document 05, § 7.2 [CP]', 'hypothese_locale'],
        ['securite.marge_annee_seche', 0.25, 'fraction', 'Marge de sécurité en année sèche ou stocks estimés', 'RC-12 ; document 05, § 7.2 [CP]', 'hypothese_locale'],
        ['couverture.deficit', 0.80, 'fraction du besoin', 'Couverture : déficit sous ce ratio', 'RC-9 [CP]', 'hypothese_locale'],
        ['couverture.limite', 1.00, 'fraction du besoin', 'Couverture : limite sous ce ratio', 'RC-9 [CP]', 'hypothese_locale'],
        ['couverture.exces', 1.50, 'fraction du besoin', 'Couverture : excès au-delà de ce ratio', 'RC-9 [CP]', 'hypothese_locale'],
        ['coherence.ecart_voies_max', 0.20, 'fraction', 'Écart toléré entre voie UGB et voie ration', 'RC-8 [CP]', 'hypothese_locale'],
    ];

    /** @var list<array{0: string, 1: string, 2: float, 3: string|null, 4: string}> code, libellé, coefficient, espèce, statut */
    private const COEFFICIENTS = [
        ['VL', 'Vache laitière', 1.02, 'BOV', 'publie'],
        ['VA', 'Vache allaitante', 0.90, 'BOV', 'publie'],
        ['GEN_0_1', 'Génisse de moins d\'un an', 0.30, 'BOV', 'publie'],
        ['GEN_1_2', 'Génisse de 1 à 2 ans', 0.60, 'BOV', 'publie'],
        ['GEN_2_PLUS', 'Génisse de plus de 2 ans', 0.80, 'BOV', 'publie'],
        ['MALE_1_2', 'Mâle de 1 à 2 ans', 0.60, 'BOV', 'publie'],
        ['TAURILLON', 'Taurillon', 0.80, 'BOV', 'publie'],
        ['TAUREAU', 'Taureau reproducteur', 1.00, 'BOV', 'consensus_professionnel'],
        ['BREBIS', 'Brebis', 0.15, 'OVI', 'publie'],
        ['CHEVRE', 'Chèvre', 0.20, 'CAP', 'publie'],
        ['JUMENT_SUITEE', 'Jument suitée', 1.25, null, 'publie'],
    ];

    /** @var array<string, string> catégorie du socle → coefficient proposé par défaut (rattachement [CP]) */
    private const RATTACHEMENTS = [
        'VL' => 'VL', 'VL_TARIE' => 'VL', 'VA' => 'VA', 'GEN' => 'GEN_1_2', 'JB' => 'TAURILLON',
        'BREBIS_ALL' => 'BREBIS', 'BREBIS_ENT' => 'BREBIS', 'BREBIS_LAIT' => 'BREBIS', 'CHEVRE' => 'CHEVRE',
    ];

    public function up(): void
    {
        DB::table('ref.version_referentiel')->insert([
            'numero' => self::VERSION, 'date_version' => '2026-10-17',
            'commentaire' => 'Stocks et troupeau (lot L5) : coefficients UGB (Idele 2020), besoins journaliers limités aux repères de RC-9 (à valider par le vétérinaire conseil, point ouvert 6) ; tables d\'aliments créées vides (point ouvert 5)',
        ]);

        foreach (self::PARAMETRES as [$cle, $valeur, $unite, $libelle, $source, $statut]) {
            DB::table('app.parametre_defaut')->insert([
                'gds_id' => null, 'cle' => $cle, 'valeur' => $valeur, 'unite' => $unite, 'libelle' => $libelle,
                'source' => 'Spécifications § 4.2-4.3, '.$source, 'statut_epistemique' => $statut, 'valide_du' => '2026-10-17',
            ]);
        }

        foreach (self::COEFFICIENTS as $ordre => [$code, $libelle, $coefficient, $espece, $statut]) {
            DB::table('ref.coefficient_ugb')->insert([
                'code' => $code, 'libelle' => $libelle, 'coefficient' => $coefficient, 'espece_code' => $espece,
                'source' => $code === 'TAUREAU' ? 'RC-8.1 ; hors référentiel Idele [C]' : 'RC-8.1 ; Idele, classeur bilan fourrager 2020 [T]',
                'statut_epistemique' => $statut, 'ordre' => $ordre + 1,
            ]);
        }

        foreach (self::RATTACHEMENTS as $categorie => $coefficient) {
            DB::table('ref.categorie_animale')->where('code', $categorie)->update(['coefficient_ugb_code' => $coefficient]);
        }

        $this->besoins();
    }

    public function down(): void
    {
        DB::table('ref.besoin_journalier')->delete();
        DB::table('ref.categorie_animale')->update(['coefficient_ugb_code' => null]);
        DB::table('ref.coefficient_ugb')->delete();
        DB::table('app.parametre_defaut')->whereIn('cle', array_column(self::PARAMETRES, 0))->delete();
        DB::table('ref.version_referentiel')->where('numero', self::VERSION)->delete();
    }

    /** Repères de RC-9 (§ 4.3), et eux seuls. */
    private function besoins(): void
    {
        $lignes = [];
        $vl = 'RC-9 : vache laitière, 0,42 UFL et 48 g PDI par kg de lait TB 40, entretien 5,3 UFL/j à 650 kg [V, ordre de grandeur]';
        foreach (['lactation', 'entretien'] as $stade) {
            $lignes[] = ['VL', $stade, 650, 'UFL_2018', 'par_jour', 'animal', null, null, 5.3, 'ordre_de_grandeur', $vl.' (entretien)'];
        }
        $lignes[] = ['VL', 'lactation', 650, 'UFL_2018', 'par_jour', 'par_kg_lait', null, null, 0.42, 'ordre_de_grandeur', $vl.' (production)'];
        $lignes[] = ['VL', 'lactation', 650, 'PDI', 'par_jour', 'par_kg_lait', null, null, 48, 'ordre_de_grandeur', $vl.' (production)'];
        $lignes[] = ['BREBIS_ENT', 'entretien', 70, 'UFL_2018', 'par_jour', 'animal', null, null, 0.8, 'consensus_professionnel', 'RC-9 : brebis à l\'entretien, 0,8 UFL/j à 70 kg [C]'];
        $lignes[] = ['BREBIS_ENT', 'entretien', 70, 'PDI', 'par_jour', 'animal', null, null, 60, 'consensus_professionnel', 'RC-9 : brebis à l\'entretien, 60 g PDI/j à 70 kg [C]'];
        $lignes[] = ['CHEVRE', 'entretien', 60, 'UFL_2018', 'par_jour', 'animal', null, null, 0.88, 'publie', 'RC-9 : chèvre à l\'entretien, 0,88 UFL/j à 60 kg [V]'];

        $va = 'RC-9 : classeur bilan fourrager Idele, fiches « Alimentation vaches allaitantes », concentration par kg MS [C]';
        foreach ([
            ['tarissement', 'UFL_2018', 0.70, 0.75], ['tarissement', 'MAT', 100, 110],
            ['prepa_velage', 'UFL_2018', 0.75, 0.85],
            ['lactation', 'UFL_2018', 0.72, 0.80], ['lactation', 'MAT', 120, 140],
        ] as [$stade, $parametre, $min, $max]) {
            $lignes[] = ['VA', $stade, null, $parametre, 'par_kg_ms', 'animal', $min, $max, null, 'consensus_professionnel', $va];
        }
        foreach (['tarissement', 'prepa_velage', 'lactation'] as $stade) {
            foreach ([['CA', 5, 7], ['P', 3, 3.5], ['MG', 2, 2.5]] as [$parametre, $min, $max]) {
                $lignes[] = ['VA', $stade, null, $parametre, 'par_kg_ms', 'animal', $min, $max, null, 'consensus_professionnel', $va];
            }
        }
        foreach ([['UFL_2018', 0.75, 0.85], ['MAT', 125, 155]] as [$parametre, $min, $max]) {
            $lignes[] = ['GEN', 'croissance', null, $parametre, 'par_kg_ms', 'animal', $min, $max, null, 'consensus_professionnel', 'RC-9 : génisses, concentration par kg MS (classeur bilan fourrager Idele) [C]'];
        }

        foreach ($lignes as [$categorie, $stade, $poids, $parametre, $mode, $base, $min, $max, $valeur, $statut, $commentaire]) {
            DB::table('ref.besoin_journalier')->insert([
                'categorie_code' => $categorie, 'stade' => $stade, 'poids_vif_kg' => $poids, 'parametre_code' => $parametre,
                'mode' => $mode, 'base' => $base, 'valeur_min' => $min, 'valeur_max' => $max, 'valeur' => $valeur,
                'statut_epistemique' => $statut, 'commentaire' => $commentaire, 'valide' => false, 'version_creation' => self::VERSION,
            ]);
        }
    }
};
