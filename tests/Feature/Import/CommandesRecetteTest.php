<?php

use App\Models\App\ImportBulletin;
use App\Models\Fourrage\Analyse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// Outillage de recette J0 (§ 9.1, T-01) : import en lot, contrôle des analyses chargées par le script 03, bilan.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Storage::fake('bulletins');
    $this->jeu = JeuCloisonnement::creer();
});

it('importe un dossier de bulletins PDF par le circuit de l\'écran E19', function () {
    $this->artisan('bulletins:importer', [
        'dossier' => base_path('tests/Fixtures/lano'), '--gds' => 24, '--technicien' => 'technicien.24@j4.test', '--sync' => true,
    ])->expectsOutputToContain('4 fichier(s) déposé(s).')->assertSuccessful();

    expect(ImportBulletin::query()->withoutGlobalScopes()->pluck('statut')->unique()->all())->toBe(['a_rapprocher']);
});

it('refuse un import en lot sans GDS de rattachement du technicien', function () {
    $this->artisan('bulletins:importer', [
        'dossier' => base_path('tests/Fixtures/lano'), '--gds' => 47, '--technicien' => 'technicien.24@j4.test',
    ])->assertFailed();

    expect(ImportBulletin::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('contrôle les analyses chargées en base : ratios ajoutés, T-02 bloqué, autres contrôlées', function () {
    $normale = AnalyseEssai::creer($this->jeu->elevage24a, ['MS' => 88.0, 'CA' => 6.0, 'P' => 2.4, 'CU' => 5.0], Analyse::STATUT_RECU);
    $t02 = AnalyseEssai::creer($this->jeu->elevage24a, ['MS' => 86.0, 'NA' => 13.6, 'CU' => 57.9, 'MN' => 1765, 'FE' => 9012], Analyse::STATUT_RECU);
    $dejaControlee = AnalyseEssai::creer($this->jeu->elevage24b, ['MS' => 88.0]);

    $this->artisan('bulletins:controler-charges')->assertSuccessful();

    expect($normale->fresh()?->statut)->toBe('controle')
        ->and(DB::table('fourrage.resultat')->where('analyse_id', $normale->id)->where('parametre_code', 'CA_P')->value('origine'))->toBe('calcul_gds')
        ->and($t02->fresh()?->statut)->toBe('anomalie')
        ->and($t02->fresh()?->exclu_references)->toBeTrue()
        ->and(DB::table('fourrage.resultat')->where('analyse_id', $t02->id)->where('flag_anomalie', true)->count())->toBe(4)
        ->and($dejaControlee->fresh()?->statut)->toBe('controle');

    // Rejouable : une analyse déjà contrôlée n'est pas retraitée
    $this->artisan('bulletins:controler-charges')->assertSuccessful();
    expect(DB::table('fourrage.resultat')->where('analyse_id', $normale->id)->where('parametre_code', 'CA_P')->count())->toBe(1);
});

it('dresse le bilan de recette d\'une campagne (T-01)', function () {
    foreach ([['CU' => 4.0, 'MAT' => 61], ['CU' => 5.5], ['CU' => 8.0]] as $valeurs) {
        $analyse = AnalyseEssai::creer($this->jeu->elevage24a, $valeurs);
        DB::table('fourrage.prelevement')->where('id', $analyse->prelevement_id)->update(['campagne_code' => '2025-2026']);
    }

    $this->artisan('bulletins:bilan', ['--campagne' => '2025-2026'])
        ->expectsOutputToContain('Campagne 2025-2026 : 3 analyse(s).')
        ->expectsOutputToContain('Cuivre : 2 lot(s) sur 3 sous 7 mg/kg MS (67 %).')
        ->assertSuccessful();
});
