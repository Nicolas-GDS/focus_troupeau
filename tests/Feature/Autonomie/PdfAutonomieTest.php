<?php

use App\Domain\Autonomie\Levier;
use App\Jobs\EnvoyerNotification;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Stock\Inventaire;
use App\Services\Autonomie\ApprovisionnementService;
use App\Services\Autonomie\PdfAutonomie;
use App\Services\Pdf\RenduBrowsershot;
use App\Services\Pdf\RenduPdf;
use Database\Seeders\JeuJ1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\JeuCloisonnement;
use Tests\Support\RenduChromium;

// PDF du bilan d'autonomie et d'un scénario (EF-M6-06, EF-GEN-08, T-26) : charte bi-entité, tableaux, graphique,
// hypothèses et sources, mentions de limites, version du référentiel ; cloisonnement (T-40).
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([EnvoyerNotification::class]);
    Mail::fake();
    app()->instance(RenduPdf::class, new class implements RenduPdf
    {
        public function pdf(string $html, string $piedDePage): string
        {
            return "%PDF-1.7\n% document d'essai\n".md5($html);
        }
    });
    (new JeuJ1Seeder)->run();
    $this->elevage = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ1Seeder::CODE)->sole();
    $this->inventaire = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->where('statut', Inventaire::STATUT_COURANT)->sole();
    $this->eleveur = User::query()->where('email', JeuJ1Seeder::ELEVEUR)->sole();
    $service = app(ApprovisionnementService::class);
    $this->scenario = $service->creerScenario($this->inventaire, 'Achat d\'enrubannage', false);
    $service->enregistrerLigne($this->scenario, ['levier_code' => Levier::ACHAT, 'quantite' => 30, 'famille_code' => 'ENRUB', 'date_effet' => '2026-12-15']);
});

it('T-26 : rend le bilan à la charte, avec graphique, hypothèses, sources, limites et version du référentiel', function () {
    $html = app(PdfAutonomie::class)->bilanHtml($this->inventaire);

    expect($html)->toContain('data-gds="24"')->toContain('data:image/jpeg;base64')->toContain('Réseau GDS France')
        ->toContain('Bilan d\'autonomie fourragère')->toContain('23/12/2026')->toContain('139 jours')
        ->toContain('Stock projeté et besoin cumulé sur la période (t MS)')->toContain('novembre 2026')
        ->toContain('Marge de sécurité')->toContain('RC-12')->toContain('Au-dessus du repère')
        ->toContain('Projection indicative')->toContain('référentiel version 5')
        ->not->toContain('J4-');
});

it('T-26 : rend le scénario avec ses leviers, son effet sur le bilan et les sources des valeurs par défaut', function () {
    $html = app(PdfAutonomie::class)->scenarioHtml($this->scenario);

    expect($html)->toContain('Achat d&#039;enrubannage')->toContain('Effet sur le bilan')
        ->toContain('23/12/2026')->toContain('30/01/2027')->toContain('5 700 €')
        ->toContain('Valeurs par défaut des leviers et sources')->toContain('Prix indicatif de l&#039;enrubannage — à valider')
        ->toContain('Prix indicatif à valider')->toContain('Projection indicative');
});

it('télécharge les PDF pour l\'éleveur, introuvables hors périmètre (T-40)', function () {
    $this->actingAs($this->eleveur);
    $this->get(route('autonomie.pdf', $this->elevage->id))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->get(route('scenarios.pdf', [$this->elevage->id, $this->scenario->id]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

    $jeu = JeuCloisonnement::creer();
    $this->actingAs($jeu->eleveur24a);
    $this->get(route('autonomie.pdf', $this->elevage->id))->assertNotFound();
    $this->get(route('scenarios.pdf', [$this->elevage->id, $this->scenario->id]))->assertNotFound();
    $this->get(route('scenarios.pdf', [$jeu->elevage24a->id, $this->scenario->id]))->assertNotFound();
    $this->actingAs($jeu->veterinaire);
    $this->get(route('autonomie.pdf', $jeu->elevage24a->id))->assertForbidden();
});

it('T-26 : rend le bilan en PDF avec Chromium en moins de 10 s', function () {
    if (! is_file((string) config('fiches.chrome'))) {
        $this->markTestSkipped('Chromium absent de cet environnement (image Docker de l\'application requise).');
    }

    $service = app(PdfAutonomie::class);
    [$pdf, $duree] = RenduChromium::mesurer(fn () => (new RenduBrowsershot)->pdf($service->bilanHtml($this->inventaire), $service->piedDePage('bilan d\'autonomie')));

    expect($pdf)->toStartWith('%PDF')->and(strlen($pdf))->toBeGreaterThan(10_000)->and(RenduChromium::dansLeSeuil($duree))->toBeTrue("Rendu en {$duree} s (seuil de T-26 : 10 s)");
});
