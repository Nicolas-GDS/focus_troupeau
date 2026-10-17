<?php

use App\Livewire\Stocks\Inventaires;
use App\Livewire\Stocks\SaisieInventaire;
use App\Models\App\Convention;
use App\Models\Fourrage\Analyse;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\Mouvement;
use App\Services\Dossier\SaisieRefusee;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use App\Services\Stocks\StocksService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// E12 — inventaire des stocks (EF-M4-01 à EF-M4-07, RC-5 à RC-7, T-10, T-11 par le service, T-32, T-40) : calculs à
// l'enregistrement avec les valeurs par défaut des référentiels, MS d'une analyse reliée, mouvements, saisie mobile.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->jeu = JeuCloisonnement::creer();
    $this->service = app(StocksService::class);
    $this->actingAs($this->jeu->technicien24);
    $this->inventaire = $this->service->creerInventaire($this->jeu->elevage24a, [
        'date_inventaire' => '2026-11-01', 'type' => 'fin_recolte', 'date_debut' => '2026-11-01', 'date_fin' => '2027-03-31', 'annee_seche' => false,
    ]);
});

function lotBalles(array $donnees): array
{
    return ['libelle' => 'Lot', 'forme' => 'balle_ronde', 'famille_code' => 'FOIN', 'mode_stockage' => 'pertes.stockage.sous_toit', ...$donnees];
}

it('calcule les stocks de J1 avec les valeurs par défaut des référentiels (T-10, T-11)', function () {
    $silo = $this->service->enregistrerLot($this->inventaire, [
        'libelle' => 'Maïs', 'forme' => 'silo', 'famille_code' => 'ENSIL_MAIS', 'ouvrage' => 'couloir', 'l1_m' => 30, 'l2_m' => 6, 'largeur_m' => 8,
        'hauteur_m' => 2, 'ms_pct' => 33, 'amidon_pct' => 30, 'methode_densite' => 'equation', 'equation_code' => 'mais_couloir',
        'mode_stockage' => 'pertes.conservation.silo_bien_conduit',
    ]);
    $foin = $this->service->enregistrerLot($this->inventaire, lotBalles(['nombre' => 400, 'poids_brut_kg' => 300]));
    $enrub = $this->service->enregistrerLot($this->inventaire, lotBalles(['famille_code' => 'ENRUB', 'nombre' => 150, 'poids_brut_kg' => 650, 'ms_pct' => 45]));
    $paille = $this->service->enregistrerLot($this->inventaire, lotBalles(['famille_code' => 'PAILLE', 'nombre' => 100, 'poids_brut_kg' => 280]));
    $vrac = $this->service->enregistrerLot($this->inventaire, ['libelle' => 'Vrac', 'forme' => 'vrac', 'famille_code' => 'FOIN', 'volume_m3' => 50, 'mode_stockage' => 'pertes.stockage.batiment_ferme']);

    expect((float) $silo->volume_calc_m3)->toEqualWithDelta(528.0, 1e-6)
        ->and((float) $silo->densite_calc)->toEqualWithDelta(676.5 * 0.33, 1e-6)
        ->and((float) $silo->stock_utile_t_ms)->toEqualWithDelta(528 * 676.5 * 0.33 / 1000 * 0.95, 1e-6)
        ->and((float) $foin->stock_utile_t_ms)->toEqualWithDelta(98.94, 1e-6)
        ->and([(float) $foin->ms_pct, $foin->ms_origine, (float) $foin->pertes_pct, (float) $foin->pertes_defaut_pct])->toBe([85.0, 'defaut', 0.03, 0.03])
        ->and((float) $enrub->stock_utile_t_ms)->toEqualWithDelta(42.55875, 1e-6)
        ->and($enrub->ms_origine)->toBe('saisie')
        ->and((float) $paille->stock_utile_t_ms)->toEqualWithDelta(24.444, 1e-6)
        ->and((float) $vrac->stock_utile_t_ms)->toEqualWithDelta(3.7485, 1e-6)
        ->and((float) $vrac->poids_brut_kg)->toBe(90.0);

    $tableau = collect($this->service->tableauDeBord($this->inventaire))->keyBy('famille');
    expect($tableau['FOIN']['stock_utile_t_ms'])->toEqualWithDelta(98.94 + 3.7485, 1e-6)
        ->and($tableau['FOIN']['part_analysee'])->toBe(0.0);
});

it('retient une perte saisie en conservant la valeur de référence (EF-M4-07)', function () {
    $lot = $this->service->enregistrerLot($this->inventaire, lotBalles(['nombre' => 400, 'poids_brut_kg' => 300, 'pertes_pct' => 0.05]));

    expect([(float) $lot->pertes_pct, (float) $lot->pertes_defaut_pct])->toBe([0.05, 0.03])
        ->and((float) $lot->stock_utile_t_ms)->toEqualWithDelta(400 * 300 * 0.85 * 0.95 / 1000, 1e-6);
});

