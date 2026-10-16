<?php

use App\Domain\Interpretation\Position;
use App\Domain\Interpretation\ReferenceQuartiles;
use App\Jobs\RafraichirContextePrelevements;
use App\Models\App\Consentement;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Services\Dossier\InterpretationVisiteService;
use App\Services\Dossier\RedactionVisite;
use App\Services\Dossier\VisiteService;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use App\Services\Pdf\RenduPdf;
use App\View\Components\Positionnement;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// Positionnement à l'écran et dans la fiche (RC-3, EF-M3-01 à EF-M3-03, § 8.3 x-positionnement) : E04 liste et détail,
// E03, fiche figée ; points anonymes (aucun élevage de la population nommé) ; rafraîchissement du contexte SIG.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Storage::fake('local');
    app()->instance(RenduPdf::class, new class implements RenduPdf
    {
        public function pdf(string $html, string $piedDePage): string
        {
            return '%PDF-1.7';
        }
    });

    $this->jeu = JeuCloisonnement::creer();
    $this->tech = $this->jeu->technicien24;
    $this->actingAs($this->tech);
    $this->population = collect([60, 62, 66, 70])->map(function (float $mat) {
        $elevage = Elevage::factory()->gds(24)->create();
        Consentement::query()->withoutGlobalScopes()->create(['elevage_id' => $elevage->id, 'type' => Consentement::TYPE_REFERENCES, 'accorde_le' => now()]);

        return AnalyseEssai::creer($elevage, ['MAT' => $mat, 'UFL_2018' => 0.7, 'CU' => 5.0], Analyse::STATUT_VALIDE, campagne: '2025-2026');
    });
    $this->lot = AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 55, 'UFL_2018' => 0.64, 'CU' => 2.8], campagne: '2025-2026');
});

/** Interprète, valide (statut « valide ») et publie seul le bulletin de l'élevage J4-24A. */
function publierLot(): void
{
    $service = app(InterpretationService::class);
    $i = $service->creerBrouillon(test()->lot, test()->tech);
    $service->enregistrer($i, new RedactionInterpretation(synthese: 'Foin pauvre.', niveauRetenu: 2));
    $service->valider($i->refresh(), test()->tech);
    $service->publier($i->refresh(), test()->tech);
}

it('rend le graphique en SVG avec sa légende textuelle (x-positionnement, ENF-ACC-01)', function () {
    $html = Blade::render('<x-positionnement :parametre="$p" unite="g/kg MS" :decimales="0" :reference="$r" :population="$pop" :valeurs="$v" :lots="$l" />', [
        'p' => 'Matières azotées totales',
        'r' => new ReferenceQuartiles(ReferenceQuartiles::DEPARTEMENTALE, 5, 60, 62, 66, 55, 70),
        'pop' => '5 lots de foin de prairie permanente du GDS 24, campagne 2025-2026',
        'v' => [55.0, 60.0, 62.0, 66.0, 70.0],
        'l' => [['libelle' => 'Ce lot', 'valeur' => 55.0, 'position' => Position::Faible]],
    ]);

    expect($html)->toContain('<svg')->toContain('role="img"')->toContain('Q1 60')->toContain('Q3 66')
        ->toContain('Ce lot : 55, faible')->toContain('médiane 62')->toContain('de 55 à 70')
        ->and(substr_count($html, '<circle'))->toBe(6);
    preg_match_all('/cx="([\d.]+)"/', $html, $x);
    expect(min($x[1]))->toBeGreaterThanOrEqual(24.0)->and(max($x[1]))->toBeLessThanOrEqual(576.0);
});

