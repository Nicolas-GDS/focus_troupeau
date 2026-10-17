<?php

use App\Domain\Troupeau\ClasseCouverture;
use App\Livewire\Troupeau\Troupeau;
use App\Models\Fourrage\Analyse;
use App\Services\Dossier\EtiquettesService;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use App\Services\Stocks\StocksService;
use App\Services\Troupeau\AnalyseRationService;
use App\Services\Troupeau\TroupeauService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// Apports, couverture et contrôles d'une ration (RC-9, EF-M5-03) et affectation (EF-M5-05) : teneurs des analyses
// reliées et des étiquettes, besoins de ref.besoin_journalier, apports inconnus d'un lot non analysé, maximum du cuivre
// chez les ovins, filtre du publié pour l'éleveur (§ 6.3), lot déconseillé.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->jeu = JeuCloisonnement::creer();
    $this->tech = $this->jeu->technicien24;
    $this->actingAs($this->tech);
    $this->inventaire = app(StocksService::class)->creerInventaire($this->jeu->elevage24a, [
        'date_inventaire' => '2026-11-01', 'type' => 'fin_recolte', 'date_debut' => '2026-11-01', 'date_fin' => '2027-03-31', 'annee_seche' => false,
    ]);
    $this->troupeau = app(TroupeauService::class);
    $this->analyse = app(AnalyseRationService::class);
});

/** Lot de stock relié à une analyse (interprétée et validée ; publiée sur demande). */
function lotAnalyse(string $libelle, string $famille, string $type, array $valeurs, bool $publier = true, array $deconseille = []): object
{
    $analyse = AnalyseEssai::creer(test()->jeu->elevage24a, $valeurs, typeFourrage: $type);
    $i = app(InterpretationService::class)->creerBrouillon($analyse, test()->tech);
    app(InterpretationService::class)->enregistrer($i, new RedactionInterpretation(synthese: 'Lecture.', utilisationDeconseillee: $deconseille, niveauRetenu: 1));
    app(InterpretationService::class)->valider($i->refresh(), test()->tech);
    if ($publier) {
        app(InterpretationService::class)->publier($i->refresh(), test()->tech);
    }

    return app(StocksService::class)->enregistrerLot(test()->inventaire, ['libelle' => $libelle, 'forme' => 'balle_ronde', 'famille_code' => $famille,
        'prelevement_id' => $analyse->prelevement_id, 'nombre' => 100, 'poids_brut_kg' => 500, 'mode_stockage' => 'pertes.stockage.sous_toit']);
}

function etiquette(string $libelle, float $ms, array $composition): object
{
    return app(EtiquettesService::class)->creer(test()->jeu->elevage24a, null, ['nature' => 'concentre', 'libelle_eleveur' => $libelle, 'ms_pct' => $ms], $composition);
}

/** Ration des vaches de J1 (T-13) avec analyses reliées, correcteur et minéral décrits par étiquette. */
function vachesJ1(bool $publier = true): array
{
    $foin = lotAnalyse('Foin 2025', 'FOIN', 'FOIN_PP', ['MS' => 85, 'UFL_2018' => 0.67, 'PDI' => 60, 'MAT' => 78, 'CA' => 7.3, 'P' => 2.2, 'CU' => 5.1, 'K' => 19.7], $publier);
    $enrub = lotAnalyse('Enrubannage 2026', 'ENRUB', 'ENRUB_HERBE', ['MS' => 45, 'UFL_2018' => 0.80, 'PDI' => 70, 'MAT' => 84, 'CA' => 9.6, 'P' => 2.5, 'CU' => 5.4, 'K' => 13.4], $publier);
    $correcteur = etiquette('Correcteur', 88, ['UFL_2018' => ['valeur' => 0.88, 'unite' => 'UFL/kg'], 'PDI' => ['valeur' => 220, 'unite' => 'g/kg'],
        'MAT' => ['valeur' => 246.4, 'unite' => 'g/kg'], 'CA' => ['valeur' => 8.8, 'unite' => 'g/kg'], 'P' => ['valeur' => 7.04, 'unite' => 'g/kg'], 'CU' => ['valeur' => 17.6, 'unite' => 'mg/kg'], 'K' => ['valeur' => 13.2, 'unite' => 'g/kg']]);
    $mineral = etiquette('Minéral', 100, ['CA' => ['valeur' => 8, 'unite' => '%'], 'P' => ['valeur' => 12, 'unite' => '%'], 'CU' => ['valeur' => 2000, 'unite' => 'mg/kg']]);
    $lot = test()->troupeau->creerLot(test()->jeu->elevage24a, test()->inventaire, ['libelle' => 'Vaches', 'categorie_code' => 'VA']);
    $phase = test()->troupeau->ajouterPhase($lot, 'lactation', '2026-11-01', '2027-03-31', 100);
    foreach ([[$foin, 4.0], [$enrub, 7.0]] as [$l, $kg]) {
        test()->troupeau->ajouterComposant($phase->ration, ['source' => 'lot_stock', 'lot_stock_id' => $l->id, 'kg_ms' => $kg]);
    }
    test()->troupeau->ajouterComposant($phase->ration, ['source' => 'etiquette', 'etiquette_id' => $correcteur->id, 'kg_ms' => 0.88]);
    test()->troupeau->ajouterComposant($phase->ration, ['source' => 'etiquette', 'etiquette_id' => $mineral->id, 'kg_ms' => 0.1]);

    return [$lot->refresh(), $phase->refresh()->load('ration.composants.lotStock', 'ration.composants.etiquette')];
}

