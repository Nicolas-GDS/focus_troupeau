<?php

namespace Database\Seeders;

use App\Models\App\Consentement;
use App\Models\App\User;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Prelevement;
use App\Services\Import\ImportBulletinService;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Jeu d'essai J5 — population de référence synthétique (§ 9.1, décision du 16/10/2026), pour voir le positionnement
 * et l'historique en développement (J0 n'existe qu'en recette). Élevages fictifs consentants (« references ») :
 * huit au GDS 24, trois au GDS 47 ; lots de la campagne 2025-2026 autour des types de J3, dont 15 foins de prairie
 * permanente au GDS 24 (17 avec les deux de J3, comme la population du prototype du document 04) ; un ensilage de
 * sorgho trop rare au GDS 24 pour être positionné seul (repli bi-GDS) ; un foin de la campagne 2024-2025 pour
 * l'historique.
 *
 * Les valeurs sont synthétiques et reproductibles (générateur à graine fixe, ± 15 % autour de centres par type, ± 4 points de MS, ± 15 g de BPR) ;
 * elles ne constituent pas une référence. Chaque bulletin passe le contrôle (RC-1, RC-2) et une interprétation validée
 * par le technicien du GDS (statut « valide », sans publication) : il entre ainsi dans les références (RC-3).
 * Rejouable, interdit en production. Les agents sont ceux de J4.
 */
class JeuJ5Seeder extends Seeder
{
    /** @var array<int, int> nombre d'élevages par GDS */
    public const ELEVAGES = [24 => 8, 47 => 3];

    /** @var array<int, array<string, int>> lots de la campagne 2025-2026 par GDS et par type */
    public const LOTS = [
        24 => ['FOIN_PP' => 15, 'FOIN_ASSOC' => 6, 'ENRUB_LUZ' => 6, 'FOIN_LUZ' => 5, 'ENRUB_HERBE' => 5, 'ENSIL_SORGHO' => 2],
        47 => ['FOIN_PP' => 4, 'ENSIL_SORGHO' => 3],
    ];

    /** @var array<string, array<string, int|float>> centres synthétiques par type (± 15 % ; MS ± 4 points ; BPR ± 15 g) */
    private const CENTRES = [
        'FOIN_PP' => ['MS' => 89, 'MAT' => 66, 'UFL_2018' => 0.70, 'PDI' => 66, 'BPR' => -35, 'CU' => 3.5, 'ZN' => 22, 'MN' => 110, 'NA' => 1.0, 'MG' => 1.6, 'K' => 15, 'FE' => 250, 'MM' => 65],
        'FOIN_ASSOC' => ['MS' => 89, 'MAT' => 140, 'UFL_2018' => 0.75, 'PDI' => 80, 'BPR' => 15, 'CU' => 6.2, 'ZN' => 20, 'MN' => 70, 'NA' => 1.2, 'MG' => 1.9, 'K' => 21, 'FE' => 130, 'MM' => 80],
        'ENRUB_LUZ' => ['MS' => 62, 'MAT' => 150, 'UFL_2018' => 0.84, 'PDI' => 82, 'BPR' => 20, 'CU' => 6.0, 'ZN' => 20, 'MN' => 75, 'NA' => 1.0, 'MG' => 1.7, 'K' => 26, 'FE' => 160, 'MM' => 80],
        'FOIN_LUZ' => ['MS' => 88, 'MAT' => 165, 'UFL_2018' => 0.68, 'PDI' => 80, 'BPR' => 25, 'CU' => 6.5, 'ZN' => 18, 'MN' => 50, 'NA' => 1.0, 'MG' => 2.2, 'K' => 20, 'FE' => 140, 'MM' => 85],
        'ENRUB_HERBE' => ['MS' => 55, 'MAT' => 105, 'UFL_2018' => 0.80, 'PDI' => 70, 'BPR' => -15, 'CU' => 4.5, 'ZN' => 22, 'MN' => 70, 'NA' => 1.0, 'MG' => 1.5, 'K' => 24, 'FE' => 250, 'MM' => 75],
        'ENSIL_SORGHO' => ['MS' => 24, 'MAT' => 80, 'CU' => 6.0, 'ZN' => 35, 'MN' => 80, 'NA' => 0.3, 'MG' => 1.8, 'K' => 16, 'FE' => 150, 'MM' => 60],
    ];

