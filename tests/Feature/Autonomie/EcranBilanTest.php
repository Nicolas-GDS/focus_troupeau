<?php

use App\Jobs\CalculerBilan;
use App\Jobs\EnvoyerNotification;
use App\Jobs\EvaluerAlertesAutonomie;
use App\Livewire\Autonomie\Bilan as EcranBilan;
use App\Models\App\Convention;
use App\Models\App\Notification;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\Bilan;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\PeriodePhase;
use App\Services\Autonomie\AlerteAutonomie;
use App\Services\Stocks\StocksService;
use Database\Seeders\JeuJ1Seeder;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

// E14 — autonomie, bilan et projection (EF-M6-01 à EF-M6-05) sur J1 ; recalcul après un mouvement (T-20) ; alerte
// d'autonomie (EF-M8-03, T-21) ; autonomie sur l'accueil (E02). Le jeu est chargé au 18 octobre 2026 pour que les
// alertes ne dépendent pas de la date d'exécution.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([EnvoyerNotification::class]);
    Mail::fake();
    Carbon::setTestNow('2026-10-18 08:00:00');
    (new JeuJ1Seeder)->run();
    $this->elevage = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ1Seeder::CODE)->sole();
    $this->inventaire = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->where('statut', Inventaire::STATUT_COURANT)->sole();
    $this->eleveur = User::query()->where('email', JeuJ1Seeder::ELEVEUR)->sole();
    $this->technicien = User::query()->where('email', JeuJ1Seeder::TECHNICIEN)->sole();
    $this->bilan = fn () => Bilan::query()->withoutGlobalScopes()->where('inventaire_id', $this->inventaire->id)->whereNull('scenario_id')->sole();
    $this->alertes = fn () => Notification::query()->where('type', AlerteAutonomie::TYPE)->get();
});

afterEach(fn () => Carbon::setTestNow());

it('affiche à l\'éleveur les trois indicateurs, leur fourchette, le repère d\'action et la projection (EF-M6-01 à EF-M6-04)', function () {
    $this->actingAs($this->eleveur);

    $this->get(route('autonomie', $this->elevage->id))->assertOk()
        ->assertSee('Couverture des besoins')->assertSee('93 %')
        ->assertSee('139')->assertSee('Rupture prévue le')->assertSee('23/12/2026')
        ->assertSee('Déficit (sécurité déduite)')->assertSee('49,5')
        ->assertSee('222,2')->assertSee('33,3')
        ->assertSee('Au-dessus du repère')->assertDontSee('En dessous du repère')
        ->assertSee('un ajustement de la ration suffit généralement', false)
        ->assertSee('Repère : 80 % de couverture')
        ->assertSee('Stock projeté et besoin cumulé sur la période (t MS)')->assertSee('Fourchette d\'incertitude', false)
        ->assertSee('novembre 2026')->assertSee('mars 2027')
        ->assertSee('Foin 2025 : épuisé le 03/01/2027')
        ->assertSee('Marge de sécurité')->assertSee('RC-12');
});

it('ajoute et retire les phases de la période de référence, coefficients par défaut du référentiel (RC-10)', function () {
    $this->actingAs($this->technicien);

    Livewire::test(EcranBilan::class, ['elevage' => $this->elevage])
        ->set('nouvellePeriode', ['libelle' => 'hivernage', 'debut' => '2026-11-01', 'fin' => '2027-03-15', 'coefficient' => '0,3'])->call('ajouterPeriode')->assertHasNoErrors()
        ->set('nouvellePeriode', ['libelle' => 'transition', 'debut' => '2027-03-16', 'fin' => '2027-03-31', 'coefficient' => '0,9'])->call('ajouterPeriode')->assertHasNoErrors()
        ->set('nouvellePeriode', ['libelle' => 'transition', 'debut' => '2027-03-20', 'fin' => '2027-03-25', 'coefficient' => ''])->call('ajouterPeriode')->assertHasErrors('periode')
        ->set('nouvellePeriode', ['libelle' => 'hivernage', 'debut' => '2027-04-01', 'fin' => '2027-04-10', 'coefficient' => ''])->call('ajouterPeriode')->assertHasErrors('periode')
        ->assertSee('Transition de mise à l\'herbe')->assertSee('143 jours équivalents');

    $phases = PeriodePhase::query()->where('inventaire_id', $this->inventaire->id)->orderBy('date_debut')->get();
    expect($phases->pluck('coefficient')->map(fn ($c) => (float) $c)->all())->toBe([1.0, 0.5])
        ->and(($this->bilan)()->detail['voie_ugb']['jours_equivalents'])->toEqualWithDelta(135 + 16 * 0.5, 1e-9);

    Livewire::test(EcranBilan::class, ['elevage' => $this->elevage])->call('supprimerPeriode', $phases[1]->id);
    expect(PeriodePhase::query()->where('inventaire_id', $this->inventaire->id)->count())->toBe(1);
});

it('lit sans saisir sous convention échue, et réserve l\'écran à l\'éleveur et au technicien (T-32, annexe 10)', function () {
    Convention::query()->where('elevage_id', $this->elevage->id)->update(['date_fin' => '2026-08-31']);
    $this->actingAs($this->eleveur);

    Livewire::test(EcranBilan::class, ['elevage' => $this->elevage])->assertSee('139')->assertDontSee('Ajouter')
        ->set('nouvellePeriode', ['libelle' => 'hivernage', 'debut' => '2026-11-01', 'fin' => '2027-03-31', 'coefficient' => ''])->call('ajouterPeriode')->assertForbidden();
});

