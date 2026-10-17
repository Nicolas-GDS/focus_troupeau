<?php

use App\Domain\Referentiels\Referentiels;
use App\Livewire\Admin\ParametresDefaut;
use App\Livewire\Admin\TextesTypes;
use App\Services\Notifications\TextesTypes as Textes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// E24 étendu (EF-M10-02, EF-M10-03, § 3.13 « Précisions du lot L7 ») : paramètres par défaut datés, nationaux ou
// propres au GDS, et textes types versionnés ; décision du comité obligatoire, journal (ENF-SEC-08).
uses(RefreshDatabase::class);

afterEach(fn () => Carbon::setTestNow());

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->version = fn () => (int) DB::table('ref.version_referentiel')->selectRaw("max(CASE WHEN numero ~ '^[0-9]+$' THEN numero::int END) AS n")->value('n');
});

it('modifie une valeur nationale : ligne datée du jour, ancienne close la veille, nouvelle version (EF-M10-02)', function () {
    Carbon::setTestNow(now()->addDays(2)); // les valeurs de référence sont datées du jour de leur chargement
    $avant = ($this->version)();
    $this->actingAs($this->jeu->adminGlobal);

    Livewire::test(ParametresDefaut::class)->assertSee('securite.marge')
        ->call('choisir', 'securite.marge')->set('saisie.valeur', '0,18')->call('enregistrer')->assertHasErrors('saisie.decision')
        ->set('saisie.decision', 'Comité du 16/10/2026')->call('enregistrer')->assertHasNoErrors()
        ->call('choisir', 'securite.marge')->set('saisie.valeur', '0,2')->set('saisie.decision', 'Correction le jour même')->call('enregistrer')->assertHasNoErrors()
        ->call('choisir', 'securite.marge')->set('saisie.valeur', '1,5')->set('saisie.decision', 'Erreur')->call('enregistrer')->assertHasErrors('saisie.valeur');

    $lignes = DB::table('app.parametre_defaut')->where('cle', 'securite.marge')->whereNull('gds_id')->orderBy('valide_du')->get();
    expect(app(Referentiels::class)->parametre('securite.marge', 24))->toBe(0.2)
        ->and($lignes)->toHaveCount(2)
        ->and($lignes[0]->valide_au)->toBe(today()->subDay()->toDateString())
        ->and(($this->version)())->toBe($avant + 2)
        ->and(DB::table('app.journal')->where('action', 'modification_referentiel')->where('objet', 'like', 'app.parametre_defaut#%')->count())->toBe(2);
});

it('laisse l\'administrateur de GDS fixer la valeur de son GDS, pas la valeur nationale', function () {
    $this->actingAs($this->jeu->admin24);
    Livewire::test(ParametresDefaut::class)
        ->call('choisir', 'prix.foin_t_ms')->set('saisie.gds', '')->set('saisie.valeur', '180')->set('saisie.decision', 'Prix GDS 24')->call('enregistrer')->assertHasErrors('saisie.gds')
        ->set('saisie.gds', '24')->call('enregistrer')->assertHasNoErrors();

    expect(app(Referentiels::class)->parametre('prix.foin_t_ms', 24))->toBe(180.0)
        ->and(app(Referentiels::class)->parametre('prix.foin_t_ms', 47))->toBe(170.0);
});

it('modifie un texte type en conservant ses variables (EF-M10-03)', function () {
    $this->actingAs($this->jeu->admin24);
    Livewire::test(TextesTypes::class)->assertSee('notification.autonomie.objet')
        ->call('choisir', 'notification.autonomie.objet')
        ->set('texte', 'Autonomie : rupture prochaine')->set('decision', 'Comité')->call('enregistrer')->assertHasErrors('texte')
        ->set('texte', 'Autonomie fourragère : rupture annoncée le {date}')->call('enregistrer')->assertHasNoErrors();

    expect(app(Textes::class)->texte('notification.autonomie.objet', ['date' => '23/12/2026']))->toBe('Autonomie fourragère : rupture annoncée le 23/12/2026')
        ->and(DB::table('ref.texte_type')->where('code', 'notification.autonomie.objet')->count())->toBe(2)
        ->and(DB::table('ref.texte_type')->where('code', 'notification.autonomie.objet')->whereNotNull('version_fin')->count())->toBe(1);
});

it('réserve ces écrans aux administrateurs', function () {
    $this->actingAs($this->jeu->technicien24);
    $this->get(route('admin.referentiels', ['table' => 'parametres']))->assertForbidden();
    $this->actingAs($this->jeu->admin24);
    $this->get(route('admin.referentiels', ['table' => 'textes']))->assertOk()->assertSee('Textes types');
    $this->get(route('admin.referentiels', ['table' => 'parametres']))->assertOk()->assertSee('Paramètres par défaut');
});
