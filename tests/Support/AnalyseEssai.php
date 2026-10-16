<?php

namespace Tests\Support;

use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Prelevement;
use Illuminate\Support\Facades\DB;

/**
 * Bulletin d'essai créé directement en base (sans passer par l'import) : un prélèvement, une analyse au statut donné
 * et ses résultats, l'unité de chaque résultat étant celle du référentiel des paramètres.
 */
final class AnalyseEssai
{
    /**
     * @param  array<string, float>  $valeurs  code paramètre → valeur
     */
    public static function creer(
        Elevage $elevage,
        array $valeurs,
        string $statut = Analyse::STATUT_CONTROLE,
        string $typeFourrage = 'FOIN_PP',
        ?string $destination = null,
        ?string $campagne = null,
        string $datePrelevement = '2026-06-15',
    ): Analyse {
        static $numero = 0;
        $numero++;

        $prelevement = Prelevement::query()->withoutGlobalScopes()->create([
            'elevage_id' => $elevage->id,
            'campagne_code' => $campagne,
            'date_prelevement' => $datePrelevement,
            'type_fourrage_code' => $typeFourrage,
            'nom_lot' => 'Lot essai '.$numero,
            'destination_categorie_code' => $destination,
        ]);

        $analyse = Analyse::query()->withoutGlobalScopes()->create([
            'prelevement_id' => $prelevement->id,
            'laboratoire_id' => (int) DB::table('ref.laboratoire')->where('code', 'LANO')->value('id'),
            'num_echantillon_labo' => 'ESSAI-'.$numero.'-'.uniqid(),
            'statut' => $statut,
        ]);

        foreach ($valeurs as $code => $valeur) {
            DB::table('fourrage.resultat')->insert([
                'analyse_id' => $analyse->id,
                'parametre_code' => $code,
                'valeur' => $valeur,
                'unite' => (string) DB::table('ref.parametre')->where('code', $code)->value('unite'),
                'origine' => 'labo',
            ]);
        }

        return $analyse;
    }
}
