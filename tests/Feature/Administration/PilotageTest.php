<?php

use App\Livewire\Pilotage\Indicateurs;
use App\Models\Fourrage\Analyse;
use App\Services\Pilotage\PilotageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// E25 — indicateurs de pilotage (EF-M10-04) : activité, délai médian, anomalies, alertes retenues, orientations
// vétérinaires, couverture des références ; exports CSV ; un agent ne voit que ses GDS.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $e = $this->jeu->elevage24a;
    $this->a1 = AnalyseEssai::creer($e, ['MS' => 85], Analyse::STATUT_VALIDE, campagne: '2025-2026', datePrelevement: '2026-06-01');
    $this->a2 = AnalyseEssai::creer($e, ['MS' => 86], Analyse::STATUT_VALIDE, campagne: '2025-2026', datePrelevement: '2026-06-03');
    $this->a3 = AnalyseEssai::creer($this->jeu->elevage24b, ['MS' => 87], Analyse::STATUT_ANOMALIE, campagne: '2025-2026');
    AnalyseEssai::creer($this->jeu->elevage47, ['MS' => 88], Analyse::STATUT_VALIDE, campagne: '2025-2026');

    $it = DB::table('fourrage.interpretation')->insertGetId(['analyse_id' => $this->a1->id, 'version' => 1, 'statut' => 'publie', 'niveau_global' => 3, 'date_interpretation' => '2026-06-20']);
    DB::table('fourrage.alerte')->insert([
        ['interpretation_id' => $it, 'parametre_code' => 'CU', 'classe' => 'faible', 'gravite' => 2, 'message' => 'Cuivre bas', 'origine' => 'automatique', 'retenue' => true],
        ['interpretation_id' => $it, 'parametre_code' => 'ZN', 'classe' => 'faible', 'gravite' => 1, 'message' => 'Zinc bas', 'origine' => 'automatique', 'retenue' => false],
        ['interpretation_id' => $it, 'parametre_code' => 'MG', 'classe' => 'faible', 'gravite' => 1, 'message' => 'Note', 'origine' => 'technicien', 'retenue' => true],
    ]);
    $visite = DB::table('dossier.visite')->insertGetId(['elevage_id' => $e->id, 'campagne_code' => '2025-2026', 'date_visite' => '2026-06-01', 'motif' => 'suivi', 'statut' => 'publiee']);
    DB::table('fourrage.prelevement')->whereIn('id', [$this->a1->prelevement_id, $this->a2->prelevement_id])->update(['visite_id' => $visite]);
    DB::table('dossier.interpretation_visite')->insert(['visite_id' => $visite, 'version' => 1, 'version_grille' => '5', 'niveau_global' => 2, 'statut' => 'publie', 'publie_le' => '2026-06-21 10:00:00']);
    DB::table('dossier.note_liaison')->insert(['elevage_id' => $e->id, 'interpretation_id' => $it, 'veterinaire_user_id' => $this->jeu->veterinaire->id, 'question' => 'Avis ?', 'envoyee_le' => '2026-06-22 09:00:00', 'statut' => 'envoyee']);
    DB::table('app.consentement')->insert(['elevage_id' => $e->id, 'type' => 'references', 'accorde_le' => now()]);
});

it('calcule les indicateurs d\'un GDS pour une campagne (EF-M10-04)', function () {
    $i = app(PilotageService::class)->indicateurs(24, '2025-2026', $this->jeu->technicien24);

    expect([$i['elevages'], $i['visites'], $i['bulletins'], $i['fiches_publiees']])->toBe([2, 1, 3, 1])
        ->and($i['delai_median_jours'])->toEqualWithDelta(20 + 10 / 24, 1e-6)
        ->and($i['part_anomalies'])->toEqualWithDelta(1 / 3, 1e-9)
        ->and([$i['alertes'], $i['part_alertes_retenues']])->toBe([2, 0.5])
        ->and([$i['notes_liaison'], $i['interpretations_niveau3']])->toBe([1, 1])
        ->and($i['couverture'])->toBe([['type' => DB::table('ref.type_fourrage')->where('code', 'FOIN_PP')->value('libelle'), 'n' => 2, 'couvert' => false]])
        ->and([$i['types_couverts'], $i['types_total'], $i['n_min']])->toBe([0, 1, 5]);
});

it('affiche l\'écran et exporte les indicateurs des seuls GDS de l\'agent', function () {
    $this->actingAs($this->jeu->technicien24);
    Livewire::test(Indicateurs::class)->assertSee('Activité de la campagne 2025-2026')->assertSee('33 %')->assertSee('50 %')->assertSee('20 jours')->assertSee('Effectif insuffisant');

    $csv = (string) $this->get(route('pilotage.indicateurs', ['campagne' => '2025-2026']))->assertOk()->getContent();
    expect($csv)->toContain('gds;campagne;elevages')->toContain("\n24;2025-2026;2;1;3;1;")->not->toContain("\n47;")
        ->and(DB::table('app.journal')->where('action', 'export_pilotage')->exists())->toBeTrue();

    $this->get(route('pilotage.references', ['gds' => 24, 'campagne' => '2025-2026']))->assertOk()->assertSee('type_fourrage;parametre');
    $this->get(route('pilotage.references', ['gds' => 47, 'campagne' => '2025-2026']))->assertNotFound();
});

it('ouvre le pilotage à l\'administrateur et au technicien, pas à l\'éleveur ni au vétérinaire conseil', function () {
    $this->actingAs($this->jeu->adminGlobal);
    expect(app(PilotageService::class)->gds($this->jeu->adminGlobal))->toHaveKeys([24, 47]);
    $this->get(route('pilotage'))->assertOk();
    $this->actingAs($this->jeu->eleveur24a);
    $this->get(route('pilotage'))->assertForbidden();
    $this->actingAs($this->jeu->veterinaireConseil24);
    $this->get(route('pilotage'))->assertForbidden();
});
