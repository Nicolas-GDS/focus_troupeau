<?php

namespace Database\Seeders;

use App\Models\App\Convention;
use App\Models\App\ElevageAcces;
use App\Models\App\User;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Prelevement;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Services\Dossier\EtiquettesService;
use App\Services\Import\ImportBulletinService;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use App\Services\Stocks\StocksService;
use App\Services\Troupeau\TroupeauService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Jeu d'essai J1 — élevage allaitant synthétique (§ 9.1 ; reproduit dans calculs/test_calculs.py) : inventaire au
 * 1er novembre 2026, période du 1er novembre 2026 au 31 mars 2027 (151 jours), stocks et lots d'animaux décrits au
 * § 9.1, saisis par les services de l'application (mêmes calculs que l'écran).
 *
 * - Silo de maïs (L1 30 m, L2 6 m, largeur 8 m, hauteur 2 m, MS 33 %, amidon 30 %) : présent pour vérifier RC-5 ;
 *   le § 9.1 le dit « non consommé dans l'exemple » : il est saisi dans un inventaire antérieur, clos, pour ne pas
 *   entrer dans le stock utile du bilan (T-14).
 * - Foin 2025 : « 40 t MS utiles (lot déjà calculé) » — reproduit par 160 balles de 300 kg à 85 % de MS et les pertes
 *   qui ramènent ce lot à 40 t MS. Foin 2025 et foin 2026 (400 balles de 300 kg, MS 85 %) analysés avec les teneurs
 *   du foin de T-13 (calculs/) ; enrubannage 2026 (150 balles de 650 kg, MS 45 % de l'analyse, teneurs de T-13),
 *   paille (100 balles de 280 kg, MS par défaut 90 %, non analysée), tous sous toit (3 %). Dates de récolte de T-14
 *   (ordre de consommation dans la famille : le foin 2025 d'abord).
 * - Correcteur azoté et minéral décrits par étiquette, aux teneurs de T-13 (valeurs d'essai de calculs/).
 * - 100 vaches allaitantes en lactation (foin 4 kg MS, enrubannage 7, correcteur 0,88, minéral 0,1) et 30 génisses
 *   de 1 à 2 ans (foin 5 kg MS, paille 1), fourrages pris par famille de stock comme en T-14, distribution par
 *   déroulé au sol (13 %).
 *
 * Attendus du § 9.1 : stock utile 205,94 t MS hors silo ; besoin prélevé 222,16 t MS ; sécurité 33,32 ; bilan
 * −49,54 ; autonomie 139 jours ; rupture de l'enrubannage le 23/12/2026 ; foin 2025 épuisé le 03/01/2027. Agents de J4 ; éleveur propre. Interdit en
 * production, rejouable.
 */
class JeuJ1Seeder extends Seeder
{
    public const CODE = 'J1-ALLAITANT';

    public const ELEVEUR = 'eleveur.j1@j1.test';

    public const TECHNICIEN = 'technicien.24@j4.test';

    /** Teneurs par kg MS du foin et de l'enrubannage de T-13 (calculs/test_calculs.py). */
    private const TENEURS = [
        'foin' => ['MS' => 85, 'UFL_2018' => 0.67, 'PDI' => 60, 'MAT' => 78, 'CA' => 7.3, 'P' => 2.2, 'CU' => 5.1, 'K' => 19.7],
        'enrub' => ['MS' => 45, 'UFL_2018' => 0.80, 'PDI' => 70, 'MAT' => 84, 'CA' => 9.6, 'P' => 2.5, 'CU' => 5.4, 'K' => 13.4],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Le jeu d\'essai J1 ne doit pas être chargé en production.');
        }

        $this->call(JeuJ4Seeder::class);

        if (Elevage::query()->withoutGlobalScopes()->where('code_pseudo', self::CODE)->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $technicien = User::query()->where('email', self::TECHNICIEN)->firstOrFail();
            $elevage = Elevage::query()->withoutGlobalScopes()->create(['code_pseudo' => self::CODE, 'departement' => '24', 'gds_id' => 24, 'systeme_bovin' => null]);
            Convention::query()->create(['elevage_id' => $elevage->id, 'gds_id' => 24, 'date_debut' => '2026-09-01', 'date_fin' => now()->addYear()->endOfYear()->toDateString()]);
            $eleveur = User::query()->firstOrCreate(['email' => self::ELEVEUR], ['nom' => 'Éleveur', 'prenom' => 'J1', 'password' => (string) config('authentification.mot_de_passe_j4')]);
            $eleveur->forceFill(['notice_rgpd_acceptee_le' => now()])->save();
            $eleveur->syncRoles(['eleveur']);
            $eleveur->elevages()->syncWithoutDetaching([$elevage->id => ['role' => ElevageAcces::ROLE_ELEVEUR]]);

            $stocks = app(StocksService::class);
            $this->silo($elevage);
            $inventaire = $stocks->creerInventaire($elevage, ['date_inventaire' => '2026-11-01', 'type' => 'fin_recolte', 'date_debut' => '2026-11-01', 'date_fin' => '2027-03-31', 'annee_seche' => false]);
            $this->stocks($elevage, $inventaire, $technicien);
            $this->troupeau($elevage, $inventaire);
        });
    }

    /** @return array<string, LotStock> */
    private function stocks(Elevage $elevage, Inventaire $inventaire, User $technicien): array
    {
        $stocks = app(StocksService::class);
        $balles = fn (string $libelle, string $famille, int $n, int $poids, array $sup = []) => $stocks->enregistrerLot($inventaire,
            ['libelle' => $libelle, 'forme' => 'balle_ronde', 'famille_code' => $famille, 'nombre' => $n, 'poids_brut_kg' => $poids, 'mode_stockage' => 'pertes.stockage.sous_toit', ...$sup]);

        return [
            'foin2025' => $balles('Foin 2025', 'FOIN', 160, 300, ['type_fourrage_code' => 'FOIN_PP', 'pertes_pct' => 1 - 40 / 40.8, 'date_recolte' => '2025-06-10',
                'prelevement_id' => $this->analyse($elevage, 'FOIN_PP', 'Foin 2025', self::TENEURS['foin'], $technicien),
                'commentaire' => 'Lot de 40 t MS utiles déjà calculé (§ 9.1), reproduit par 160 balles de 300 kg à 85 % de MS.']),
            'foin2026' => $balles('Foin 2026', 'FOIN', 400, 300, ['type_fourrage_code' => 'FOIN_PP', 'date_recolte' => '2026-06-15',
                'prelevement_id' => $this->analyse($elevage, 'FOIN_PP', 'Foin 2026', self::TENEURS['foin'], $technicien, 'J1-FOIN26')]),
            'enrub' => $balles('Enrubannage 2026', 'ENRUB', 150, 650, ['type_fourrage_code' => 'ENRUB_HERBE', 'date_recolte' => '2026-05-15',
                'prelevement_id' => $this->analyse($elevage, 'ENRUB_HERBE', 'Enrubannage 2026', self::TENEURS['enrub'], $technicien)]),
            'paille' => $balles('Paille', 'PAILLE', 100, 280, ['date_recolte' => '2026-07-20']),
        ];
    }

    /** Silo de maïs de RC-5, dans un inventaire antérieur clos : hors du bilan de J1 (« non consommé », § 9.1). */
    private function silo(Elevage $elevage): void
    {
        $stocks = app(StocksService::class);
        $inventaire = $stocks->creerInventaire($elevage, ['date_inventaire' => '2026-10-01', 'type' => 'fin_recolte', 'date_debut' => '2026-10-01', 'date_fin' => '2026-10-31', 'annee_seche' => false,
            'commentaire' => 'Inventaire du silo de maïs de J1 (vérification de RC-5, § 9.1).']);
        $stocks->enregistrerLot($inventaire, [
            'libelle' => 'Maïs ensilage — silo couloir', 'forme' => 'silo', 'famille_code' => 'ENSIL_MAIS', 'ouvrage' => 'couloir',
            'l1_m' => 30, 'l2_m' => 6, 'largeur_m' => 8, 'hauteur_m' => 2, 'ms_pct' => 33, 'amidon_pct' => 30,
            'methode_densite' => 'equation', 'equation_code' => 'mais_couloir', 'mode_stockage' => 'pertes.conservation.silo_bien_conduit',
            'commentaire' => 'Non consommé dans l\'exemple J1 (vérification de RC-5, § 9.1).',
        ]);
        $stocks->clore($inventaire);
    }

    private function troupeau(Elevage $elevage, Inventaire $inventaire): void
    {
        $etiquettes = app(EtiquettesService::class);
        $correcteur = $etiquettes->creer($elevage, null, ['nature' => 'concentre', 'libelle_eleveur' => 'Correcteur azoté', 'ms_pct' => 88], [
            'UFL_2018' => ['valeur' => 0.88, 'unite' => 'UFL/kg'], 'PDI' => ['valeur' => 220, 'unite' => 'g/kg'], 'MAT' => ['valeur' => 246.4, 'unite' => 'g/kg'],
            'CA' => ['valeur' => 8.8, 'unite' => 'g/kg'], 'P' => ['valeur' => 7.04, 'unite' => 'g/kg'], 'CU' => ['valeur' => 17.6, 'unite' => 'mg/kg'], 'K' => ['valeur' => 13.2, 'unite' => 'g/kg'],
        ]);
        $mineral = $etiquettes->creer($elevage, null, ['nature' => 'mineral', 'libelle_eleveur' => 'Minéral', 'ms_pct' => 100, 'dose_g_par_animal_jour' => 100], [
            'CA' => ['valeur' => 8, 'unite' => '%'], 'P' => ['valeur' => 12, 'unite' => '%'], 'CU' => ['valeur' => 2000, 'unite' => 'mg/kg'],
        ]);

        $troupeau = app(TroupeauService::class);
        $vaches = $troupeau->creerLot($elevage, $inventaire, ['libelle' => 'Vaches allaitantes', 'categorie_code' => 'VA']);
        $ration = $troupeau->ajouterPhase($vaches, 'lactation', '2026-11-01', '2027-03-31', 100)->ration;
        $troupeau->ajouterComposant($ration, ['source' => 'famille_stock', 'famille_code' => 'FOIN', 'kg_ms' => 4.0]);
        $troupeau->ajouterComposant($ration, ['source' => 'famille_stock', 'famille_code' => 'ENRUB', 'kg_ms' => 7.0]);
        $troupeau->ajouterComposant($ration, ['source' => 'etiquette', 'etiquette_id' => $correcteur->id, 'kg_ms' => 0.88]);
        $troupeau->ajouterComposant($ration, ['source' => 'etiquette', 'etiquette_id' => $mineral->id, 'kg_ms' => 0.1]);

        $genisses = $troupeau->creerLot($elevage, $inventaire, ['libelle' => 'Génisses 1-2 ans', 'categorie_code' => 'GEN']);
        $ration = $troupeau->ajouterPhase($genisses, 'croissance', '2026-11-01', '2027-03-31', 30)->ration;
        $troupeau->ajouterComposant($ration, ['source' => 'famille_stock', 'famille_code' => 'FOIN', 'kg_ms' => 5.0]);
        $troupeau->ajouterComposant($ration, ['source' => 'famille_stock', 'famille_code' => 'PAILLE', 'kg_ms' => 1.0]);
    }

    /**
     * Prélèvement analysé (bulletin contrôlé, interprétation validée et publiée) ; renvoie l'identifiant du prélèvement.
     *
     * @param  array<string, float|int>  $teneurs
     */
    private function analyse(Elevage $elevage, string $type, string $nom, array $teneurs, User $technicien, ?string $echantillon = null): int
    {
        $prelevement = Prelevement::query()->withoutGlobalScopes()->create([
            'elevage_id' => $elevage->id, 'campagne_code' => '2025-2026', 'date_prelevement' => '2026-06-15', 'type_fourrage_code' => $type, 'nom_lot' => $nom,
        ]);
        $analyse = Analyse::query()->withoutGlobalScopes()->create([
            'prelevement_id' => $prelevement->id, 'laboratoire_id' => (int) DB::table('ref.laboratoire')->where('code', 'LANO')->value('id'),
            'num_echantillon_labo' => $echantillon ?? 'J1-'.$type, 'statut' => Analyse::STATUT_RECU,
        ]);
        $unites = DB::table('ref.parametre')->whereIn('code', array_keys($teneurs))->pluck('unite', 'code');
        foreach ($teneurs as $code => $valeur) {
            DB::table('fourrage.resultat')->insert(['analyse_id' => $analyse->id, 'parametre_code' => $code, 'valeur' => $valeur, 'unite' => (string) $unites[$code], 'origine' => 'labo']);
        }

        app(ImportBulletinService::class)->controlerAnalyseChargee($analyse);
        $interpretations = app(InterpretationService::class);
        $i = $interpretations->creerBrouillon($analyse->refresh(), $technicien);
        $interpretations->enregistrer($i, new RedactionInterpretation(synthese: 'Lot du jeu J1 (valeurs d\'essai de calculs/).', niveauRetenu: $i->refresh()->niveau_propose));
        $interpretations->valider($i->refresh(), $technicien);
        $interpretations->publier($i->refresh(), $technicien, notifier: false);

        return $prelevement->id;
    }
}