it('compare la ration de J1 aux repères des vaches allaitantes en lactation (RC-9, mêmes apports que T-13)', function () {
    [$lot, $phase] = vachesJ1();
    $a = $this->analyse->analyser($phase, $lot, false);
    $parCode = collect($a['elements'])->keyBy('code');
    $conc = fn (string $c) => $parCode[$c]['resultat']->concentration;

    expect($a['msi'])->toEqualWithDelta(11.98, 1e-9)
        ->and($a['complet'])->toBeTrue()
        ->and($conc('UFL_2018'))->toEqualWithDelta((4 * 0.67 + 7 * 0.80 + 0.88) / 11.98, 1e-6)
        ->and($conc('MAT'))->toEqualWithDelta((4 * 78 + 7 * 84 + 0.88 * 280) / 11.98, 1e-6)
        ->and($conc('CA'))->toEqualWithDelta((4 * 7.3 + 7 * 9.6 + 0.88 * 10 + 0.1 * 80) / 11.98, 1e-6)
        ->and($parCode['UFL_2018']['resultat']->classe)->toBe(ClasseCouverture::Couvert)
        ->and($parCode['MAT']['resultat']->classe)->toBe(ClasseCouverture::Deficit)
        ->and($parCode['MG']['resultat']->classe)->toBe(ClasseCouverture::Deficit)
        ->and($parCode['CA']['resultat']->classe)->toBe(ClasseCouverture::Couvert)
        ->and($parCode['UFL_2018']['source']['a_valider'])->toBeTrue()
        ->and(collect($a['controles'])->mapWithKeys(fn ($c) => [$c->code => $c->gravite])->all())->toBe(['CA_P' => null, 'PDI_UFL' => null]);   // K/Mg non calculé : magnésium non apporté
});

it('signale le maximum réglementaire du cuivre chez des brebis nourries avec un seau pour bovins (T-13k, gravité 3)', function () {
    $foin = lotAnalyse('Foin', 'FOIN', 'FOIN_PP', ['MS' => 85, 'CU' => 5]);
    $seau = etiquette('Seau', 100, ['CU' => ['valeur' => 300, 'unite' => 'mg/kg']]);
    $lot = $this->troupeau->creerLot($this->jeu->elevage24a, $this->inventaire, ['libelle' => 'Brebis', 'categorie_code' => 'BREBIS_ALL']);
    $phase = $this->troupeau->ajouterPhase($lot, 'lactation', '2026-11-01', '2027-03-31', 300);
    $this->troupeau->ajouterComposant($phase->ration, ['source' => 'lot_stock', 'lot_stock_id' => $foin->id, 'kg_ms' => 1.5]);
    $this->troupeau->ajouterComposant($phase->ration, ['source' => 'etiquette', 'etiquette_id' => $seau->id, 'kg_ms' => 0.1]);

    $cu = collect($this->analyse->analyser($phase->refresh(), $lot, false)['elements'])->firstWhere('code', 'CU');

    expect($cu['resultat']->concentration)->toEqualWithDelta((7.5 + 30) / 1.6, 1e-9)
        ->and($cu['resultat']->classe)->toBe(ClasseCouverture::MaximumDepasse)
        ->and($cu['resultat']->gravite)->toBe(3)
        ->and($cu['besoin']->maximum)->toBe(15.0);
});

