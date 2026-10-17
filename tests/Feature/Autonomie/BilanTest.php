<?php

use App\Models\Fourrage\Elevage;
use App\Models\Plan\Bilan;
use App\Models\Plan\LevierLigne;
use App\Models\Plan\Scenario;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Services\Autonomie\BilanService;
use App\Services\Stocks\StocksService;
use Database\Seeders\JeuJ1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

// Bilan d'autonomie mémorisé (EF-M6-01 à EF-M6-05, RC-11, RC-13.4, RC-14) sur J1 : mêmes valeurs que T-14 et T-16
// (calculs/test_calculs.py), recalcul après chaque changement des stocks (T-20), scénario appliqué virtuellement.
uses(RefreshDatabase::class);

beforeEach(function () {
    (new JeuJ1Seeder)->run();
    $this->elevage = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ1Seeder::CODE)->sole();
    $this->inventaire = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->where('statut', Inventaire::STATUT_COURANT)->sole();
    $this->lot = fn (string $libelle) => LotStock::query()->withoutGlobalScopes()->where('inventaire_id', $this->inventaire->id)->where('libelle', $libelle)->sole();
});

it('mémorise le bilan de J1 au chargement : valeurs du § 9.1 et de T-14', function () {
    $bilan = app(BilanService::class)->dernier($this->elevage->id);
    $foin25 = ($this->lot)('Foin 2025');

    expect($bilan)->not->toBeNull()
        ->and(round((float) $bilan->stock_utile_t_ms, 2))->toBe(205.94)
        ->and(round((float) $bilan->besoin_t_ms, 2))->toBe(222.16)
        ->and(round((float) $bilan->securite_t_ms, 2))->toBe(33.32)
        ->and(round((float) $bilan->bilan_t_ms, 2))->toBe(-49.54)
        ->and($bilan->autonomie_jours)->toBe(139)
        ->and($bilan->date_rupture->toDateString())->toBe('2026-12-23')
        ->and($bilan->detail['rupture_famille'])->toMatchArray(['ENRUB' => '2026-12-23', 'FOIN' => null])
        ->and($bilan->detail['epuisement_lot']['L'.$foin25->id])->toBe('2027-01-03')
        ->and($bilan->detail['rupture_lot']['L'.$foin25->id])->toBeNull()
        ->and($bilan->detail['mensuel'])->toHaveCount(5)
        ->and($bilan->alerte_envoyee)->toBeFalse();
});

it('encadre la rupture par les bornes d\'incertitude des stocks (RC-14)', function () {
    $bilan = app(BilanService::class)->dernier($this->elevage->id);

    expect((float) $bilan->incertitude_rel)->toBeGreaterThan(0.0)->toBeLessThan(1.0)
        ->and($bilan->date_rupture_min->lte($bilan->date_rupture))->toBeTrue()
        ->and($bilan->date_rupture_max->gte($bilan->date_rupture))->toBeTrue()
        ->and($bilan->date_rupture_min->lt($bilan->date_rupture_max))->toBeTrue();
});

it('recalcule le bilan après un mouvement : l\'achat repousse la rupture, le re-comptage enregistre l\'écart (T-14n à p, T-20)', function () {
    $stocks = app(StocksService::class);
    $stocks->ajouterMouvement(($this->lot)('Enrubannage 2026'), 'achat', '2026-12-15', 30.0);
    $stocks->ajouterMouvement(($this->lot)('Foin 2026'), 'recomptage', '2027-01-15', 50.0, motif: 'Re-comptage des balles');
    $bilan = app(BilanService::class)->dernier($this->elevage->id);

    expect($bilan->date_rupture->toDateString())->toBe('2027-01-30')
        // EF-M7-05 : l'achat met le bilan à jour ; le re-comptage reste un écart de recalage, hors apports
        ->and(round((float) $bilan->stock_utile_t_ms, 2))->toBe(235.94)
        ->and($bilan->autonomie_jours)->toBe((int) floor(235.94 / (222.16 / 151)))
        ->and($bilan->detail['ecarts'])->toHaveCount(1)
        ->and(round($bilan->detail['ecarts'][0]['ecart'], 2))->toBe(41.53);
});

