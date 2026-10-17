<?php

use App\Livewire\Troupeau\Troupeau;
use App\Models\App\Convention;
use App\Models\Plan\LotAnimaux;
use App\Models\Plan\RationComposant;
use App\Services\Dossier\EtiquettesService;
use App\Services\Dossier\SaisieRefusee;
use App\Services\Stocks\StocksService;
use App\Services\Troupeau\TroupeauService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// E13 — troupeau (EF-M5-01 à EF-M5-06, RC-8) : lots, coefficient UGB proposé, phases sans chevauchement, rations et kg MS
// depuis le brut, besoins de J1 par les deux voies (§ 9.1 : besoin prélevé 222,16 t MS), copie d'une période, droits.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->actingAs($this->jeu->technicien24);
    $this->stocks = app(StocksService::class);
    $this->service = app(TroupeauService::class);
    $this->inventaire = $this->stocks->creerInventaire($this->jeu->elevage24a, [
        'date_inventaire' => '2026-11-01', 'type' => 'fin_recolte', 'date_debut' => '2026-11-01', 'date_fin' => '2027-03-31', 'annee_seche' => false,
    ]);
    $balles = fn (string $libelle, string $famille, int $n, int $poids, ?float $ms) => $this->stocks->enregistrerLot($this->inventaire,
        ['libelle' => $libelle, 'forme' => 'balle_ronde', 'famille_code' => $famille, 'nombre' => $n, 'poids_brut_kg' => $poids, 'ms_pct' => $ms, 'mode_stockage' => 'pertes.stockage.sous_toit']);
    $this->foin = $balles('Foin 2026', 'FOIN', 400, 300, null);
    $this->enrub = $balles('Enrubannage 2026', 'ENRUB', 150, 650, 45);
    $this->paille = $balles('Paille', 'PAILLE', 100, 280, null);
    $this->correcteur = app(EtiquettesService::class)->creer($this->jeu->elevage24a, null,
        ['nature' => 'concentre', 'libelle_eleveur' => 'Correcteur', 'ms_pct' => 88], ['UFL_2018' => ['valeur' => 0.9, 'unite' => 'UFL/kg']]);
});

/** Lots d'animaux de J1 : 100 vaches allaitantes en lactation, 30 génisses 1-2 ans, sur la période (151 jours). */
function troupeauJ1(): array
{
    $s = test()->service;
    $vaches = $s->creerLot(test()->jeu->elevage24a, test()->inventaire, ['libelle' => 'Vaches', 'categorie_code' => 'VA']);
    $phaseV = $s->ajouterPhase($vaches, 'lactation', '2026-11-01', '2027-03-31', 100);
    foreach ([['lot_stock', test()->foin->id, 4.0], ['lot_stock', test()->enrub->id, 7.0]] as [$source, $id, $kgMs]) {
        $s->ajouterComposant($phaseV->ration, ['source' => $source, 'lot_stock_id' => $id, 'kg_ms' => $kgMs]);
    }
    $s->ajouterComposant($phaseV->ration, ['source' => 'etiquette', 'etiquette_id' => test()->correcteur->id, 'kg_ms' => 0.88]);
    $genisses = $s->creerLot(test()->jeu->elevage24a, test()->inventaire, ['libelle' => 'Génisses', 'categorie_code' => 'GEN']);
    $phaseG = $s->ajouterPhase($genisses, 'croissance', '2026-11-01', '2027-03-31', 30);
    $s->ajouterComposant($phaseG->ration, ['source' => 'lot_stock', 'lot_stock_id' => test()->foin->id, 'kg_ms' => 5.0]);
    $s->ajouterComposant($phaseG->ration, ['source' => 'lot_stock', 'lot_stock_id' => test()->paille->id, 'kg_ms' => 1.0]);

    return compact('vaches', 'genisses', 'phaseV', 'phaseG');
}

