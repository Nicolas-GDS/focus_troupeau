<?php

use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// Historique pluriannuel (E10, RC-4, EF-M1-08, EF-M3-05, T-09) : série par campagne (médiane des lots), variation en
// valeur et en pourcentage sans classe, frise des décisions ; l'éleveur ne voit que ses lots publiés (§ 6.3).
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    DB::table('fourrage.campagne')->insert(['code' => '2024-2025', 'libelle' => 'Campagne 2024-2025', 'date_debut' => '2024-09-01', 'date_fin' => '2025-08-31']);
    $this->jeu = JeuCloisonnement::creer();
    $this->tech = $this->jeu->technicien24;
    $this->elevage = $this->jeu->elevage24a;
    $this->actingAs($this->tech);
});

/** Bulletin de foin de l'élevage J4-24A, interprété, validé et, sauf demande contraire, publié seul. */
function foinHistorique(Elevage $elevage, float $mat, string $campagne, bool $publier = true, ?string $recommandations = null): Analyse
{
    $analyse = AnalyseEssai::creer($elevage, ['MAT' => $mat, 'CU' => 4.0], campagne: $campagne,
        datePrelevement: $campagne === '2025-2026' ? '2026-06-15' : '2025-06-15');
    $service = app(InterpretationService::class);
    $i = $service->creerBrouillon($analyse, test()->tech);
    $service->enregistrer($i, new RedactionInterpretation(synthese: 'Lecture.', recommandations: $recommandations, niveauRetenu: 1));
    $service->valider($i->refresh(), test()->tech);
    if ($publier) {
        $service->publier($i->refresh(), test()->tech);
    }

    return $analyse->refresh();
}

it('T-09 : affiche la variation entre deux campagnes de foin en valeur et en pourcentage, sans classe', function () {
    foinHistorique($this->elevage, 55, '2024-2025');
    foinHistorique($this->elevage, 60, '2025-2026');
    foinHistorique($this->elevage, 74, '2025-2026');

    $page = $this->actingAs($this->jeu->eleveur24a)->get(route('dossier.historique', $this->elevage->id))->assertOk()
        ->assertSee('Foin de prairie permanente')
        ->assertSee('2024-2025 : 55 ; 2025-2026 : 67 (médiane de 2 lots).')
        ->assertSee('Entre 2024-2025 et 2025-2026 : + 12 g/kg MS (+ 21,8 %).');

    // aucune classe d'état sur la courbe : ni pastille, ni position dans la figure de l'évolution
    preg_match('/titre-evolution.*?<\/section>/s', (string) $page->getContent(), $section);
    expect($section[0])->not->toContain('ft-etat')->not->toContain('img/puces');
});

it('ne montre à l\'éleveur que ses lots publiés ; le technicien voit aussi les lots validés non publiés', function () {
    foinHistorique($this->elevage, 55, '2024-2025');
    foinHistorique($this->elevage, 60, '2025-2026');
    foinHistorique($this->elevage, 98, '2025-2026', publier: false);

    $this->actingAs($this->jeu->eleveur24a)->get(route('dossier.historique', $this->elevage->id))
        ->assertSee('2025-2026 : 60.')->assertDontSee('2025-2026 : 79')->assertSee('+ 5 g/kg MS');
    $this->actingAs($this->tech)->get(route('dossier.historique', $this->elevage->id))
        ->assertSee('2025-2026 : 79 (médiane de 2 lots).');
});

it('ne rappelle dans le positionnement que les campagnes précédentes publiées pour l\'éleveur (E04)', function () {
    foinHistorique($this->elevage, 41, '2024-2025', publier: false);
    $lot = foinHistorique($this->elevage, 60, '2025-2026');
    foreach ([62, 64, 66, 70, 72] as $mat) {
        $autre = Elevage::factory()->gds(24)->create();
        DB::table('app.consentement')->insert(['elevage_id' => $autre->id, 'type' => 'references', 'accorde_le' => now()]);
        AnalyseEssai::creer($autre, ['MAT' => $mat], Analyse::STATUT_VALIDE, campagne: '2025-2026');
    }
    $detail = route('dossier.fourrage', [$this->elevage->id, $lot->prelevement_id]);

    $this->actingAs($this->jeu->eleveur24a)->get($detail)->assertOk()->assertSee('<svg', false)->assertDontSee('Campagnes précédentes');
    $this->actingAs($this->tech)->get($detail)->assertOk()->assertSee('Campagnes précédentes (cercles en pointillé) : 2024-2025 41.');
});

it('porte les décisions sur la frise de leur campagne : recommandations des lots publiés', function () {
    foinHistorique($this->elevage, 55, '2024-2025', recommandations: 'Associer ce foin à une légumineuse.');
    foinHistorique($this->elevage, 60, '2025-2026', publier: false, recommandations: 'Recommandation non publiée.');

    $this->actingAs($this->jeu->eleveur24a)->get(route('dossier.historique', $this->elevage->id))->assertOk()
        ->assertSeeInOrder(['Campagne 2024-2025', 'Décisions', 'Recommandation', 'Associer ce foin à une légumineuse.'])
        ->assertDontSee('Recommandation non publiée.');
});