it('déclare la référence insuffisante sous cinq lots, puis positionne le lot et le montre à l\'éleveur (E04, T-07)', function () {
    publierLot();
    $this->actingAs($this->jeu->eleveur24a);
    $detail = route('dossier.fourrage', [$this->jeu->elevage24a->id, $this->lot->prelevement_id]);

    $this->get($detail)->assertOk()->assertSee('référence insuffisante (n = 4', false)->assertDontSee('<svg', false);

    $elevage = Elevage::factory()->gds(24)->create();
    Consentement::query()->withoutGlobalScopes()->create(['elevage_id' => $elevage->id, 'type' => Consentement::TYPE_REFERENCES, 'accorde_le' => now()]);
    AnalyseEssai::creer($elevage, ['MAT' => 64, 'UFL_2018' => 0.72, 'CU' => 5.5], Analyse::STATUT_VALIDE, campagne: '2025-2026');

    $page = $this->get($detail)->assertOk()->assertSee('<svg', false)
        ->assertSee('Ce lot : 55, faible')->assertSee('5 lots de foin de prairie permanente du GDS 24, campagne 2025-2026');
    // points anonymes : aucun élevage de la population n'apparaît
    foreach (Elevage::query()->withoutGlobalScopes()->where('code_pseudo', 'like', 'TST-%')->pluck('code_pseudo') as $code) {
        expect(str_contains((string) $page->getContent(), $code))->toBeFalse();
    }

    $this->get(route('dossier.fourrages', $this->jeu->elevage24a->id))->assertOk()
        ->assertSee('Position dans la campagne')->assertSeeInOrder(['UFL', 'Faible', 'MAT', 'Faible']);
});

it('fige le positionnement des lots de la visite dans la fiche publiée (document 04, figures 3 et 4)', function () {
    $elevage = Elevage::factory()->gds(24)->create();
    Consentement::query()->withoutGlobalScopes()->create(['elevage_id' => $elevage->id, 'type' => Consentement::TYPE_REFERENCES, 'accorde_le' => now()]);
    AnalyseEssai::creer($elevage, ['MAT' => 64, 'UFL_2018' => 0.72, 'CU' => 5.5], Analyse::STATUT_VALIDE, campagne: '2025-2026');

    $visite = app(VisiteService::class)->creer($this->jeu->elevage24a, $this->tech, '2026-07-29', 'initiale');
    app(VisiteService::class)->rattacherPrelevements($visite, [$this->lot->prelevement_id]);
    $lots = app(InterpretationService::class);
    $lot = $lots->creerBrouillon($this->lot, $this->tech);
    $lots->enregistrer($lot, new RedactionInterpretation(synthese: 'Foin pauvre.', niveauRetenu: 2));
    $lots->valider($lot->refresh(), $this->tech);
    $service = app(InterpretationVisiteService::class);
    $iv = $service->creerBrouillon($visite, $this->tech);
    $service->enregistrer($iv, new RedactionVisite('Vos fourrages', 'Synthèse.', null, 2), $this->tech);
    $service->valider($iv->refresh(), $this->tech);
    $iv = $service->publier($iv->refresh(), $this->tech);
    $html = (string) Storage::disk('local')->get((string) $iv->fiche_html);

    expect($html)->toContain('Où se situent vos fourrages dans la campagne ?')->toContain('Position campagne')
        ->toContain('<svg')->toContain('data:image/svg+xml;base64')
        ->toContain('Matières azotées totales')->toContain('Cuivre');
    Queue::assertPushed(RafraichirContextePrelevements::class);

    $this->actingAs($this->jeu->eleveur24a)->get(route('dossier.visite', [$this->jeu->elevage24a->id, $visite->id]))->assertOk()
        ->assertSee('Où se situent vos fourrages dans la campagne ?');
});

it('rafraîchit le contexte pédo-géologique des prélèvements', function () {
    (new RafraichirContextePrelevements)->handle();

    expect(DB::table('valo.mv_prelevement_contexte')->where('prelevement_id', $this->lot->prelevement_id)->exists())->toBeTrue();
});

it('évite les chevauchements d\'étiquettes : lots voisins, lot en bout d\'échelle, bande étroite', function () {
    $composant = new Positionnement('Cuivre total', 'mg/kg MS', 1,
        new ReferenceQuartiles(ReferenceQuartiles::DEPARTEMENTALE, 5, 4.6, 4.6, 4.7, 3.9, 5.1), '5 lots', [3.9, 4.5, 4.6, 4.7, 5.1],
        [['libelle' => 'A', 'valeur' => 1.8, 'position' => Position::Faible], ['libelle' => 'B', 'valeur' => 1.9, 'position' => Position::Faible]]);

    expect(array_column($composant->etiquettesLots(), 'y'))->toBe([76, 90])
        ->and($composant->borneLisible(1.8))->toBeFalse()
        ->and($composant->borneLisible(5.1))->toBeTrue()
        ->and(array_column($composant->etiquettesQuartiles(), 'ancre'))->toBe(['end', 'start']);
});
