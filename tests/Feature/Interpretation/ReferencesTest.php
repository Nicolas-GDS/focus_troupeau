<?php

use App\Domain\Interpretation\Position;
use App\Domain\Referentiels\Referentiels;
use App\Models\App\Consentement;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Prelevement;
use App\Services\Interpretation\PositionnementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\AnalyseEssai;

// Populations de référence et positionnement (RC-3, EF-M3-01 à EF-M3-04, T-07, T-08) ; historique (RC-4, EF-M3-05).
// Les vues valo du lot L4 ne retiennent que les bulletins « valide », non exclus, sans anomalie, d'élevages
// consentants ; n est toujours donné, le seuil references.n_min est appliqué par le domaine.
uses(RefreshDatabase::class);

function elevageConsentant(int $gds = 24): Elevage
{
    $elevage = Elevage::factory()->gds($gds)->create();
    Consentement::query()->withoutGlobalScopes()->create(['elevage_id' => $elevage->id, 'type' => Consentement::TYPE_REFERENCES, 'accorde_le' => now()]);

    return $elevage;
}

function lotReference(Elevage $elevage, float $mat, string $statut = Analyse::STATUT_VALIDE, string $type = 'FOIN_PP', string $campagne = '2025-2026'): Analyse
{
    return AnalyseEssai::creer($elevage, ['MAT' => $mat, 'CU' => 4.0], $statut, $type, campagne: $campagne,
        datePrelevement: $campagne === '2025-2026' ? '2026-06-15' : '2025-06-15');
}

/** @return list<array{population: string, n: int, position: Position}> */
function populationsMat(Analyse $analyse): array
{
    $ligne = collect(app(PositionnementService::class)->positionner($analyse))->firstWhere('code', 'MAT');

    return array_map(fn ($p) => ['population' => $p['reference']->population, 'n' => $p['reference']->n, 'position' => $p['position']], $ligne['populations']);
}

beforeEach(function () {
    $this->lot = lotReference(elevageConsentant(), 55);
    foreach ([60, 62, 66] as $mat) {
        lotReference(elevageConsentant(), $mat);
    }
});

it('T-07 : déclare la référence insuffisante avec 4 lots, puis affiche les quartiles avec 5', function () {
    expect(populationsMat($this->lot))->toBe([
        ['population' => 'departementale', 'n' => 4, 'position' => Position::Insuffisante],
        ['population' => 'bigds', 'n' => 4, 'position' => Position::Insuffisante],
    ]);

    lotReference(elevageConsentant(), 70);
    $refs = app(PositionnementService::class)->references(Prelevement::query()->withoutGlobalScopes()->find($this->lot->prelevement_id), 'MAT');

    // percentile_cont sur 55, 60, 62, 66, 70 : Q1 60, médiane 62, Q3 66 ; le lot fait partie de la population
    expect([$refs['departementale']->n, $refs['departementale']->q1, $refs['departementale']->mediane, $refs['departementale']->q3])->toBe([5, 60.0, 62.0, 66.0])
        ->and(populationsMat($this->lot))->toBe([['population' => 'departementale', 'n' => 5, 'position' => Position::Faible]]);
});

it('n\'admet que les bulletins valides, non exclus, sans anomalie, de la campagne, du type et du GDS (EF-M3-04)', function () {
    lotReference(elevageConsentant(), 99, Analyse::STATUT_CONTROLE);
    $exclu = lotReference(elevageConsentant(), 98);
    $exclu->update(['exclu_references' => true]);
    $anomalie = lotReference(elevageConsentant(), 97);
    DB::table('fourrage.resultat')->where('analyse_id', $anomalie->id)->where('parametre_code', 'MAT')->update(['flag_anomalie' => true]);
    lotReference(elevageConsentant(), 96, type: 'FOIN_LUZ');
    lotReference(elevageConsentant(47), 95);

    expect(populationsMat($this->lot))->toBe([
        ['population' => 'departementale', 'n' => 4, 'position' => Position::Insuffisante],
        ['population' => 'bigds', 'n' => 5, 'position' => Position::Faible],
    ]);
});

it('positionne le lot d\'un élevage sans consentement sans le compter dans la population', function () {
    $sansConsentement = lotReference(Elevage::factory()->gds(24)->create(), 50);
    lotReference(elevageConsentant(), 70);

    expect(populationsMat($sansConsentement))->toBe([['population' => 'departementale', 'n' => 5, 'position' => Position::Faible]]);
});

it('T-08 : retire les lots d\'un élevage dès la révocation de son consentement « références »', function () {
    $elevage = elevageConsentant();
    lotReference($elevage, 70);
    expect(populationsMat($this->lot)[0]['n'])->toBe(5);

    Consentement::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)->update(['revoque_le' => now()]);

    expect(populationsMat($this->lot)[0]['n'])->toBe(4)
        ->and(populationsMat($this->lot)[0]['position'])->toBe(Position::Insuffisante);
});

it('ne positionne que les paramètres suivis et dosés, et fournit les valeurs anonymes du graphique', function () {
    DB::table('ref.parametre')->where('code', 'CU')->update(['suivi_references' => false]);
    app(Referentiels::class)->oublier();
    $prelevement = Prelevement::query()->withoutGlobalScopes()->find($this->lot->prelevement_id);

    expect(collect(app(PositionnementService::class)->positionner($this->lot))->pluck('code')->all())->toBe(['MAT'])
        ->and(app(PositionnementService::class)->valeursPopulation($prelevement, 'MAT'))->toBe([55.0, 60.0, 62.0, 66.0]);
});

it('donne la série pluriannuelle de l\'élevage et sa variation, sans condition de consentement (RC-4)', function () {
    DB::table('fourrage.campagne')->insert(['code' => '2024-2025', 'libelle' => 'Campagne 2024-2025', 'date_debut' => '2024-09-01', 'date_fin' => '2025-08-31']);
    $elevage = Elevage::factory()->gds(24)->create();
    lotReference($elevage, 50, campagne: '2024-2025');
    lotReference($elevage, 60);
    lotReference($elevage, 70);
    lotReference($elevage, 99, Analyse::STATUT_CONTROLE);

    $historique = app(PositionnementService::class)->historique($elevage->id, 'FOIN_PP', 'MAT');

    expect(array_map(fn ($p) => [$p->campagne, $p->valeur, $p->n], $historique['points']))->toBe([['2024-2025', 50.0, 1], ['2025-2026', 65.0, 2]])
        ->and($historique['variation']->ecart)->toBe(15.0)
        ->and($historique['variation']->pourcentage)->toBe(30.0);
});