it('ne calcule pas la couverture d\'une ration comprenant un lot non analysé, et le dit', function () {
    $foin = app(StocksService::class)->enregistrerLot($this->inventaire, ['libelle' => 'Foin non analysé', 'forme' => 'balle_ronde', 'famille_code' => 'FOIN',
        'nombre' => 100, 'poids_brut_kg' => 300, 'mode_stockage' => 'pertes.stockage.sous_toit']);
    $lot = $this->troupeau->creerLot($this->jeu->elevage24a, $this->inventaire, ['libelle' => 'Vaches', 'categorie_code' => 'VA']);
    $phase = $this->troupeau->ajouterPhase($lot, 'lactation', '2026-11-01', '2027-03-31', 100);
    $this->troupeau->ajouterComposant($phase->ration, ['source' => 'lot_stock', 'lot_stock_id' => $foin->id, 'kg_ms' => 12]);

    $a = $this->analyse->analyser($phase->refresh(), $lot, false);

    expect($a['complet'])->toBeFalse()
        ->and($a['inconnus'])->toBe(['Foin non analysé'])
        ->and(collect($a['elements'])->pluck('resultat.classe')->filter()->all())->toBe([])
        ->and($a['controles'])->toBe([]);
    Livewire::test(Troupeau::class, ['elevage' => $this->jeu->elevage24a])->assertSee('Apports inconnus pour : Foin non analysé');
});

it('ne donne à l\'éleveur que les teneurs des analyses publiées (§ 6.3)', function () {
    [$lot, $phase] = vachesJ1(publier: false);

    expect($this->analyse->analyser($phase, $lot, true)['inconnus'])->toBe(['Foin 2025', 'Enrubannage 2026'])
        ->and($this->analyse->analyser($phase, $lot, false)['complet'])->toBeTrue();
});

it('demande la production laitière avant de calculer le besoin d\'une vache laitière en lactation', function () {
    $lot = $this->troupeau->creerLot($this->jeu->elevage24a, $this->inventaire, ['libelle' => 'Laitières', 'categorie_code' => 'VL']);
    $phase = $this->troupeau->ajouterPhase($lot, 'lactation', '2026-11-01', '2027-03-31', 60);
    $this->troupeau->ajouterComposant($phase->ration, ['source' => 'paturage', 'kg_ms' => 16]);

    $libelles = DB::table('ref.parametre')->whereIn('code', ['UFL_2018', 'PDI'])->pluck('libelle', 'code');

    expect($this->analyse->analyser($phase->refresh(), $lot, false)['manquants'])->toBe([$libelles['UFL_2018'], $libelles['PDI']]);
    $lot->update(['production_valeur' => 25, 'production_unite' => 'kg_lait_j']);
    $ufl = collect($this->analyse->analyser($phase->refresh(), $lot->refresh(), false)['elements'])->firstWhere('code', 'UFL_2018');
    expect($ufl['besoin']->valeur)->toEqualWithDelta(5.3 + 0.42 * 25, 1e-9);
});

it('montre qui mange quoi et signale un lot déconseillé pour la catégorie (EF-M5-05)', function () {
    $foin = lotAnalyse('Foin riche en potassium', 'FOIN', 'FOIN_PP', ['MS' => 85, 'K' => 32], deconseille: ['VA']);
    $lot = $this->troupeau->creerLot($this->jeu->elevage24a, $this->inventaire, ['libelle' => 'Vaches', 'categorie_code' => 'VA']);
    $phase = $this->troupeau->ajouterPhase($lot, 'prepa_velage', '2027-01-01', '2027-02-28', 40);
    $this->troupeau->ajouterComposant($phase->ration, ['source' => 'lot_stock', 'lot_stock_id' => $foin->id, 'kg_ms' => 10]);

    $affectation = $this->analyse->affectation($this->inventaire, false);

    expect($affectation)->toHaveCount(1)
        ->and($affectation[0]['consommateurs'][0]['t_ms_jour'])->toEqualWithDelta(0.4, 1e-9)
        ->and($affectation[0]['consommateurs'][0]['deconseille'])->toBeTrue();
    Livewire::test(Troupeau::class, ['elevage' => $this->jeu->elevage24a])->assertSee('Qui mange quoi')->assertSee('Lot déconseillé pour cette catégorie');
});
