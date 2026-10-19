<?php

namespace Database\Seeders;

use App\Models\App\Convention;
use App\Models\App\ElevageAcces;
use App\Models\App\User;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Prelevement;
use App\Services\Dossier\EtiquettesService;
use App\Services\Dossier\InterpretationVisiteService;
use App\Services\Dossier\RedactionVisite;
use App\Services\Dossier\VisiteService;
use App\Services\Import\ImportBulletinService;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use App\Services\Stocks\StocksService;
use App\Services\Troupeau\TroupeauService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Jeu d'essai J2 — élevage ovin synthétique (§ 9.1 ; calculs/test_calculs.py, T-13k et T-13l) : 300 brebis
 * allaitantes nourries au foin (Cu 5 mg/kg MS, 1,5 kg MS par jour) et à un seau à lécher destiné aux bovins (Cu
 * 300 mg/kg, 0,1 kg MS par jour) ; le cuivre de la ration (23,4 mg/kg MS) dépasse le maximum réglementaire des ovins
 * (15 mg/kg MS) : classe « maximum dépassé », gravité 3. Une visite de niveau 3, visée par le vétérinaire conseil, est
 * publiée : SMS à l'éleveur et au technicien (T-22). Agents de J4 ; éleveur propre, avec un numéro de mobile. Interdit
 * en production, rejouable.
 */
class JeuJ2Seeder extends Seeder
{
    public const CODE = 'J2-OVIN';

    public const ELEVEUR = 'eleveur.j2@j2.test';

    public const TECHNICIEN = 'technicien.24@j4.test';

    public const VETERINAIRE_CONSEIL = 'veterinaire.conseil.24@j4.test';

