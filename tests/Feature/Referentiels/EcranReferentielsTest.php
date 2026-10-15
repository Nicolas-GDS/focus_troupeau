<?php

use App\Livewire\Admin\Referentiels;
use App\Models\App\Journal;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\JeuCloisonnement;

// E24 — Administration : référentiels versionnés (EF-M10-02, EF-M10-03, ENF-EXP-04) ; ENF-SEC-04 (autorisation
// de chaque action) ; T-40 (accès à l'écran par rôle, route GET /admin/referentiels/{table}).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->versionInitiale = (int) DB::table('ref.version_referentiel')->max(DB::raw('numero::int'));
});

/**
 * Numéro de version relatif à la version courante au début du test (0 : courante, 1 : la suivante…) :
 * les tests ne dépendent pas du nombre de versions déjà livrées.
 */
function versionEcranE24(int $decalage): string
{
    return (string) (test()->versionInitiale + $decalage);
}

it('ouvre l\'écran aux seuls administrateurs, pour chaque table', function (string $profil, int $statut) {
    $this->actingAs($this->jeu->{$profil});

    foreach (['seuils', 'plages'] as $table) {
        expect($this->get(route('admin.referentiels', ['table' => $table]))->status())->toBe($statut, "{$profil} → {$table}");
    }
})->with([
    ['admin24', 200], ['adminGlobal', 200], ['chargeDeMission', 200], ['technicien24', 403], ['technicien47', 403],
    ['veterinaireConseil24', 403], ['eleveur24a', 403], ['eleveur47', 403], ['veterinaire', 403],
]);

it('répond 404 pour une table inconnue', function () {
    $this->actingAs($this->jeu->admin24)->get('/admin/referentiels/besoins')->assertNotFound();
});

it('renvoie un visiteur vers la connexion', function () {
    $this->get(route('admin.referentiels', ['table' => 'seuils']))->assertRedirect(route('login'));
});

it('propose l\'écran dans la navigation des administrateurs seulement', function () {
    $this->actingAs($this->jeu->admin24)->get(route('admin.gds'))->assertSee('Référentiels')
        ->assertSee(route('admin.referentiels', ['table' => 'seuils']), false);
    $this->actingAs($this->jeu->technicien24)->get(route('travail'))->assertDontSee('Référentiels');
});

it('affiche la version courante, les seuils actifs, leur source et le signalement « à valider »', function () {
    DB::table('ref.seuil_interpretation')->where('parametre_code', 'MAT')->where('classe', 'faible')->whereNull('type_fourrage_code')
        ->update(['statut_epistemique' => 'hypothese_locale']);

    $this->actingAs($this->jeu->admin24)->get(route('admin.referentiels', ['table' => 'seuils']))
        ->assertOk()
        ->assertSee('Version courante des référentiels : '.versionEcranE24(0))
        ->assertSee('ROCHEREAU2024')
        ->assertSee('À valider')
        ->assertSee('[−∞ ; 80[', false);

    $this->actingAs($this->jeu->admin24)->get(route('admin.referentiels', ['table' => 'plages']))
        ->assertOk()->assertSee('Plages de plausibilité')->assertSee('[75 ; 95]', false);
});

it('filtre les lignes par paramètre', function () {
    $this->actingAs($this->jeu->admin24);

    Livewire::test(Referentiels::class, ['table' => 'seuils'])
        ->set('filtreParametre', 'CU')
        ->assertSee('carence_probable')
        ->assertDontSee('faible_pour_luzerne');
});

it('crée un seuil depuis l\'écran, avec la décision du comité technique', function () {
    $this->actingAs($this->jeu->admin24);

    Livewire::test(Referentiels::class, ['table' => 'seuils'])
        ->call('nouveau')
        ->set('formulaire.parametre_code', 'MAT')
        ->set('formulaire.nature', 'nutritionnel')
        ->set('formulaire.borne_min', '80')
        ->set('formulaire.borne_max', '100')
        ->set('formulaire.classe', 'moyen')
        ->set('formulaire.gravite', '0')
        ->set('formulaire.sens', 'bas')
        ->set('decision', 'Comité technique du 15/10/2026')
        ->call('enregistrer')
        ->assertHasNoErrors()
        ->assertSet('formulaireOuvert', false);

    $ligne = DB::table('ref.seuil_interpretation')->where('classe', 'moyen')->first();
    expect($ligne->version_creation)->toBe(versionEcranE24(1))->and($ligne->statut_epistemique)->toBe('hypothese_locale')
        ->and(Journal::query()->where('action', 'modification_referentiel')->count())->toBe(1);
});

