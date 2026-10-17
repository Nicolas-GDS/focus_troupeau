<?php

use App\Livewire\Stocks\Inventaires;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Services\Dossier\SaisieRefusee;
use App\Services\Stocks\ImportCalculateurService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// T-28 (EF-M9-06) : reprise d'un bilan du calculateur HTML (docs/calculateur/, contenu « gds-autonomie-fourragere-v1 »,
// exemple intégré au calculateur) ; inventaire créé avec les mêmes stocks utiles que le calculateur.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->actingAs($this->jeu->technicien24);
    $this->contenu = (string) file_get_contents(base_path('tests/Fixtures/calculateur/exemple_v1.json'));
});

/** Stocks utiles du calculateur pour son exemple (formules de calc() du calculateur). */
function stocksCalculateur(): array
{
    $mais = (18 + 0.5 * 6) * 8 * 2.4 * ((929 - 17.5 * 33 + 7.9 * 30 + 44 * 2.4) * 33 / 100) / 1000 * 0.95;
    $herbe = (12 + 0.5 * 5) * 6 * 1.6 * (27 * 1.6 + 1.8 * 28 + 54) / 1000 * 0.92;

    return [
        'Maïs — silo couloir' => $mais, 'Herbe — taupinière' => $herbe,
        'Foin prairie permanente Ø 1,50 m — sous toit' => 260 * 275 * 85 / 100 * 0.97 / 1000,
        'Enrubannage Ø 1,20 m — 50 % MS, bâché au sol' => 90 * 440 * 50 / 100 * 0.92 / 1000,
        'Paille alimentaire Ø 1,50 m — sous toit' => 70 * 185 * 90 / 100 * 0.97 / 1000,
    ];
}

it('T-28 : crée l\'inventaire avec les mêmes stocks utiles que le calculateur', function () {
    $import = app(ImportCalculateurService::class);
    $apercu = $import->analyser($this->contenu);
    $resultat = $import->importer($this->jeu->elevage24a, $apercu);
    $lots = LotStock::query()->where('inventaire_id', $resultat['inventaire']->id)->pluck('stock_utile_t_ms', 'libelle');

    expect($resultat['lots'])->toBe(5)->and($resultat['refus'])->toBe([]);
    foreach (stocksCalculateur() as $libelle => $attendu) {
        expect((float) $lots[$libelle])->toEqualWithDelta($attendu, 1e-6, $libelle);
    }
    expect((float) $lots->sum())->toEqualWithDelta(array_sum(stocksCalculateur()), 1e-6)
        ->and([$resultat['inventaire']->date_debut->toDateString(), $resultat['inventaire']->date_fin->toDateString()])->toBe(['2026-11-01', '2027-04-09'])
        ->and((float) $resultat['inventaire']->marge_securite)->toBe(0.15);
});

it('propose familles, pertes et ouvrages, et signale ce qui n\'est pas repris', function () {
    $apercu = app(ImportCalculateurService::class)->analyser($this->contenu);
    $parLibelle = collect($apercu['lots'])->keyBy('donnees.libelle');

    expect($parLibelle['Enrubannage Ø 1,20 m — 50 % MS, bâché au sol']['donnees']['famille_code'])->toBe('ENRUB')
        ->and($parLibelle['Enrubannage Ø 1,20 m — 50 % MS, bâché au sol']['donnees']['mode_stockage'])->toBe('pertes.stockage.bache_sol')
        ->and($parLibelle['Paille alimentaire Ø 1,50 m — sous toit']['donnees']['famille_code'])->toBe('PAILLE')
        ->and($parLibelle['Herbe — taupinière']['donnees']['famille_code'])->toBe('ENSIL_HERBE')
        ->and($parLibelle['Herbe — taupinière']['alertes'][0])->toContain('Ouvrage non précisé')
        ->and($apercu['non_repris'])->toHaveCount(3);
});

it('refuse un contenu illisible ou étranger au calculateur', function () {
    $import = app(ImportCalculateurService::class);

    expect(fn () => $import->analyser('pas du json'))->toThrow(SaisieRefusee::class, 'illisible')
        ->and(fn () => $import->analyser('{"autre": 1}'))->toThrow(SaisieRefusee::class, 'ne vient pas du calculateur');
});

it('signale une équation herbe au-delà de 36 % de MS et ne reprend pas ce silo, contrairement au calculateur', function () {
    $contenu = json_decode($this->contenu, true);
    $contenu['silos'][1]['ms'] = '40';
    $import = app(ImportCalculateurService::class);
    $apercu = $import->analyser(json_encode($contenu));
    $resultat = $import->importer($this->jeu->elevage24a, $apercu);

    expect(collect($apercu['lots'])->firstWhere('donnees.libelle', 'Herbe — taupinière')['alertes'])->toContain('MS au-delà de 36 % : l\'équation herbe ne s\'applique pas, une densité mesurée est nécessaire (le calculateur l\'appliquait).')
        ->and($resultat['lots'])->toBe(4)
        ->and($resultat['refus'][0])->toContain('Herbe — taupinière');
});

it('reprend le bilan depuis l\'écran des inventaires, famille corrigée dans l\'aperçu', function () {
    $this->actingAs($this->jeu->eleveur24a);

    Livewire::test(Inventaires::class, ['elevage' => $this->jeu->elevage24a])
        ->set('contenuCalculateur', $this->contenu)->call('analyserCalculateur')->assertHasNoErrors()
        ->assertSee('Voir ce qui sera repris')->assertSee('Foin prairie permanente')
        ->set('apercu.lots.2.donnees.forme', 'balle_carree')
        ->call('importerCalculateur')->assertHasNoErrors()->assertRedirect();

    expect(Inventaire::query()->where('elevage_id', $this->jeu->elevage24a->id)->sole()->commentaire)->toContain('Repris du calculateur')
        ->and(LotStock::query()->where('libelle', 'Foin prairie permanente Ø 1,50 m — sous toit')->sole()->forme)->toBe('balle_carree');
});
