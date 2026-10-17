<?php

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\LotAnimaux;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Services\Troupeau\AnalyseRationService;
use App\Services\Troupeau\TroupeauService;
use Database\Seeders\JeuJ1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Recette du lot L5 sur J1 (§ 9.1, § 9.3 « saisie mobile d'un inventaire et d'une ration ») : stocks, besoins par les
// deux voies, apports de la ration des vaches (mêmes valeurs que T-13), écrans E12 et E13 de l'éleveur.
// Procédure : docs/recette/L5_recette_J1.md.
uses(RefreshDatabase::class);

beforeEach(function () {
    (new JeuJ1Seeder)->run();
    $this->elevage = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ1Seeder::CODE)->sole();
    $this->inventaire = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->where('statut', Inventaire::STATUT_COURANT)->sole();
    $this->eleveur = User::query()->where('email', JeuJ1Seeder::ELEVEUR)->sole();
});

it('charge J1 : stock utile 205,94 t MS, silo de RC-5 à 111,98 t MS dans un inventaire clos, rejouable (§ 9.1)', function () {
    (new JeuJ1Seeder)->run();
    $lots = LotStock::query()->withoutGlobalScopes()->where('inventaire_id', $this->inventaire->id)->pluck('stock_utile_t_ms', 'libelle')->map(fn ($v) => (float) $v);
    $clos = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->where('statut', Inventaire::STATUT_CLOS)->sole();
    $silo = (float) LotStock::query()->withoutGlobalScopes()->where('inventaire_id', $clos->id)->sole()->stock_utile_t_ms;

    expect(round($lots->sum(), 2))->toBe(205.94)
        ->and($lots->keys()->all())->not->toContain('Maïs ensilage — silo couloir')
        ->and($silo)->toEqualWithDelta(528 * 676.5 * 0.33 / 1000 * 0.95, 1e-6)
        ->and($lots['Foin 2025'])->toEqualWithDelta(40.0, 1e-9)
        ->and(Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->count())->toBe(2)
        ->and([$this->inventaire->date_debut->diffInDays($this->inventaire->date_fin) + 1, (float) $this->inventaire->marge_securite])->toBe([151.0, 0.15]);
});

it('calcule le besoin prélevé de J1 : 222,16 t MS (RC-8, § 9.1)', function () {
    $besoins = app(TroupeauService::class)->besoins($this->inventaire);

    expect(round($besoins['stock_t_ms'], 2))->toBe(222.16)
        ->and($besoins['ration_t_ms'])->toEqualWithDelta(193.28, 1e-6);
});

it('retrouve les apports de la ration des vaches de T-13 (foin et enrubannage pris par famille) ; la paille non analysée laisse la couverture des génisses non calculée', function () {
    $this->actingAs($this->eleveur);
    $analyse = app(AnalyseRationService::class);
    $vaches = LotAnimaux::query()->where('libelle', 'Vaches allaitantes')->sole();
    $genisses = LotAnimaux::query()->where('libelle', 'Génisses 1-2 ans')->sole();
    $a = $analyse->analyser($vaches->phases()->sole(), $vaches, true);
    $ufl = collect($a['elements'])->firstWhere('code', 'UFL_2018')['resultat'];

    expect($a['msi'])->toEqualWithDelta(11.98, 1e-9)
        ->and($a['complet'])->toBeTrue()
        ->and($ufl->apport)->toEqualWithDelta(4 * 0.67 + 7 * 0.80 + 0.88, 1e-6)
        ->and(collect($a['controles'])->firstWhere('code', 'CA_P')->valeur)->toEqualWithDelta((4 * 7.3 + 7 * 9.6 + 0.88 * 10 + 0.1 * 80) / (4 * 2.2 + 7 * 2.5 + 0.88 * 8 + 0.1 * 120), 1e-6)
        ->and($analyse->analyser($genisses->phases()->sole(), $genisses, true)['inconnus'])->toBe(['Paille et coproduits fibreux']);
});

it('ouvre à l\'éleveur de J1 la saisie de ses stocks et de son troupeau (E12, E13)', function () {
    $this->actingAs($this->eleveur);

    $this->get(route('stocks.inventaire', [$this->elevage->id, $this->inventaire->id]))->assertOk()
        ->assertSee('Foin 2025')->assertSee('MS analysée')->assertSee('Ajouter un lot');
    $this->get(route('troupeau', $this->elevage->id))->assertOk()
        ->assertSee('Vaches allaitantes')->assertSee('222,2')->assertSee('Apports et couverture');
});

it('refuse de charger J1 en production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new JeuJ1Seeder)->run())->toThrow(RuntimeException::class);
});