    public const MESSAGE = 'Retirer dès aujourd\'hui le seau à lécher destiné aux bovins : le cuivre de la ration des brebis dépasse le maximum réglementaire des ovins (15 mg/kg MS), au risque d\'une intoxication.';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Le jeu d\'essai J2 ne doit pas être chargé en production.');
        }

        $this->call(JeuJ4Seeder::class);

        if (Elevage::query()->withoutGlobalScopes()->where('code_pseudo', self::CODE)->exists()) {
            return;
        }

        DB::transaction(function (): void {
            $technicien = User::query()->where('email', self::TECHNICIEN)->firstOrFail();
            $elevage = Elevage::query()->withoutGlobalScopes()->create(['code_pseudo' => self::CODE, 'departement' => '24', 'gds_id' => 24, 'especes' => '{OVI}', 'systeme_ovin' => 'viande', 'effectif_ovins' => 300]);
            DB::table('prive.elevage_identite')->insert(['elevage_id' => $elevage->id, 'raison_sociale' => 'GAEC des Brebis (J2, synthétique)', 'consentement_donnees' => true, 'consentement_date' => '2026-09-01']);
            Convention::query()->create(['elevage_id' => $elevage->id, 'gds_id' => 24, 'date_debut' => '2026-09-01', 'date_fin' => now()->addYear()->endOfYear()->toDateString()]);
            $eleveur = User::query()->firstOrCreate(['email' => self::ELEVEUR], ['nom' => 'Éleveur', 'prenom' => 'J2', 'password' => (string) config('authentification.mot_de_passe_j4'), 'telephone' => '06 00 00 00 02']);
            $eleveur->forceFill(['notice_rgpd_acceptee_le' => now()])->save();
            $eleveur->syncRoles(['eleveur']);
            $eleveur->elevages()->syncWithoutDetaching([$elevage->id => ['role' => ElevageAcces::ROLE_ELEVEUR]]);

            $visite = app(VisiteService::class)->creer($elevage, $technicien, '2026-11-05', 'suivi');
            $prelevement = $this->foinAnalyse($elevage, $technicien, $visite->id);
            $seau = app(EtiquettesService::class)->creer($elevage, $visite->id, ['nature' => 'seau', 'libelle_eleveur' => 'Seau à lécher « bovins »', 'ms_pct' => 100],
                ['CU' => ['valeur' => 300, 'unite' => 'mg/kg']]);
            app(EtiquettesService::class)->valider($seau);

            $stocks = app(StocksService::class);
            $inventaire = $stocks->creerInventaire($elevage, ['date_inventaire' => '2026-11-01', 'type' => 'fin_recolte', 'date_debut' => '2026-11-01', 'date_fin' => '2027-03-31', 'annee_seche' => false]);
            $foin = $stocks->enregistrerLot($inventaire, ['libelle' => 'Foin 2026', 'forme' => 'balle_ronde', 'famille_code' => 'FOIN', 'type_fourrage_code' => 'FOIN_PP',
                'nombre' => 300, 'poids_brut_kg' => 300, 'mode_stockage' => 'pertes.stockage.sous_toit', 'date_recolte' => '2026-06-15', 'prelevement_id' => $prelevement]);

            $troupeau = app(TroupeauService::class);
            $lot = $troupeau->creerLot($elevage, $inventaire, ['libelle' => 'Brebis allaitantes', 'categorie_code' => 'BREBIS_ALL']);
            $ration = $troupeau->ajouterPhase($lot, 'lactation', '2026-11-01', '2027-03-31', 300)->ration;
            $troupeau->ajouterComposant($ration, ['source' => 'lot_stock', 'lot_stock_id' => $foin->id, 'kg_ms' => 1.5]);
            $troupeau->ajouterComposant($ration, ['source' => 'etiquette', 'etiquette_id' => $seau->id, 'kg_ms' => 0.1]);

            $this->publierNiveau3($visite, $technicien);
        });
    }

    /** Foin analysé (Cu 5 mg/kg MS), rattaché à la visite ; interprétation validée (publiée avec la visite). */
    private function foinAnalyse(Elevage $elevage, User $technicien, int $visiteId): int
    {
        $prelevement = Prelevement::query()->withoutGlobalScopes()->create([
            'elevage_id' => $elevage->id, 'campagne_code' => '2025-2026', 'date_prelevement' => '2026-10-20', 'type_fourrage_code' => 'FOIN_PP', 'nom_lot' => 'Foin 2026', 'visite_id' => $visiteId,
        ]);
        $analyse = Analyse::query()->withoutGlobalScopes()->create([
            'prelevement_id' => $prelevement->id, 'laboratoire_id' => (int) DB::table('ref.laboratoire')->where('code', 'LANO')->value('id'),
            'num_echantillon_labo' => 'J2-FOIN', 'statut' => Analyse::STATUT_RECU,
        ]);
        $unites = DB::table('ref.parametre')->whereIn('code', ['MS', 'CU', 'MAT', 'UFL_2018'])->pluck('unite', 'code');
        foreach (['MS' => 85, 'CU' => 5, 'MAT' => 95, 'UFL_2018' => 0.68] as $code => $valeur) {
            DB::table('fourrage.resultat')->insert(['analyse_id' => $analyse->id, 'parametre_code' => $code, 'valeur' => $valeur, 'unite' => (string) $unites[$code], 'origine' => 'labo']);
        }
        app(ImportBulletinService::class)->controlerAnalyseChargee($analyse);
        $interpretations = app(InterpretationService::class);
        $i = $interpretations->creerBrouillon($analyse->refresh(), $technicien);
        $interpretations->enregistrer($i, new RedactionInterpretation(synthese: 'Foin de J2 (valeurs synthétiques).', niveauRetenu: $i->refresh()->niveau_propose));
        $interpretations->valider($i->refresh(), $technicien);

        return $prelevement->id;
    }

    private function publierNiveau3(Visite $visite, User $technicien): void
    {
        $service = app(InterpretationVisiteService::class);
        $iv = $service->creerBrouillon($visite, $technicien);
        $service->remplacerMessages($iv, [['gravite' => 3, 'texte' => self::MESSAGE]]);
        $service->enregistrer($iv, new RedactionVisite('Cuivre en excès chez les brebis', 'Le seau à lécher destiné aux bovins apporte trop de cuivre aux brebis.', null, 3), $technicien);
        $service->valider($iv->refresh(), $technicien);
        $service->viser($iv->refresh(), User::query()->where('email', self::VETERINAIRE_CONSEIL)->firstOrFail());
        $service->publier($iv->refresh(), $technicien);
    }
}