it('prend la MS du bulletin d\'un lot relié à une analyse (EF-M4-04)', function () {
    $analyse = AnalyseEssai::creer($this->jeu->elevage24a, ['MS' => 47.5], typeFourrage: 'ENRUB_HERBE');
    $lot = $this->service->enregistrerLot($this->inventaire, lotBalles(['famille_code' => 'ENRUB', 'type_fourrage_code' => 'ENRUB_HERBE', 'prelevement_id' => $analyse->prelevement_id,
        'nombre' => 150, 'poids_brut_kg' => 650, 'ms_pct' => 40]));

    expect([(float) $lot->ms_pct, $lot->ms_origine, $lot->famille_code])->toBe([47.5, 'analyse', 'ENRUB']);
});

it('refuse un enrubannage sans MS, une équation herbe au-delà de 36 % de MS et un prélèvement d\'un autre élevage', function () {
    expect(fn () => $this->service->enregistrerLot($this->inventaire, lotBalles(['famille_code' => 'ENRUB', 'nombre' => 10, 'poids_brut_kg' => 650])))
        ->toThrow(SaisieRefusee::class, 'Saisir la matière sèche')
        ->and(fn () => $this->service->enregistrerLot($this->inventaire, ['libelle' => 'Herbe', 'forme' => 'silo', 'famille_code' => 'ENSIL_HERBE', 'ouvrage' => 'couloir',
            'l1_m' => 20, 'largeur_m' => 6, 'hauteur_m' => 2, 'ms_pct' => 40, 'methode_densite' => 'equation', 'equation_code' => 'herbe_longs',
            'mode_stockage' => 'pertes.conservation.silo_bien_conduit']))->toThrow(SaisieRefusee::class, '36 %')
        ->and(fn () => $this->service->enregistrerLot($this->inventaire, lotBalles(['nombre' => 1, 'poids_brut_kg' => 300,
            'prelevement_id' => AnalyseEssai::creer($this->jeu->elevage24b, ['MS' => 85])->prelevement_id])))->toThrow(SaisieRefusee::class, 'n\'appartient pas');
});

it('corrige le volume d\'un silo fermé depuis dix jours (RC-5.3) et accepte une densité mesurée', function () {
    $lot = $this->service->enregistrerLot($this->inventaire, [
        'libelle' => 'Maïs', 'forme' => 'silo', 'famille_code' => 'ENSIL_MAIS', 'ouvrage' => 'couloir', 'l1_m' => 30, 'l2_m' => 6, 'largeur_m' => 8,
        'hauteur_m' => 2, 'ms_pct' => 33, 'date_fermeture' => '2026-10-22', 'methode_densite' => 'carottage', 'densite_kg_ms_m3' => 220,
        'mode_stockage' => 'pertes.conservation.silo_chauffe',
    ]);

    expect((float) $lot->volume_calc_m3)->toEqualWithDelta(528 * 0.95, 1e-6)
        ->and((float) $lot->stock_utile_t_ms)->toEqualWithDelta(528 * 0.95 * 220 / 1000 * 0.88, 1e-6);
});

it('enregistre les mouvements avec leur sens, exige le motif d\'un ajustement (EF-M4-05)', function () {
    $lot = $this->service->enregistrerLot($this->inventaire, lotBalles(['nombre' => 400, 'poids_brut_kg' => 300]));
    $vente = $this->service->ajouterMouvement($lot, 'vente', '2026-12-01', 5.0, 900);
    $recomptage = $this->service->ajouterMouvement($lot, 'recomptage', '2027-01-15', 50.0);

    expect((float) $vente->quantite_t_ms)->toBe(-5.0)
        ->and((float) $recomptage->quantite_t_ms)->toBe(50.0)
        ->and(fn () => $this->service->ajouterMouvement($lot, 'ajustement', '2027-01-20', -2.0))->toThrow(SaisieRefusee::class, 'motif')
        ->and(fn () => $this->service->ajouterMouvement($lot, 'achat', '2027-01-20', -2.0))->toThrow(SaisieRefusee::class);
});