it('met plan.bilan à jour en moins de 5 s après la saisie d\'un mouvement (T-20)', function () {
    Carbon::setTestNow();
    $avant = ($this->bilan)()->calcule_le;
    $debut = microtime(true);
    app(StocksService::class)->ajouterMouvement(LotStock::query()->withoutGlobalScopes()->where('inventaire_id', $this->inventaire->id)->where('libelle', 'Enrubannage 2026')->sole(), 'achat', '2026-12-15', 30.0);
    $duree = microtime(true) - $debut;
    $bilan = ($this->bilan)();

    expect($duree)->toBeLessThan(5.0)
        ->and($bilan->calcule_le->equalTo($avant))->toBeFalse()
        ->and($bilan->date_rupture->toDateString())->toBe('2027-01-30');
});

it('passe par un job CalculerBilan unique par inventaire, dispatché en fin de transaction (chapitre 6.4)', function () {
    Queue::fake([CalculerBilan::class]);
    $lot = LotStock::query()->withoutGlobalScopes()->where('inventaire_id', $this->inventaire->id)->where('libelle', 'Paille')->sole();

    DB::transaction(function () use ($lot) {
        app(StocksService::class)->ajouterMouvement($lot, 'achat', '2026-12-01', 5.0);
        app(StocksService::class)->ajouterMouvement($lot, 'vente', '2026-12-10', 1.0);
        Queue::assertNothingPushed();
    });

    Queue::assertPushed(CalculerBilan::class, 1);
    Queue::assertPushed(CalculerBilan::class, fn (CalculerBilan $j) => $j->inventaireId === $this->inventaire->id);
});

it('alerte l\'éleveur et le technicien une seule fois quand la rupture passe à 59 jours (T-21, EF-M8-03)', function () {
    expect(($this->alertes)())->toBeEmpty();

    Carbon::setTestNow('2026-10-25 08:00:00'); // 23/12/2026 − 59 jours
    expect(app(AlerteAutonomie::class)->evaluer($this->inventaire))->toBeTrue();
    $alertes = ($this->alertes)();

    expect($alertes->pluck('user_id')->sort()->values()->all())->toContain($this->eleveur->id, $this->technicien->id)
        ->and($alertes->firstWhere('user_id', $this->eleveur->id)->objet)->toBe('Autonomie fourragère : rupture prévue le 23/12/2026')
        ->and($alertes->firstWhere('user_id', $this->eleveur->id)->corps)->toContain('soit dans 59 jours')
        ->and($alertes->firstWhere('user_id', $this->technicien->id)->objet)->toStartWith('Copie technicien — ')
        ->and(($this->bilan)()->alerte_envoyee)->toBeTrue();

    // recalcul suivant : aucune seconde alerte
    app(StocksService::class)->ajouterMouvement(LotStock::query()->withoutGlobalScopes()->where('inventaire_id', $this->inventaire->id)->where('libelle', 'Paille')->sole(), 'achat', '2026-12-01', 1.0);
    (new EvaluerAlertesAutonomie)->handle(app(AlerteAutonomie::class));
    expect(($this->alertes)())->toHaveCount($alertes->count());
});

it('rouvre l\'alerte après un retour au-dessus du seuil, et prévient le technicien de la dernière visite', function () {
    Carbon::setTestNow('2026-10-25 08:00:00');
    app(AlerteAutonomie::class)->evaluer($this->inventaire);
    $premieres = ($this->alertes)()->count();

    // achat d'enrubannage : rupture repoussée au 30/01/2027, au-delà du seuil
    app(StocksService::class)->ajouterMouvement(LotStock::query()->withoutGlobalScopes()->where('inventaire_id', $this->inventaire->id)->where('libelle', 'Enrubannage 2026')->sole(), 'achat', '2026-12-15', 30.0);
    expect(($this->bilan)()->alerte_envoyee)->toBeFalse();

    $autre = User::query()->whereHas('roles', fn ($q) => $q->where('name', 'technicien'))->whereKeyNot($this->technicien->id)->where('actif', true)
        ->whereHas('gds', fn ($q) => $q->where('id', 24))->first() ?? $this->technicien;
    DB::table('dossier.visite')->insert(['elevage_id' => $this->elevage->id, 'campagne_code' => '2025-2026', 'date_visite' => '2026-10-01', 'technicien_id' => $autre->id, 'motif' => 'suivi', 'statut' => 'en_cours']);
    Carbon::setTestNow('2026-12-02 08:00:00'); // 30/01/2027 − 59 jours
    expect(app(AlerteAutonomie::class)->evaluer($this->inventaire))->toBeTrue();
    $nouvelles = ($this->alertes)()->skip($premieres);

    expect($nouvelles->pluck('user_id')->all())->toContain($this->eleveur->id, $autre->id)
        ->and($nouvelles->filter(fn ($n) => str_starts_with($n->objet, 'Copie technicien'))->pluck('user_id')->values()->all())->toBe([$autre->id]);
});

it('alerte quand le stock de sécurité est entamé, et planifie l\'évaluation chaque nuit', function () {
    $entame = ($this->bilan)()->date_entame_securite;
    Carbon::setTestNow($entame->copy()->setTime(8, 0));
    Bilan::query()->withoutGlobalScopes()->whereKey(($this->bilan)()->id)->update(['date_rupture' => null]);

    expect(app(AlerteAutonomie::class)->evaluer($this->inventaire))->toBeTrue()
        ->and(collect(app(Schedule::class)->events())->pluck('description')->all())->toContain('alertes-autonomie');
});

it('montre l\'autonomie sur l\'accueil de l\'éleveur d\'après le dernier bilan (E02, EF-M6-05)', function () {
    $this->actingAs($this->eleveur);

    $this->get(route('accueil'))->assertOk()
        ->assertSee('Autonomie fourragère')->assertSee('139 jours')->assertSee('23/12/2026')->assertSee('93 %')
        ->assertSee('Voir le bilan et la projection');
});
