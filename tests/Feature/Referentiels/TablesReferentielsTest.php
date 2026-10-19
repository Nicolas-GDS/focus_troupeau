<?php

use App\Domain\Referentiels\Edition\EditionRefusee;
use App\Domain\Referentiels\Edition\TableReferentiel;
use App\Livewire\Admin\Aliments;
use App\Livewire\Admin\TableReferentielEcran;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// E24 étendu au lot L8 (EF-M10-02) : besoins journaliers, repères des mesures terrain et des profils sanguins, seuils
// du bilan sanitaire (versionnés) ; aliments de table (journalisés).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->actingAs($this->jeu->admin24);
});

it('contrôle une saisie : obligatoires, nombres, choix, minimum ≤ maximum (domaine)', function () {
    $d = TableReferentiel::tous()['reperes_mesures'];
    $saisie = ['mesure_code' => 'PH_URINE', 'espece_code' => 'BOV', 'stade' => 'tarie', 'borne_basse' => '8,3', 'borne_haute' => '7,8', 'statut_epistemique' => 'publie'];

    expect(fn () => $d->normaliser($saisie, ['mesure_code' => ['PH_URINE'], 'espece_code' => ['BOV']]))->toThrow(EditionRefusee::class)
        ->and($d->normaliser([...$saisie, 'borne_haute' => '8,5'], ['mesure_code' => ['PH_URINE'], 'espece_code' => ['BOV']]))->toMatchArray(['borne_basse' => 8.3, 'borne_haute' => 8.5, 'valide' => false])
        ->and(fn () => $d->normaliser([...$saisie, 'mesure_code' => 'INCONNUE', 'borne_haute' => '9'], ['mesure_code' => ['PH_URINE'], 'espece_code' => ['BOV']]))->toThrow(EditionRefusee::class);
});

it('modifie un repère de mesure terrain dans une nouvelle version, l\'ancienne ligne close', function () {
    $ligne = DB::table('ref.repere_mesure_terrain')->whereNull('version_fin')->where('mesure_code', 'PH_URINE')->where('stade', 'tarie')->first();

    Livewire::test(TableReferentielEcran::class, ['table' => 'reperes_mesures'])
        ->assertSee('Repères des mesures terrain')->call('modifier', $ligne->id)
        ->set('saisie.borne_haute', '8,4')->set('saisie.valide', true)->call('enregistrer')->assertHasErrors('decision')
        ->set('decision', 'Comité technique du 18/10/2026')->call('enregistrer')->assertHasNoErrors();

    $nouvelle = DB::table('ref.repere_mesure_terrain')->whereNull('version_fin')->where('mesure_code', 'PH_URINE')->where('stade', 'tarie')->sole();
    expect((float) $nouvelle->borne_haute)->toBe(8.4)->and($nouvelle->valide)->toBeTrue()
        ->and(DB::table('ref.repere_mesure_terrain')->where('id', $ligne->id)->value('version_fin'))->toBe($nouvelle->version_creation)
        ->and(DB::table('app.journal')->where('action', 'modification_referentiel')->where('objet', 'like', 'ref.repere_mesure_terrain#%')->exists())->toBeTrue();
});

it('crée puis retire un besoin journalier (table sans colonne « actif »)', function () {
    $ecran = Livewire::test(TableReferentielEcran::class, ['table' => 'besoins'])->call('nouveau')
        ->set('saisie.categorie_code', 'BREBIS_ALL')->set('saisie.stade', 'lactation')->set('saisie.parametre_code', 'CU')
        ->set('saisie.mode', 'par_kg_ms')->set('saisie.base', 'animal')->set('saisie.valeur_min', '8')->set('saisie.maximum', '15')
        ->set('decision', 'Essai')->call('enregistrer')->assertHasNoErrors();
    $id = DB::table('ref.besoin_journalier')->whereNull('version_fin')->where('categorie_code', 'BREBIS_ALL')->where('parametre_code', 'CU')->where('stade', 'lactation')->orderByDesc('id')->value('id');

    $ecran->call('modifier', $id)->set('decision', 'Retrait d\'essai')->call('retirer', $id)->assertHasNoErrors();
    expect(DB::table('ref.besoin_journalier')->where('id', $id)->value('version_fin'))->not->toBeNull();
});

it('modifie un seuil du bilan sanitaire et refuse une valeur hors liste', function () {
    $ligne = DB::table('ref.seuil_bilan_sanitaire')->whereNull('version_fin')->where('indicateur_code', 'METRITE')->first();
    Livewire::test(TableReferentielEcran::class, ['table' => 'bilan_sanitaire'])
        ->call('modifier', $ligne->id)->set('saisie.sens', 'oblique')->set('decision', 'Essai')->call('enregistrer')->assertHasErrors('saisie.sens')
        ->set('saisie.sens', 'haut')->set('saisie.s2', '12')->call('enregistrer')->assertHasNoErrors();

    expect((float) DB::table('ref.seuil_bilan_sanitaire')->whereNull('version_fin')->where('indicateur_code', 'METRITE')->value('s2'))->toBe(12.0);
});

it('saisit un aliment de table et ses valeurs, journalisé', function () {
    Livewire::test(Aliments::class)->assertSee('Aucun aliment')->call('nouveau')
        ->set('aliment.code', 'tourteau_colza')->set('aliment.libelle', 'Tourteau de colza')->set('aliment.categorie', 'tourteau')->set('aliment.ms_pct', '89')
        ->set('valeurs.PDI', '144')->set('valeurs.UFL_2018', '0,97')->call('enregistrer')->assertHasNoErrors()->assertSee('Tourteau de colza');

    $id = DB::table('ref.aliment_reference')->where('code', 'TOURTEAU_COLZA')->value('id');
    expect(DB::table('ref.aliment_reference_valeur')->where('aliment_reference_id', $id)->pluck('valeur', 'parametre_code')->map(fn ($v) => (float) $v)->all())
        ->toEqual(['PDI' => 144.0, 'UFL_2018' => 0.97])
        ->and(DB::table('app.journal')->where('objet', 'ref.aliment_reference#'.$id)->exists())->toBeTrue();
});

it('ouvre chaque onglet à l\'administrateur, pas au technicien', function (string $table) {
    $this->get(route('admin.referentiels', ['table' => $table]))->assertOk();
    $this->actingAs($this->jeu->technicien24)->get(route('admin.referentiels', ['table' => $table]))->assertForbidden();
})->with(['besoins', 'reperes_mesures', 'reperes_animaux', 'bilan_sanitaire', 'aliments']);
