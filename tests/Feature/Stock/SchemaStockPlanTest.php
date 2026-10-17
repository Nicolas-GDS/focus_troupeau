<?php

use App\Models\App\Convention;
use App\Models\Plan\LotAnimaux;
use App\Models\Plan\Phase;
use App\Models\Plan\Ration;
use App\Models\Plan\RationComposant;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\Mouvement;
use App\Models\Stock\PeriodePhase;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Support\JeuCloisonnement;

// Lot L5, étape 1 — schémas stock et plan (§ 5.4, § 5.5), référentiels (version 5), cloisonnement (§ 6.3) et droits
// (§ 2.3 : éleveur L/E propre, technicien L/E, autres rôles L ; écriture sous convention active, T-32).
uses(RefreshDatabase::class);

/** Un inventaire courant de l'élevage avec un lot, un mouvement, une phase ; un lot d'animaux avec phase, ration, composant. */
function jeuStock(object $elevage): array
{
    $inventaire = Inventaire::query()->withoutGlobalScopes()->create([
        'elevage_id' => $elevage->id, 'date_inventaire' => '2026-11-01', 'type' => 'fin_recolte',
        'date_debut' => '2026-11-01', 'date_fin' => '2027-03-31', 'marge_securite' => 0.15,
    ]);
    $lot = LotStock::query()->withoutGlobalScopes()->create([
        'inventaire_id' => $inventaire->id, 'elevage_id' => $elevage->id, 'libelle' => 'Foin 2026', 'famille_code' => 'FOIN',
        'forme' => 'balle_ronde', 'nombre' => 400, 'poids_brut_kg' => 300, 'ms_pct' => 85, 'ms_origine' => 'defaut',
        'mode_stockage' => 'pertes.stockage.sous_toit', 'pertes_pct' => 0.03, 'pertes_defaut_pct' => 0.03,
    ]);
    $mouvement = Mouvement::query()->withoutGlobalScopes()->create(['lot_stock_id' => $lot->id, 'date_mouvement' => '2026-12-15', 'type' => 'achat', 'quantite_t_ms' => 30]);
    $periode = PeriodePhase::query()->withoutGlobalScopes()->create(['inventaire_id' => $inventaire->id, 'libelle' => 'hivernage', 'date_debut' => '2026-11-01', 'date_fin' => '2027-03-31']);
    $animaux = LotAnimaux::query()->withoutGlobalScopes()->create(['elevage_id' => $elevage->id, 'inventaire_id' => $inventaire->id,
        'libelle' => 'Vaches', 'espece_code' => 'BOV', 'categorie_code' => 'VA', 'coeff_ugb' => 0.90]);
    $phase = Phase::query()->withoutGlobalScopes()->create(['lot_animaux_id' => $animaux->id, 'stade' => 'lactation', 'date_debut' => '2026-11-01', 'date_fin' => '2027-03-31', 'effectif' => 100]);
    $ration = Ration::query()->withoutGlobalScopes()->create(['phase_id' => $phase->id, 'mode_distribution' => 'pertes.distribution.deroule_sol', 'pertes_distribution_pct' => 0.13]);
    $composant = RationComposant::query()->withoutGlobalScopes()->create(['ration_id' => $ration->id, 'ordre' => 1, 'source' => 'lot_stock', 'lot_stock_id' => $lot->id, 'kg_ms' => 4]);

    return compact('inventaire', 'lot', 'mouvement', 'periode', 'animaux', 'phase', 'ration', 'composant');
}

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->a = jeuStock($this->jeu->elevage24a);
    $this->b = jeuStock($this->jeu->elevage24b);
});

it('charge les valeurs par défaut de calculs/, les coefficients UGB et les seuls repères de RC-9 (version 5)', function () {
    $parametre = fn (string $cle) => (float) DB::table('app.parametre_defaut')->where('cle', $cle)->value('valeur');

    expect($parametre('pertes.stockage.sous_toit'))->toBe(0.03)
        ->and($parametre('pertes.distribution.ratelier_anneau'))->toBe(0.061)
        ->and($parametre('pertes.distribution.deroule_sol'))->toBe(0.13)
        ->and($parametre('ms.defaut.foin'))->toBe(85.0)
        ->and($parametre('ugb.kg_ms_par_ugb_jour'))->toBe(13.0)
        ->and($parametre('securite.marge'))->toBe(0.15)
        ->and($parametre('couverture.deficit'))->toBe(0.80)
        ->and(DB::table('ref.coefficient_ugb')->pluck('coefficient', 'code')->map(fn ($v) => (float) $v)->only(['VL', 'VA', 'GEN_1_2', 'BREBIS'])->all())
        ->toBe(['VL' => 1.02, 'VA' => 0.9, 'GEN_1_2' => 0.6, 'BREBIS' => 0.15])
        ->and(DB::table('ref.categorie_animale')->whereNull('coefficient_ugb_code')->orderBy('code')->pluck('code')->all())->toBe(['AGNEAU', 'BOUC_MALE', 'CHEVRETTE', 'VEAU'])
        ->and(DB::table('ref.besoin_journalier')->count())->toBe(39)
        ->and(DB::table('ref.besoin_journalier')->whereNotNull('reference_id')->count())->toBe(23)
        ->and(DB::table('ref.besoin_journalier')->where('valide', true)->count())->toBe(0)
        ->and(DB::table('ref.aliment_reference')->count())->toBe(0)
        ->and(DB::table('ref.version_referentiel')->max('numero'))->toBe('5');
});