it('applique un scénario virtuellement, sans toucher au bilan de l\'inventaire (RC-13.4)', function () {
    $scenario = Scenario::query()->create(['inventaire_id' => $this->inventaire->id, 'libelle' => 'Achat d\'enrubannage', 'origine' => 'utilisateur']);
    LevierLigne::query()->create(['scenario_id' => $scenario->id, 'ordre' => 1, 'levier_code' => 'achat_fourrage', 'famille_code' => 'ENRUB',
        'quantite' => 30, 'unite' => 't MS', 'date_effet' => '2026-12-15', 'gain_t_ms' => 30, 'prix_unitaire' => 190, 'cout_eur' => 5700]);
    $service = app(BilanService::class);
    $simule = $service->enregistrer($this->inventaire, $scenario);
    LevierLigne::query()->create(['scenario_id' => $scenario->id, 'ordre' => 2, 'levier_code' => 'reaffectation', 'famille_code' => 'ENRUB',
        'famille_source_code' => 'FOIN', 'quantite' => 10, 'unite' => 't MS', 'gain_t_ms' => 10]);

    expect($simule->date_rupture->toDateString())->toBe('2027-01-30')
        // RC-13.4 : stock corrigé S' = S + Σ gains
        ->and(round((float) $simule->stock_utile_t_ms, 2))->toBe(235.94)
        ->and(round((float) $simule->bilan_t_ms, 2))->toBe(-19.54)
        ->and(Bilan::query()->withoutGlobalScopes()->where('inventaire_id', $this->inventaire->id)->count())->toBe(2)
        ->and($service->dernier($this->elevage->id)->date_rupture->toDateString())->toBe('2026-12-23')
        ->and($service->calculer($this->inventaire, $scenario->refresh())['stocks_famille'])
        ->toMatchArray(['ENRUB' => 42.55875 + 30 + 10, 'FOIN' => (float) ($this->lot)('Foin 2025')->stock_utile_t_ms + (float) ($this->lot)('Foin 2026')->stock_utile_t_ms - 10]);
});

it('calcule l\'incertitude d\'un lot de stock comme T-16 (RC-14)', function () {
    $stocks = app(StocksService::class);

    expect($stocks->incertitude($this->inventaire, ['forme' => 'silo', 'ouvrage' => 'couloir', 'methode_densite' => 'equation', 'famille_code' => 'ENSIL_MAIS',
        'ms_pct' => 33, 'ms_origine' => 'analyse', 'pertes_pct' => 0.05]))->toEqualWithDelta(sqrt(0.05 ** 2 + 0.07 ** 2 + (2 / 33) ** 2 + 0.025 ** 2), 1e-12)
        ->and($stocks->incertitude($this->inventaire, ['forme' => 'balle_ronde', 'famille_code' => 'FOIN', 'ms_pct' => 85, 'ms_origine' => 'defaut',
            'pertes_pct' => 0.03, 'poids_mesure' => false]))->toEqualWithDelta(sqrt(0.15 ** 2 + (5 / 85) ** 2 + 0.015 ** 2), 1e-12)
        ->and((float) ($this->lot)('Paille')->incertitude_rel)->toEqualWithDelta(sqrt(0.15 ** 2 + (5 / 90) ** 2 + 0.015 ** 2), 1e-9);
});

it('recalcule le bilan quand un lot est supprimé, et ignore l\'inventaire clos du silo', function () {
    app(StocksService::class)->supprimerLot(($this->lot)('Paille'));
    $bilan = app(BilanService::class)->dernier($this->elevage->id);
    $clos = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->where('statut', Inventaire::STATUT_CLOS)->sole();

    expect(round((float) $bilan->stock_utile_t_ms, 2))->toBe(round(205.94 - 24.44, 2))
        ->and($bilan->detail['rupture_famille']['PAILLE'])->toBe('2026-11-01')
        ->and(app(BilanService::class)->calculer($clos)['centrale']->besoinStock)->toBe(0.0);
});