it('propose le coefficient UGB de la catégorie, et aucun quand RC-8.1 n\'en donne pas', function () {
    $va = $this->service->creerLot($this->jeu->elevage24a, $this->inventaire, ['libelle' => 'Vaches', 'categorie_code' => 'VA']);
    $veaux = $this->service->creerLot($this->jeu->elevage24a, $this->inventaire, ['libelle' => 'Veaux', 'categorie_code' => 'VEAU']);
    $saisi = $this->service->creerLot($this->jeu->elevage24a, $this->inventaire, ['libelle' => 'Génisses', 'categorie_code' => 'GEN', 'coeff_ugb' => 0.3]);

    expect([(float) $va->coeff_ugb, $va->espece_code])->toBe([0.9, 'BOV'])
        ->and($veaux->coeff_ugb)->toBeNull()
        ->and((float) $saisi->coeff_ugb)->toBe(0.3);
});

it('refuse deux phases qui se chevauchent et crée la ration avec les pertes de référence (RC-6)', function () {
    $lot = $this->service->creerLot($this->jeu->elevage24a, $this->inventaire, ['libelle' => 'Vaches', 'categorie_code' => 'VA']);
    $phase = $this->service->ajouterPhase($lot, 'gestation', '2026-11-01', '2027-01-31', 100);

    expect((float) $phase->ration->pertes_distribution_pct)->toBe(0.13)
        ->and(fn () => $this->service->ajouterPhase($lot, 'lactation', '2027-01-15', '2027-03-31', 100))->toThrow(SaisieRefusee::class, 'chevauche');

    $this->service->enregistrerDistribution($phase->ration, 'pertes.distribution.auge_cone');
    expect((float) $phase->ration->refresh()->pertes_distribution_pct)->toBe(0.04);
});

it('calcule les kg MS d\'un composant depuis le brut et la MS de sa source', function () {
    $lot = $this->service->creerLot($this->jeu->elevage24a, $this->inventaire, ['libelle' => 'Vaches', 'categorie_code' => 'VA']);
    $ration = $this->service->ajouterPhase($lot, 'lactation', '2026-11-01', '2027-03-31', 100)->ration;

    $foin = $this->service->ajouterComposant($ration, ['source' => 'lot_stock', 'lot_stock_id' => $this->foin->id, 'quantite_brute_kg' => 10]);
    $famille = $this->service->ajouterComposant($ration, ['source' => 'famille_stock', 'famille_code' => 'ENRUB', 'quantite_brute_kg' => 10]);
    $correcteur = $this->service->ajouterComposant($ration, ['source' => 'etiquette', 'etiquette_id' => $this->correcteur->id, 'quantite_brute_kg' => 1]);
    $sansMs = app(EtiquettesService::class)->creer($this->jeu->elevage24a, null, ['nature' => 'mineral', 'libelle_eleveur' => 'Minéral'], []);

    expect((float) $foin->kg_ms)->toEqualWithDelta(8.5, 1e-9)
        ->and((float) $famille->kg_ms)->toEqualWithDelta(4.5, 1e-9)
        ->and((float) $correcteur->kg_ms)->toEqualWithDelta(0.88, 1e-9)
        ->and(fn () => $this->service->ajouterComposant($ration, ['source' => 'etiquette', 'etiquette_id' => $sansMs->id, 'quantite_brute_kg' => 0.1]))
        ->toThrow(SaisieRefusee::class, 'MS inconnue')
        ->and(fn () => $this->service->ajouterComposant($ration, ['source' => 'paturage', 'quantite_brute_kg' => 30]))->toThrow(SaisieRefusee::class)
        ->and((float) $this->service->ajouterComposant($ration, ['source' => 'paturage', 'kg_ms' => 3])->kg_ms)->toBe(3.0);
});