it('n\'admet qu\'un inventaire courant par élevage et refuse deux phases chevauchantes d\'un même lot', function () {
    expect(fn () => Inventaire::query()->withoutGlobalScopes()->create([
        'elevage_id' => $this->jeu->elevage24a->id, 'date_inventaire' => '2027-01-15', 'type' => 'mi_hiver',
        'date_debut' => '2027-01-15', 'date_fin' => '2027-03-31', 'marge_securite' => 0.15,
    ]))->toThrow(QueryException::class);

    expect(fn () => Phase::query()->withoutGlobalScopes()->create(['lot_animaux_id' => $this->a['animaux']->id, 'stade' => 'tarissement',
        'date_debut' => '2027-03-01', 'date_fin' => '2027-04-30', 'effectif' => 100]))->toThrow(QueryException::class);
});

it('exige la référence correspondant à la source d\'un composant de ration', function () {
    expect(fn () => RationComposant::query()->withoutGlobalScopes()->create(['ration_id' => $this->a['ration']->id, 'ordre' => 2, 'source' => 'etiquette', 'kg_ms' => 0.1]))
        ->toThrow(QueryException::class);
});

it('cloisonne chaque objet des stocks et du troupeau par élevage, y compris à travers les chaînes (§ 6.3)', function () {
    $modeles = [Inventaire::class, LotStock::class, Mouvement::class, PeriodePhase::class, LotAnimaux::class, Phase::class, Ration::class, RationComposant::class];

    $this->actingAs($this->jeu->eleveur24a);
    foreach ($modeles as $modele) {
        expect($modele::query()->count())->toBe(1, $modele);
    }

    $this->actingAs($this->jeu->technicien24);
    foreach ($modeles as $modele) {
        expect($modele::query()->count())->toBe(2, $modele);
    }

    $this->actingAs($this->jeu->technicien47);
    foreach ($modeles as $modele) {
        expect($modele::query()->count())->toBe(0, $modele);
    }
});

it('ouvre la lecture à tous les rôles du périmètre et l\'écriture à l\'éleveur et au technicien (§ 2.3)', function () {
    $jeu = $this->jeu;

    foreach (['lot', 'mouvement', 'composant', 'phase'] as $objet) {
        expect(Gate::forUser($jeu->eleveur24a)->allows('view', $this->a[$objet]))->toBeTrue()
            ->and(Gate::forUser($jeu->eleveur24a)->allows('update', $this->a[$objet]))->toBeTrue()
            ->and(Gate::forUser($jeu->technicien24)->allows('update', $this->a[$objet]))->toBeTrue()
            ->and(Gate::forUser($jeu->veterinaire)->allows('view', $this->a[$objet]))->toBeTrue()
            ->and(Gate::forUser($jeu->veterinaire)->allows('update', $this->a[$objet]))->toBeFalse()
            ->and(Gate::forUser($jeu->veterinaireConseil24)->allows('update', $this->a[$objet]))->toBeFalse()
            ->and(Gate::forUser($jeu->admin24)->allows('update', $this->a[$objet]))->toBeFalse()
            ->and(Gate::forUser($jeu->eleveur24a)->allows('view', $this->b[$objet]))->toBeFalse()
            ->and(Gate::forUser($jeu->technicien47)->allows('view', $this->a[$objet]))->toBeFalse();
    }

    expect(Gate::forUser($jeu->eleveur24a)->allows('create', [Inventaire::class, $jeu->elevage24a]))->toBeTrue()
        ->and(Gate::forUser($jeu->eleveur24a)->allows('create', [LotAnimaux::class, $jeu->elevage24b]))->toBeFalse()
        ->and(Gate::forUser($jeu->technicien24)->allows('delete', $this->a['inventaire']))->toBeFalse()
        ->and(Gate::forUser($jeu->technicien24)->allows('delete', $this->a['animaux']))->toBeFalse()
        ->and(Gate::forUser($jeu->technicien24)->allows('delete', $this->a['lot']))->toBeTrue();
});

it('passe les stocks et le troupeau en lecture seule sous convention échue (T-32)', function () {
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage24a->id)
        ->update(['statut' => 'echue', 'date_fin' => now()->subDay()->toDateString()]);

    foreach ([$this->jeu->eleveur24a, $this->jeu->technicien24] as $user) {
        expect(Gate::forUser($user)->allows('update', $this->a['lot']))->toBeFalse()
            ->and(Gate::forUser($user)->allows('update', $this->a['ration']))->toBeFalse()
            ->and(Gate::forUser($user)->allows('create', [Inventaire::class, $this->jeu->elevage24a]))->toBeFalse()
            ->and(Gate::forUser($user)->allows('view', $this->a['lot']))->toBeTrue();
    }
});

it('fige un inventaire clos et un lot d\'animaux archivé', function () {
    $this->a['inventaire']->update(['statut' => Inventaire::STATUT_CLOS]);
    $this->a['animaux']->update(['archive_le' => now()]);

    expect(Gate::forUser($this->jeu->eleveur24a)->allows('update', $this->a['lot']->refresh()))->toBeFalse()
        ->and(Gate::forUser($this->jeu->eleveur24a)->allows('update', $this->a['mouvement']))->toBeFalse()
        ->and(Gate::forUser($this->jeu->eleveur24a)->allows('update', $this->a['composant']))->toBeFalse()
        ->and(Gate::forUser($this->jeu->eleveur24a)->allows('view', $this->a['composant']))->toBeTrue();
});