it('crée l\'inventaire avec la marge en vigueur, majorée en année sèche, un seul courant à la fois (EF-M4-01, RC-12)', function () {
    expect((float) $this->inventaire->marge_securite)->toBe(0.15)
        ->and(fn () => $this->service->creerInventaire($this->jeu->elevage24a, ['date_inventaire' => '2027-01-15', 'type' => 'mi_hiver',
            'date_debut' => '2027-01-15', 'date_fin' => '2027-03-31', 'annee_seche' => false]))->toThrow(SaisieRefusee::class);

    $this->actingAs($this->jeu->eleveur47);
    Livewire::test(Inventaires::class, ['elevage' => $this->jeu->elevage47])
        ->set('dateInventaire', '2026-11-01')->set('dateDebut', '2026-11-01')->set('dateFin', '2027-04-30')->set('anneeSeche', true)
        ->call('creer')->assertHasNoErrors()->assertRedirect();

    expect((float) Inventaire::query()->where('elevage_id', $this->jeu->elevage47->id)->sole()->marge_securite)->toBe(0.25);
});

it('laisse l\'éleveur saisir un lot sur téléphone avec l\'aperçu du calcul, puis un mouvement', function () {
    $this->actingAs($this->jeu->eleveur24a);

    $ecran = Livewire::test(SaisieInventaire::class, ['elevage' => $this->jeu->elevage24a, 'inventaire' => $this->inventaire])
        ->set('lot.libelle', 'Foin 2026')->set('lot.nombre', '400')->set('lot.poids_brut_kg', '300')
        ->assertSee('98,94')
        ->call('enregistrerLot')->assertHasNoErrors();
    $lot = LotStock::query()->sole();

    $ecran->set("mouvements.{$lot->id}", ['type' => 'achat', 'date' => '2026-12-15', 'quantite' => '30', 'prix' => '', 'motif' => ''])
        ->call('ajouterMouvement', $lot->id)->assertHasNoErrors()
        ->assertSee('+ 30,00 t MS');

    expect(Mouvement::query()->sole()->cree_par)->toBe($this->jeu->eleveur24a->id);
});

it('cloisonne l\'inventaire : autre élevage dans l\'adresse, autre GDS, rôle sans accès (T-40)', function () {
    $this->actingAs($this->jeu->eleveur24a);
    $this->get(route('stocks.inventaire', [$this->jeu->elevage24a->id, $this->inventaire->id]))->assertOk()->assertSee('Stock utile par famille');
    $this->get(route('stocks.inventaire', [$this->jeu->elevage24b->id, $this->inventaire->id]))->assertNotFound();

    $this->actingAs($this->jeu->technicien47);
    $this->get(route('stocks.inventaire', [$this->jeu->elevage24a->id, $this->inventaire->id]))->assertNotFound();

    $this->actingAs($this->jeu->veterinaire);
    $this->get(route('stocks.inventaire', [$this->jeu->elevage24a->id, $this->inventaire->id]))->assertForbidden();
});

it('passe l\'inventaire en lecture seule sous convention échue (T-32)', function () {
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage24a->id)->update(['statut' => 'echue', 'date_fin' => now()->subDay()->toDateString()]);
    $this->actingAs($this->jeu->eleveur24a);

    Livewire::test(SaisieInventaire::class, ['elevage' => $this->jeu->elevage24a, 'inventaire' => $this->inventaire])
        ->assertDontSee('Ajouter un lot')
        ->set('lot.libelle', 'Foin')->set('lot.nombre', '1')->set('lot.poids_brut_kg', '300')
        ->call('enregistrerLot')->assertForbidden();
});

it('ne propose à l\'éleveur que les analyses publiées pour la MS d\'un lot (§ 6.3)', function () {
    $publiee = AnalyseEssai::creer($this->jeu->elevage24a, ['MS' => 86.0]);
    $brouillon = AnalyseEssai::creer($this->jeu->elevage24a, ['MS' => 79.0]);
    $interpretations = app(InterpretationService::class);
    $i = $interpretations->creerBrouillon($publiee, $this->jeu->technicien24);
    $interpretations->enregistrer($i, new RedactionInterpretation(synthese: 'Lecture.', niveauRetenu: 1));
    $interpretations->valider($i->refresh(), $this->jeu->technicien24);
    $interpretations->publier($i->refresh(), $this->jeu->technicien24);

    $pourEleveur = collect($this->service->prelevementsReliables($this->jeu->elevage24a, true))->pluck('ms', 'prelevement_id');
    $pourAgent = collect($this->service->prelevementsReliables($this->jeu->elevage24a))->pluck('ms', 'prelevement_id');

    expect($pourEleveur->all())->toBe([$publiee->prelevement_id => 86.0])
        ->and($pourAgent[$brouillon->prelevement_id])->toBe(79.0)
        ->and(fn () => $this->service->enregistrerLot($this->inventaire, lotBalles(['nombre' => 1, 'poids_brut_kg' => 300, 'prelevement_id' => $brouillon->prelevement_id]), null, true))
        ->not->toThrow(SaisieRefusee::class);
    expect(LotStock::query()->where('prelevement_id', $brouillon->prelevement_id)->sole()->ms_origine)->toBe('defaut');
});
