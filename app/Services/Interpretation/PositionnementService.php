<?php

namespace App\Services\Interpretation;

use App\Domain\Interpretation\Historique;
use App\Domain\Interpretation\PointHistorique;
use App\Domain\Interpretation\Position;
use App\Domain\Interpretation\Positionnement;
use App\Domain\Interpretation\ReferenceQuartiles;
use App\Domain\Interpretation\Variation;
use App\Domain\Referentiels\Referentiels;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Prelevement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Positionnement d'un lot dans les populations de référence (RC-3, EF-M3-01 à EF-M3-04) et historique pluriannuel
 * d'un élevage (RC-4, EF-M3-05). Les populations sont lues dans les vues valo du lot L4 (bulletins « valide »,
 * consentement « references » actif, hors exclusions et anomalies) ; le classement et le seuil n_min relèvent du
 * domaine. Aucune donnée d'identité ne sort de ces vues : seules des valeurs anonymes servent aux graphiques.
 */
class PositionnementService
{
    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly Positionnement $positionnement = new Positionnement,
        private readonly Historique $historique = new Historique,
    ) {}

    public function nMin(?int $gdsId = null): int
    {
        $valeur = $this->referentiels->parametre('references.n_min', $gdsId);

        if ($valeur === null) {
            throw new RuntimeException('Paramètre references.n_min absent de app.parametre_defaut.');
        }

        return (int) $valeur;
    }

    /**
     * Les trois populations d'un prélèvement pour un paramètre (null si vide).
     *
     * @return array{departementale: ReferenceQuartiles|null, lithologique: ReferenceQuartiles|null, bigds: ReferenceQuartiles|null}
     */
    public function references(Prelevement $prelevement, string $parametre): array
    {
        $vide = ['departementale' => null, 'lithologique' => null, 'bigds' => null];

        if ($prelevement->campagne_code === null) {
            return $vide;
        }

        $gdsId = $this->gdsDe($prelevement);
        $commun = ['campagne_code' => $prelevement->campagne_code, 'type_fourrage_code' => $prelevement->type_fourrage_code, 'parametre_code' => $parametre];
        $litho = DB::table('valo.mv_prelevement_contexte')->where('prelevement_id', $prelevement->id)->value('classe_litho_gds');

        return [
            'departementale' => $this->quartiles(ReferenceQuartiles::DEPARTEMENTALE, DB::table('valo.v_reference_gds')->where($commun)->where('gds_id', $gdsId)->first()),
            'lithologique' => $litho === null ? null : $this->quartiles(ReferenceQuartiles::LITHOLOGIQUE,
                DB::table('valo.v_reference_litho')->where($commun)->where('gds_id', $gdsId)->where('classe_litho_gds', $litho)->first()),
            'bigds' => $this->quartiles(ReferenceQuartiles::BIGDS, DB::table('valo.v_reference_bigds')->where($commun)->first()),
        ];
    }

    /**
     * Positionnement d'un bulletin sur les paramètres suivis et dosés, populations dans l'ordre d'affichage de RC-3.
     *
     * @return list<array{code: string, valeur: float, populations: list<array{reference: ReferenceQuartiles, position: Position}>}>
     */
    public function positionner(Analyse $analyse): array
    {
        $prelevement = Prelevement::query()->withoutGlobalScopes()->findOrFail($analyse->prelevement_id);
        $nMin = $this->nMin($this->gdsDe($prelevement));
        $valeurs = DB::table('fourrage.resultat')->where('analyse_id', $analyse->id)->whereNotNull('valeur')
            ->whereIn('parametre_code', $this->referentiels->parametresSuivis())->pluck('valeur', 'parametre_code');
        $lignes = [];

        foreach ($this->referentiels->parametresSuivis() as $code) {
            if (! isset($valeurs[$code])) {
                continue;
            }

            $valeur = (float) $valeurs[$code];
            $refs = $this->references($prelevement, $code);
            $lignes[] = [
                'code' => $code,
                'valeur' => $valeur,
                'populations' => array_map(fn (ReferenceQuartiles $r) => ['reference' => $r, 'position' => $this->positionnement->classer($valeur, $r, $nMin)],
                    $this->positionnement->populationsAffichees($refs['departementale'], $refs['lithologique'], $refs['bigds'], $nMin)),
            ];
        }

        return $lignes;
    }

    /**
     * Valeurs anonymes de la population départementale (points du graphique de positionnement, EF-M3-03).
     *
     * @return list<float>
     */
    public function valeursPopulation(Prelevement $prelevement, string $parametre): array
    {
        if ($prelevement->campagne_code === null) {
            return [];
        }

        return DB::table('valo.v_reference_population')->where([
            'campagne_code' => $prelevement->campagne_code, 'gds_id' => $this->gdsDe($prelevement),
            'type_fourrage_code' => $prelevement->type_fourrage_code, 'parametre_code' => $parametre,
        ])->orderBy('valeur')->pluck('valeur')->map(fn ($v) => (float) $v)->values()->all();
    }

    /**
     * Série pluriannuelle d'un élevage pour un type de fourrage et un paramètre, et variation entre les deux dernières
     * campagnes (RC-4).
     *
     * @return array{points: list<PointHistorique>, variation: Variation|null}
     */
    public function historique(string $elevageId, string $typeFourrage, string $parametre): array
    {
        $points = DB::table('valo.v_historique_elevage')
            ->where(['elevage_id' => $elevageId, 'type_fourrage_code' => $typeFourrage, 'parametre_code' => $parametre])
            ->get()->map(fn ($l) => new PointHistorique((string) $l->campagne_code, (float) $l->mediane, (int) $l->n))->values()->all();

        return ['points' => $this->historique->serie($points), 'variation' => $this->historique->variation($points)];
    }

    private function quartiles(string $population, ?object $ligne): ?ReferenceQuartiles
    {
        return $ligne === null ? null : new ReferenceQuartiles($population, (int) $ligne->n, (float) $ligne->q1, (float) $ligne->mediane,
            (float) $ligne->q3, (float) $ligne->minimum, (float) $ligne->maximum);
    }

    private function gdsDe(Prelevement $prelevement): int
    {
        return (int) DB::table('fourrage.elevage')->where('id', $prelevement->elevage_id)->value('gds_id');
    }
}
