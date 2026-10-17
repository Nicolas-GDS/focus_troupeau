<?php

use App\Domain\Autonomie\Levier;
use App\Jobs\EnvoyerNotification;
use App\Livewire\Autonomie\Approvisionnement;
use App\Livewire\Autonomie\ScenarioEcran;
use App\Models\App\Convention;
use App\Models\App\Notification;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\LevierLigne;
use App\Models\Plan\Scenario;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\Mouvement;
use App\Services\Autonomie\ApprovisionnementService;
use App\Services\Autonomie\BilanService;
use App\Services\Dossier\SaisieRefusee;
use Database\Seeders\JeuJ1Seeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// E15 — approvisionnement (EF-M7-01 à EF-M7-06, RC-13) sur J1 : proposition initiale (étapes 1 et 2 chiffrées),
// leviers de RC-13.3 (mêmes valeurs que T-15), comparaison de quatre scénarios et un seul retenu (T-23), lots et
// mouvements d'achat créés à la retenue et retirés ensuite (T-24), technicien prévenu, cloisonnement (T-32, T-40).
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([EnvoyerNotification::class]);
    Mail::fake();
    (new JeuJ1Seeder)->run();
    $this->elevage = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ1Seeder::CODE)->sole();
    $this->inventaire = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->where('statut', Inventaire::STATUT_COURANT)->sole();
    $this->eleveur = User::query()->where('email', JeuJ1Seeder::ELEVEUR)->sole();
    $this->technicien = User::query()->where('email', JeuJ1Seeder::TECHNICIEN)->sole();
    $this->service = app(ApprovisionnementService::class);
    // Besoins prélevés de T-14 (pertes à la distribution 13 %) et sécurité de 15 %
    $this->besoinFoin = (100 * 4.0 + 30 * 5.0) / 0.87 / 1000 * 151;
    $this->besoinEnrub = 100 * 7.0 / 0.87 / 1000 * 151;
    $this->stock = fn (string $libelle) => (float) LotStock::query()->withoutGlobalScopes()->where('inventaire_id', $this->inventaire->id)->where('libelle', $libelle)->sole()->stock_utile_t_ms;
});

it('propose de réaffecter l\'excédent de foin vers l\'enrubannage puis d\'acheter le reste au prix indicatif (RC-13.1, RC-13.2, EF-M7-01)', function () {
    $p = $this->service->proposition($this->inventaire);
    $excedentFoin = ($this->stock)('Foin 2025') + ($this->stock)('Foin 2026') - 1.15 * $this->besoinFoin;
    $deficitEnrub = 1.15 * $this->besoinEnrub - ($this->stock)('Enrubannage 2026');

    expect($p['deficits'])->toHaveKeys(['ENRUB'])->not->toHaveKeys(['FOIN', 'PAILLE'])
        ->and($p['deficits']['ENRUB'])->toEqualWithDelta($deficitEnrub, 1e-9)
        ->and($p['excedents']['FOIN'])->toEqualWithDelta($excedentFoin, 1e-9)
        ->and($p['lignes'])->toHaveCount(2)
        ->and([$p['lignes'][0]['levier'], $p['lignes'][0]['source'], $p['lignes'][0]['famille']])->toBe([Levier::REAFFECTATION, 'FOIN', 'ENRUB'])
        ->and($p['lignes'][0]['quantite'])->toEqualWithDelta($excedentFoin, 1e-9)
        ->and([$p['lignes'][1]['levier'], $p['lignes'][1]['famille'], $p['lignes'][1]['prix']])->toBe([Levier::ACHAT, 'ENRUB', 190.0])
        ->and($p['lignes'][1]['cout'])->toEqualWithDelta(($deficitEnrub - $excedentFoin) * 190, 1e-6)
        ->and($p['lignes'][1]['consequence'])->toContain('analysé')
        ->and(collect($p['residuels'])->pluck('etape')->unique()->diff([3, 4])->all())->toBe([]);
});

it('crée un scénario depuis la proposition : plus de rupture, coût de l\'achat (RC-13.4)', function () {
    $scenario = $this->service->creerScenario($this->inventaire, 'Proposition', true);
    $e = $this->service->evaluer($scenario);
    $achat = $e['lignes']->firstWhere('levier_code', Levier::ACHAT);

    expect($scenario->origine)->toBe('proposition')
        ->and($e['lignes']->where('levier_code', Levier::REAFFECTATION))->toHaveCount(1)
        ->and($e['bilan']->date_rupture)->toBeNull()
        ->and($e['cout'])->toEqualWithDelta((float) $achat->quantite * 190, 1e-6)
        ->and($e['vigilance'])->toContain('Prix indicatif à valider : achat de fourrage à 190 €/t MS.')
        ->and($e['vigilance'])->toContain('Fourrage acheté : valeurs nutritionnelles inconnues, à faire analyser.')
        // le bilan de l'inventaire n'est pas touché par un scénario non retenu
        ->and(app(BilanService::class)->dernier($this->elevage->id)->date_rupture->toDateString())->toBe('2026-12-23');
});