it('calcule les besoins de J1 par les deux voies : 222,16 t MS prélevées sur les stocks (RC-8, § 9.1)', function () {
    troupeauJ1();
    $b = $this->service->besoins($this->inventaire);

    expect($b['ration_t_ms'])->toEqualWithDelta(100 * 11 * 151 / 1000 + 30 * 6 * 151 / 1000, 1e-6)
        ->and(round($b['stock_t_ms'], 2))->toBe(222.16)
        ->and($b['ugb_t_ms'])->toEqualWithDelta((100 * 0.9 + 30 * 0.6) * 13 * 151 / 1000, 1e-6)
        ->and($b['ecart'])->toEqualWithDelta(abs($b['ugb_t_ms'] - $b['ration_t_ms']) / $b['ugb_t_ms'], 1e-9)
        ->and($b['ecart_max'])->toBe(0.2)
        ->and($b['lots'][0]['phases'][0]['jours'])->toBe(151)
        ->and($b['lots'][0]['phases'][0]['kg_ms_fourrage'])->toBe(11.0);
});

it('repart des lots et rations de l\'inventaire précédent (EF-M5-06)', function () {
    troupeauJ1();
    $this->stocks->clore($this->inventaire);
    $suivant = $this->stocks->creerInventaire($this->jeu->elevage24a, [
        'date_inventaire' => '2027-11-01', 'type' => 'fin_recolte', 'date_debut' => '2027-11-01', 'date_fin' => '2028-03-31', 'annee_seche' => false,
    ]);

    expect($this->service->copierDepuis($this->inventaire, $suivant))->toBe(2);
    $vaches = LotAnimaux::query()->where('inventaire_id', $suivant->id)->where('libelle', 'Vaches')->sole();
    $phase = $vaches->phases()->sole();
    $sources = RationComposant::query()->where('ration_id', $phase->ration->id)->orderBy('ordre')->get();

    expect([$phase->date_debut->toDateString(), $phase->date_fin->toDateString(), $phase->effectif])->toBe(['2027-11-01', '2028-03-31', 100])
        ->and($sources->pluck('source')->all())->toBe(['famille_stock', 'famille_stock', 'etiquette'])
        ->and($sources->pluck('famille_code')->filter()->values()->all())->toBe(['FOIN', 'ENRUB'])
        ->and($sources->whereNotNull('lot_stock_id')->count())->toBe(0);
});

it('laisse l\'éleveur décrire un lot, sa phase et sa ration depuis l\'écran E13', function () {
    $this->actingAs($this->jeu->eleveur24a);

    $ecran = Livewire::test(Troupeau::class, ['elevage' => $this->jeu->elevage24a])
        ->set('nouveauLot.libelle', 'Vaches')->set('nouveauLot.categorie_code', 'VA')
        ->call('creerLot')->assertHasNoErrors();
    $lot = LotAnimaux::query()->sole();
    $ecran->set("phases.{$lot->id}", ['stade' => 'lactation', 'debut' => '2026-11-01', 'fin' => '2027-03-31', 'effectif' => '100'])
        ->call('ajouterPhase', $lot->id)->assertHasNoErrors();
    $ration = $lot->phases()->sole()->ration;
    $ecran->set("composants.{$ration->id}", ['source' => 'lot_stock', 'reference' => (string) $this->foin->id, 'brut' => '', 'kg_ms' => '4'])
        ->call('ajouterComposant', $ration->id)->assertHasNoErrors()
        ->assertSee('Foin 2026')->assertSee('60,4');   // 100 × 4 × 151 / 1000 = 60,4 t MS
});

it('refuse la saisie au vétérinaire et sous convention échue, et cloisonne les lots (T-32, T-40)', function () {
    troupeauJ1();
    $this->actingAs($this->jeu->veterinaire);
    $this->get(route('troupeau', $this->jeu->elevage24a->id))->assertForbidden();

    $this->actingAs($this->jeu->technicien47);
    $this->get(route('troupeau', $this->jeu->elevage24a->id))->assertNotFound();

    Convention::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage24a->id)->update(['statut' => 'echue', 'date_fin' => now()->subDay()->toDateString()]);
    $this->actingAs($this->jeu->eleveur24a);
    Livewire::test(Troupeau::class, ['elevage' => $this->jeu->elevage24a])
        ->assertSee('Vaches')->assertDontSee('Nouveau lot d\'animaux')
        ->set('nouveauLot.libelle', 'Autre')->call('creerLot')->assertForbidden();
});