    private const GRAINE = 20261016;

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Le jeu d\'essai J5 ne doit pas être chargé en production.');
        }

        $this->call(JeuJ4Seeder::class);

        if (Elevage::query()->withoutGlobalScopes()->where('code_pseudo', 'like', 'J5-%')->exists()) {
            return;
        }

        DB::transaction(fn () => $this->charger());
    }

    private function charger(): void
    {
        mt_srand(self::GRAINE);
        DB::table('fourrage.campagne')->insertOrIgnore(['code' => '2024-2025', 'libelle' => 'Campagne 2024-2025', 'date_debut' => '2024-09-01', 'date_fin' => '2025-08-31']);
        $decimales = DB::table('ref.parametre')->pluck('decimales', 'code');
        $numero = 0;

        foreach (self::LOTS as $gds => $types) {
            $technicien = User::query()->where('email', 'technicien.'.$gds.'@j4.test')->firstOrFail();
            $elevages = $this->elevages($gds);
            $i = 0;

            foreach ($types as $type => $nombre) {
                for ($k = 0; $k < $nombre; $k++, $i++) {
                    $this->lot($elevages[$i % count($elevages)], $type, '2025-2026', '2026-06-15', ++$numero, $decimales->all(), $technicien);
                }
            }

            if ($gds === 24) {
                $this->lot($elevages[0], 'FOIN_PP', '2024-2025', '2025-06-15', ++$numero, $decimales->all(), $technicien);
            }
        }

        mt_srand();
    }

    /** @return list<Elevage> */
    private function elevages(int $gds): array
    {
        $elevages = [];

        for ($n = 1; $n <= self::ELEVAGES[$gds]; $n++) {
            $elevage = Elevage::query()->withoutGlobalScopes()->create([
                'code_pseudo' => sprintf('J5-%d-%02d', $gds, $n), 'departement' => (string) $gds, 'gds_id' => $gds,
            ]);
            Consentement::query()->withoutGlobalScopes()->create(['elevage_id' => $elevage->id, 'type' => Consentement::TYPE_REFERENCES, 'accorde_le' => now()]);
            $elevages[] = $elevage;
        }

        return $elevages;
    }

    /**
     * @param  array<string, int>  $decimales
     */
    private function lot(Elevage $elevage, string $type, string $campagne, string $date, int $numero, array $decimales, User $technicien): void
    {
        $prelevement = Prelevement::query()->withoutGlobalScopes()->create([
            'elevage_id' => $elevage->id, 'campagne_code' => $campagne, 'date_prelevement' => $date, 'type_fourrage_code' => $type,
            'nom_lot' => 'Lot synthétique '.$numero,
        ]);
        $analyse = Analyse::query()->withoutGlobalScopes()->create([
            'prelevement_id' => $prelevement->id,
            'laboratoire_id' => (int) DB::table('ref.laboratoire')->where('code', 'LANO')->value('id'),
            'num_echantillon_labo' => sprintf('J5-%03d', $numero),
            'statut' => Analyse::STATUT_RECU,
        ]);

        $unites = DB::table('ref.parametre')->whereIn('code', array_keys(self::CENTRES[$type]))->pluck('unite', 'code');
        foreach (self::CENTRES[$type] as $code => $centre) {
            $ecart = match ($code) {
                'BPR' => 15.0, 'MS' => 4.0, default => abs($centre) * 0.15
            };
            DB::table('fourrage.resultat')->insert([
                'analyse_id' => $analyse->id, 'parametre_code' => $code, 'unite' => (string) $unites[$code], 'origine' => 'labo',
                'valeur' => round($centre + (mt_rand(-1000, 1000) / 1000) * $ecart, $decimales[$code] ?? 1),
            ]);
        }

        if (app(ImportBulletinService::class)->controlerAnalyseChargee($analyse) !== Analyse::STATUT_CONTROLE) {
            throw new RuntimeException("Bulletin synthétique {$analyse->num_echantillon_labo} hors des plages de plausibilité.");
        }

        $interpretations = app(InterpretationService::class);
        $interpretation = $interpretations->creerBrouillon($analyse->refresh(), $technicien);
        $interpretations->enregistrer($interpretation, new RedactionInterpretation(synthese: 'Lot synthétique du jeu J5 (développement).',
            niveauRetenu: $interpretation->refresh()->niveau_propose));
        $interpretations->valider($interpretation->refresh(), $technicien);
    }
}