it('chiffre les leviers du catalogue comme T-15 (RC-13.3)', function () {
    $s = $this->service->creerScenario($this->inventaire, 'Leviers', false);
    $ligne = fn (array $d) => $this->service->enregistrerLigne($s, $d);
    $besoinRation = 193.28;
    $prelevement = app(BilanService::class)->calculer($this->inventaire)['centrale']->besoinStock;

    $concentre = $ligne(['levier_code' => Levier::CONCENTRE, 'quantite' => 10, 'famille_code' => 'FOIN']);
    $decap = $ligne(['levier_code' => Levier::DECAPITALISATION, 'quantite' => 5]);
    $genisses = $ligne(['levier_code' => Levier::GENISSES, 'quantite' => 2]);
    $paturage = $ligne(['levier_code' => Levier::PATURAGE, 'quantite' => 10, 'parametre' => 500]);
    $ratelier = $ligne(['levier_code' => Levier::RATELIER, 'quantite' => 0, 'parametre' => 0.04]);
    $achat = $ligne(['levier_code' => Levier::ACHAT, 'quantite' => 20, 'famille_code' => 'FOIN', 'parametre' => 160]);

    expect([(float) $concentre->gain_t_ms, (float) $concentre->cout_eur])->toEqualWithDelta([4.4, 2900.0], 1e-9)
        ->and([(float) $decap->gain_t_ms, (float) $decap->cout_eur])->toEqualWithDelta([5 * 13 * 151 / 1000, 4500.0], 1e-9)
        ->and([(float) $genisses->gain_t_ms, (float) $genisses->cout_eur])->toEqualWithDelta([12.0, 0.0], 1e-9)
        ->and([(float) $paturage->gain_t_ms, (float) $paturage->cout_eur])->toEqualWithDelta([10 * $prelevement / 151, 500.0], 1e-6)
        ->and((float) $ratelier->gain_t_ms)->toEqualWithDelta($besoinRation * (1 / 0.87 - 1 / 0.96), 1e-6)
        ->and([(float) $achat->gain_t_ms, (float) $achat->cout_eur, (float) $achat->prix_unitaire])->toEqualWithDelta([20.0, 3200.0, 160.0], 1e-9)
        // décapitalisation : gain imputé à la famille du plus fort déficit, à la date de début de période
        ->and([$decap->famille_code, $decap->date_effet->toDateString()])->toBe(['ENRUB', '2026-11-01'])
        ->and($concentre->commentaire)->toContain('se substitue')
        ->and(fn () => $ligne(['levier_code' => Levier::CONCENTRE, 'quantite' => 5, 'famille_code' => 'ENRUB']))->toThrow(SaisieRefusee::class)
        ->and(fn () => $ligne(['levier_code' => Levier::REAFFECTATION, 'quantite' => 5, 'famille_code' => 'ENRUB']))->toThrow(SaisieRefusee::class);
});

it('compare quatre scénarios côte à côte et n\'en retient qu\'un à la fois (T-23)', function () {
    $ids = collect(range(1, 5))->map(fn (int $i) => $this->service->creerScenario($this->inventaire, "Scénario {$i}", $i === 1)->id)->all();
    $comparaison = $this->service->comparer($this->inventaire, array_slice($ids, 0, 4));

    expect($comparaison)->toHaveCount(4)
        ->and(collect($comparaison)->pluck('bilan.date_rupture')->filter()->count())->toBe(3)
        ->and(fn () => $this->service->comparer($this->inventaire, $ids))->toThrow(SaisieRefusee::class);

    $this->service->retenir(Scenario::query()->find($ids[0]), $this->eleveur);
    $this->service->retenir(Scenario::query()->find($ids[1]), $this->eleveur);
    expect(Scenario::query()->where('inventaire_id', $this->inventaire->id)->where('statut', Scenario::STATUT_RETENU)->pluck('id')->all())->toBe([$ids[1]])
        ->and(fn () => DB::transaction(fn () => Scenario::query()->whereKey($ids[2])->update(['statut' => Scenario::STATUT_RETENU])))->toThrow(QueryException::class);

    $this->actingAs($this->eleveur);
    Livewire::test(Approvisionnement::class, ['elevage' => $this->elevage])
        ->set('compares', array_map('strval', $ids))->assertHasErrors('compares')->assertSet('compares', array_slice($ids, 0, 4))
        ->assertSee('Comparaison')->assertSee('Lots d\'animaux en déficit nutritionnel', false)->assertSee('Points de vigilance');
});

