<?php

use App\Domain\Interpretation\Position;
use App\Models\App\User;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Prelevement;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\PositionnementService;
use App\Services\Interpretation\RedactionInterpretation;
use Database\Seeders\JeuJ3Seeder;
use Database\Seeders\JeuJ5Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

// Recette du lot L4 en développement (§ 9.1 J5, § 9.3 « graphiques de positionnement ») : population synthétique J5,
// lots de J3 positionnés contre elle, bilan des références (T-07 sur le GDS 47, point ouvert 14), historique de J5.
// Procédure sur J0 : docs/recette/L4_recette_J0.md.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    (new JeuJ5Seeder)->run();
});

/** Interprète et valide les neuf lots de J3 (statut « valide »), sans publication. */
function validerLotsJ3PourL4(): void
{
    (new JeuJ3Seeder)->run();
    $technicien = User::query()->where('email', JeuJ3Seeder::TECHNICIEN)->sole();
    $service = app(InterpretationService::class);

    foreach (Analyse::query()->withoutGlobalScopes()->where('num_echantillon_labo', 'like', 'J3-%')->get() as $analyse) {
        $i = $service->creerBrouillon($analyse, $technicien);
        $service->enregistrer($i, new RedactionInterpretation(synthese: 'Recette L4.', niveauRetenu: 2));
        $service->valider($i->refresh(), $technicien);
    }
}

it('charge J5 : élevages consentants, bulletins validés non publiés, populations attendues, rejouable', function () {
    (new JeuJ5Seeder)->run();
    $j5 = DB::table('fourrage.analyse')->where('num_echantillon_labo', 'like', 'J5-%');
    $mat = fn (string $vue, array $filtre) => DB::table($vue)->where(['campagne_code' => '2025-2026', 'parametre_code' => 'MAT', ...$filtre])->value('n');

    expect(Elevage::query()->withoutGlobalScopes()->where('code_pseudo', 'like', 'J5-%')->count())->toBe(11)
        ->and(DB::table('app.consentement')->where('type', 'references')->count())->toBe(11)
        ->and((clone $j5)->count())->toBe(47)
        ->and((clone $j5)->pluck('statut')->unique()->all())->toBe(['valide'])
        ->and(DB::table('fourrage.interpretation')->where('statut', 'publie')->count())->toBe(0)
        ->and($mat('valo.v_reference_gds', ['gds_id' => 24, 'type_fourrage_code' => 'FOIN_PP']))->toBe(15)
        ->and($mat('valo.v_reference_gds', ['gds_id' => 24, 'type_fourrage_code' => 'ENSIL_SORGHO']))->toBe(2)
        ->and($mat('valo.v_reference_bigds', ['type_fourrage_code' => 'ENSIL_SORGHO']))->toBe(5);
});

it('refuse de charger J5 en production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new JeuJ5Seeder)->run())->toThrow(RuntimeException::class);
});

it('positionne les lots de J3 contre J5 sans les compter, faute de consentement d\'ELV-0001 (RC-3)', function () {
    validerLotsJ3PourL4();
    $foin = Analyse::query()->withoutGlobalScopes()->where('num_echantillon_labo', 'J3-07')->sole();
    $sorgho = Analyse::query()->withoutGlobalScopes()->where('num_echantillon_labo', 'J3-01')->sole();
    $populations = fn (Analyse $a) => collect(app(PositionnementService::class)->positionner($a))->firstWhere('code', 'MAT')['populations'];

    $foinMat = $populations($foin);
    expect($foinMat)->toHaveCount(1)
        ->and($foinMat[0]['reference']->n)->toBe(15)
        ->and($foinMat[0]['position'])->toBe(Position::Faible);

    // sorgho : départementale insuffisante (n = 2), repli bi-GDS (n = 5)
    expect(array_map(fn ($p) => [$p['reference']->population, $p['reference']->n], $populations($sorgho)))->toBe([['departementale', 2], ['bigds', 5]])
        ->and(app(PositionnementService::class)->references(Prelevement::query()->withoutGlobalScopes()->find($foin->prelevement_id), 'MAT')['departementale']->n)->toBe(15);
});

it('dresse le bilan des références : T-07 au GDS 47, lots sans consentement signalés', function () {
    validerLotsJ3PourL4();

    $this->artisan('references:bilan', ['--campagne' => '2025-2026'])
        ->expectsOutputToContain('référence insuffisante (n = 4)')
        ->expectsOutputToContain('9 bulletin(s) valide(s) n\'entrent pas dans les références faute de consentement')
        ->expectsOutputToContain('Population lithologique : aucune')
        ->assertSuccessful();
});

it('donne à l\'élevage J5-24-01 un historique de foin sur deux campagnes (RC-4)', function () {
    $elevage = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', 'J5-24-01')->sole();
    $historique = app(PositionnementService::class)->historique($elevage->id, 'FOIN_PP', 'MAT');

    expect(array_map(fn ($p) => $p->campagne, $historique['points']))->toBe(['2024-2025', '2025-2026'])
        ->and($historique['variation'])->not->toBeNull();

    $this->actingAs(User::query()->where('email', 'technicien.24@j4.test')->sole())
        ->get(route('dossier.historique', $elevage->id))->assertOk()->assertSee('Entre 2024-2025 et 2025-2026');
});
