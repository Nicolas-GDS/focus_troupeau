<?php

use App\Domain\Autonomie\Levier;
use App\Jobs\EnvoyerNotification;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\Scenario;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\Mouvement;
use App\Services\Autonomie\ApprovisionnementService;
use App\Services\Autonomie\BilanService;
use App\Services\Pdf\RenduPdf;
use Database\Seeders\JeuJ1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

// Recette du lot L6 sur J1 (§ 9.1, § 9.3 « bilan J1 conforme aux valeurs attendues ») : bilan mémorisé, écrans E14,
// E15, E16 et PDF de l'éleveur, scénario retenu. Procédure : docs/recette/L6_recette_J1.md.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([EnvoyerNotification::class]);
    Mail::fake();
    app()->instance(RenduPdf::class, new class implements RenduPdf
    {
        public function pdf(string $html, string $piedDePage): string
        {
            return "%PDF-1.7\n".md5($html);
        }
    });
    (new JeuJ1Seeder)->run();
    $this->elevage = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ1Seeder::CODE)->sole();
    $this->inventaire = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->where('statut', Inventaire::STATUT_COURANT)->sole();
    $this->eleveur = User::query()->where('email', JeuJ1Seeder::ELEVEUR)->sole();
});

it('donne le bilan J1 attendu au § 9.1', function () {
    $b = app(BilanService::class)->dernier($this->elevage->id);
    $foin25 = LotStock::query()->withoutGlobalScopes()->where('inventaire_id', $this->inventaire->id)->where('libelle', 'Foin 2025')->sole();

    expect([round((float) $b->stock_utile_t_ms, 2), round((float) $b->besoin_t_ms, 2), round((float) $b->securite_t_ms, 2), round((float) $b->bilan_t_ms, 2)])
        ->toBe([205.94, 222.16, 33.32, -49.54])
        ->and($b->autonomie_jours)->toBe(139)
        ->and($b->date_rupture->toDateString())->toBe('2026-12-23')
        ->and($b->detail['epuisement_lot']['L'.$foin25->id])->toBe('2027-01-03');
});

it('ouvre à l\'éleveur de J1 le bilan, l\'approvisionnement, les paramètres et les PDF (E14, E15, E16)', function () {
    $this->actingAs($this->eleveur);

    $this->get(route('accueil'))->assertOk()->assertSee('139 jours')->assertSee('23/12/2026');
    $this->get(route('autonomie', $this->elevage->id))->assertOk()->assertSee('Projection des stocks')->assertSee('bilan en PDF');
    $this->get(route('approvisionnement', $this->elevage->id))->assertOk()->assertSee('Proposition initiale');
    $this->get(route('parametres', $this->elevage->id))->assertOk()->assertSee('Prix indicatifs');
    $this->get(route('autonomie.pdf', $this->elevage->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
});

it('retient la proposition initiale : achats en lots et mouvements, plus de rupture', function () {
    $service = app(ApprovisionnementService::class);
    $scenario = $service->creerScenario($this->inventaire, 'Proposition', true);
    $service->retenir($scenario, $this->eleveur);
    $achat = $service->lignes($scenario)->firstWhere('levier_code', Levier::ACHAT);

    expect(Scenario::query()->withoutGlobalScopes()->where('statut', Scenario::STATUT_RETENU)->count())->toBe(1)
        ->and(Mouvement::query()->withoutGlobalScopes()->where('scenario_ligne_id', $achat->id)->sole()->date_mouvement->toDateString())->toBe('2026-11-01')
        // la réaffectation n'est pas un achat : seule la ligne d'achat devient un mouvement ; la rupture de l'enrubannage
        // est repoussée au-delà de la période dans le scénario, l'inventaire reçoit l'achat
        ->and($service->evaluer($scenario->refresh())['bilan']->date_rupture)->toBeNull()
        ->and((float) app(BilanService::class)->dernier($this->elevage->id)->bilan_t_ms)->toBeGreaterThan(-49.54);
});