it('refuse depuis l\'écran un commentaire manquant, un chevauchement, un type hors famille', function () {
    $this->actingAs($this->jeu->admin24);
    $remplir = fn ($composant) => $composant->call('nouveau')
        ->set('formulaire.parametre_code', 'MAT')->set('formulaire.nature', 'nutritionnel')
        ->set('formulaire.classe', 'moyen')->set('formulaire.gravite', '1')->set('formulaire.sens', 'bas');

    $remplir(Livewire::test(Referentiels::class, ['table' => 'seuils']))
        ->set('formulaire.borne_min', '80')->set('formulaire.borne_max', '100')
        ->call('enregistrer')->assertHasErrors(['decision' => 'required']);

    $remplir(Livewire::test(Referentiels::class, ['table' => 'seuils']))
        ->set('formulaire.borne_min', '70')->set('formulaire.borne_max', '90')->set('decision', 'Décision du comité')
        ->call('enregistrer')->assertHasErrors('formulaire');

    $remplir(Livewire::test(Referentiels::class, ['table' => 'seuils']))
        ->set('formulaire.borne_max', '120')->set('formulaire.famille_fourrage_code', 'ENRUB')
        ->set('formulaire.type_fourrage_code', 'FOIN_LUZ')->set('decision', 'Décision du comité')
        ->call('enregistrer')->assertHasErrors('formulaire.type_fourrage_code');

    $remplir(Livewire::test(Referentiels::class, ['table' => 'seuils']))
        ->set('formulaire.borne_min', '1,5')->set('formulaire.borne_max', 'abc')->set('decision', 'Décision du comité')
        ->call('enregistrer')->assertHasErrors('formulaire.borne_max');

    expect(DB::table('ref.version_referentiel')->count())->toBe($this->versionInitiale);
});

it('modifie et retire une ligne depuis l\'écran', function () {
    $this->actingAs($this->jeu->adminGlobal);
    $id = (int) DB::table('ref.seuil_interpretation')->where('parametre_code', 'MAT')->where('classe', 'faible')->value('id');

    Livewire::test(Referentiels::class, ['table' => 'seuils'])
        ->call('modifier', $id)
        ->assertSet('formulaire.classe', 'faible')
        ->set('formulaire.borne_max', '85')
        ->set('decision', 'Relèvement du seuil MAT')
        ->call('enregistrer')
        ->assertHasNoErrors();

    $nouveau = (int) DB::table('ref.seuil_interpretation')->where('parametre_code', 'MAT')->where('classe', 'faible')->whereNull('version_fin')->value('id');
    expect($nouveau)->not->toBe($id)->and(DB::table('ref.seuil_interpretation')->find($id)->version_fin)->toBe(versionEcranE24(1));

    Livewire::test(Referentiels::class, ['table' => 'seuils'])
        ->call('demanderRetrait', $nouveau)
        ->call('retirer')->assertHasErrors('decision')
        ->set('decision', 'Retrait décidé par le comité')
        ->call('retirer')->assertHasNoErrors();

    expect(DB::table('ref.seuil_interpretation')->find($nouveau)->version_fin)->toBe(versionEcranE24(2));
});

it('modifie une plage de plausibilité depuis l\'écran', function () {
    $this->actingAs($this->jeu->admin24);
    $id = (int) DB::table('ref.plage_plausibilite')->where('parametre_code', 'NA')->value('id');

    Livewire::test(Referentiels::class, ['table' => 'plages'])
        ->call('modifier', $id)
        ->set('formulaire.borne_max', '8')
        ->set('decision', 'Na : borne haute portée à 8 g/kg MS')
        ->call('enregistrer')
        ->assertHasNoErrors();

    expect((float) DB::table('ref.plage_plausibilite')->where('parametre_code', 'NA')->whereNull('version_fin')->value('borne_max'))->toBe(8.0);
});

it('affiche l\'historique des versions', function () {
    $this->actingAs($this->jeu->admin24);

    Livewire::test(Referentiels::class, ['table' => 'seuils'])
        ->call('afficher', 'historique')
        ->assertSee('Version 2')
        ->assertSee('Version 1')
        ->assertSee('Grille initiale du document 02');
});

it('vérifie l\'autorisation d\'écriture côté serveur dans chaque action (ENF-SEC-04)', function () {
    // Administrateur privé de la permission d'écriture (lecture conservée) : chaque action est refusée.
    Role::findByName('admin')->revokePermissionTo('referentiel.ecrire');
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($this->jeu->admin24->refresh());

    $composant = fn () => Livewire::test(Referentiels::class, ['table' => 'seuils'])->assertOk()->assertDontSee('Nouveau seuil');
    $composant()->call('nouveau')->assertForbidden();
    $composant()->call('modifier', 1)->assertForbidden();
    $composant()->call('demanderRetrait', 1)->assertForbidden();
    $composant()->set('decision', 'Décision du comité')->call('enregistrer')->assertForbidden();
    $composant()->set('decision', 'Décision du comité')->call('retirer')->assertForbidden();

    expect(DB::table('ref.version_referentiel')->count())->toBe($this->versionInitiale);
});

it('refuse le composant à un technicien', function () {
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(Referentiels::class, ['table' => 'seuils'])->assertForbidden();
});