it('crée le lot et le mouvement d\'achat liés à la ligne quand le scénario est retenu, les retire ensuite (T-24, EF-M7-05)', function () {
    $achat = $this->service->creerScenario($this->inventaire, 'Achat d\'enrubannage', false);
    $ligne = $this->service->enregistrerLigne($achat, ['levier_code' => Levier::ACHAT, 'quantite' => 30, 'famille_code' => 'ENRUB', 'date_effet' => '2026-12-15']);
    $this->service->retenir($achat, $this->eleveur);

    $mouvement = Mouvement::query()->withoutGlobalScopes()->where('scenario_ligne_id', $ligne->id)->sole();
    $lot = LotStock::query()->withoutGlobalScopes()->where('scenario_ligne_id', $ligne->id)->sole();
    expect([$mouvement->type, $mouvement->date_mouvement->toDateString(), (float) $mouvement->quantite_t_ms, (float) $mouvement->prix_eur])->toBe(['achat', '2026-12-15', 30.0, 5700.0])
        ->and([$lot->famille_code, (float) $lot->stock_utile_t_ms, $lot->inventaire_id, $mouvement->lot_stock_id])->toBe(['ENRUB', 0.0, $this->inventaire->id, $lot->id])
        ->and(app(BilanService::class)->dernier($this->elevage->id)->date_rupture->toDateString())->toBe('2027-01-30')
        // le scénario retenu s'évalue sans compter deux fois ses achats
        ->and($this->service->evaluer($achat->refresh())['bilan']->date_rupture->toDateString())->toBe('2027-01-30');

    $notification = Notification::query()->where('type', 'scenario_retenu')->where('user_id', $this->technicien->id)->sole();
    expect($notification->objet)->toContain('Scénario d\'approvisionnement retenu')->and($notification->corps)->toContain('Achat d\'enrubannage');

    $this->service->retenir($this->service->creerScenario($this->inventaire, 'Sans achat', false), $this->eleveur);
    expect(Mouvement::query()->withoutGlobalScopes()->where('scenario_ligne_id', $ligne->id)->exists())->toBeFalse()
        ->and(LotStock::query()->withoutGlobalScopes()->where('scenario_ligne_id', $ligne->id)->exists())->toBeFalse()
        ->and($achat->refresh()->statut)->toBe(Scenario::STATUT_BROUILLON)
        ->and(app(BilanService::class)->dernier($this->elevage->id)->date_rupture->toDateString())->toBe('2026-12-23');
});

it('ouvre E15 à l\'éleveur : proposition, scénario, saisie d\'un levier avec le prix indicatif signalé (EF-M7-03)', function () {
    $this->actingAs($this->eleveur);
    $this->get(route('approvisionnement', $this->elevage->id))->assertOk()
        ->assertSee('Proposition initiale')->assertSee('Réaffectation d\'un stock excédentaire')->assertSee('prix indicatif à valider');

    Livewire::test(Approvisionnement::class, ['elevage' => $this->elevage])->set('libelle', 'Mon scénario')->call('creer', true)->assertRedirect();
    $scenario = Scenario::query()->where('libelle', 'Mon scénario')->sole();

    Livewire::test(ScenarioEcran::class, ['elevage' => $this->elevage, 'scenario' => $scenario->id])
        ->assertSee('Mon scénario')->assertSee('Points de vigilance')
        ->set('ligne.levier_code', Levier::ACHAT)->set('ligne.famille_code', 'PAILLE')->assertSee('Défaut : 75')
        ->set('ligne.quantite', '5,5')->call('enregistrerLigne')->assertHasNoErrors()
        ->call('retenir')->assertHasNoErrors()->assertSee('Retenu le');

    expect(LevierLigne::query()->where('scenario_id', $scenario->id)->where('famille_code', 'PAILLE')->sole()->cout_eur)->toEqual('412.5');
    $this->get(route('scenarios.voir', [$this->elevage->id, $scenario->id]))->assertOk()->assertSee('Retenu le');
});

it('cloisonne les scénarios : autre élevage introuvable, lecture seule sous convention échue (T-40, T-32)', function () {
    $scenario = $this->service->creerScenario($this->inventaire, 'J1', true);
    $jeu = JeuCloisonnement::creer();

    $this->actingAs($jeu->eleveur24a);
    $this->get(route('scenarios.voir', [$this->elevage->id, $scenario->id]))->assertNotFound();
    $this->get(route('approvisionnement', $this->elevage->id))->assertNotFound();

    $this->actingAs($jeu->technicien47);
    $this->get(route('scenarios.voir', [$this->elevage->id, $scenario->id]))->assertNotFound();

    $this->actingAs($jeu->veterinaire);
    $this->get(route('approvisionnement', $jeu->elevage24a->id))->assertForbidden();

    Convention::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->update(['date_fin' => now()->subDay()->toDateString()]);
    $this->actingAs($this->eleveur);
    Livewire::test(ScenarioEcran::class, ['elevage' => $this->elevage, 'scenario' => $scenario->id])
        ->assertSee('J1')->assertDontSee('Ajouter le levier')->call('retenir')->assertForbidden();
    Livewire::test(Approvisionnement::class, ['elevage' => $this->elevage])->assertDontSee('Créer un scénario vide')->call('creer', false)->assertForbidden();
});
