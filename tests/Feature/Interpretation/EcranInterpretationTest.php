<?php

use App\Livewire\Import\ImportBulletins;
use App\Livewire\Interpretation\InterpretationBulletin;
use App\Models\App\ImportBulletin;
use App\Models\Fourrage\Alerte;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Interpretation;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use Database\Seeders\JeuJ4Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// E20 — écran d'interprétation d'un bulletin (EF-M2-02 à EF-M2-04, EF-GEN-02, EF-GEN-03) ; ENF-SEC-04 (autorisation
// de chaque action côté serveur) ; T-05 ; T-40 (cloisonnement de la route GET /analyses/{analyse}/interpretation sur J4).
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    $this->jeu = JeuCloisonnement::creer();
    // Lot T-03 de l'élevage J4-24A
    $this->analyse = AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 61, 'CU' => 4.2, 'FE' => 166, 'K' => 18]);
});

function interpretationDe(Analyse $analyse): ?Interpretation
{
    return app(InterpretationService::class)->courante($analyse);
}

/**
 * Interprétation rédigée et validée au niveau donné, par le service.
 */
function interpretationEcranValidee(Analyse $analyse, int $niveau): Interpretation
{
    $service = app(InterpretationService::class);
    $interpretation = $service->creerBrouillon($analyse, test()->jeu->technicien24);
    $service->enregistrer($interpretation, new RedactionInterpretation(synthese: 'Foin pauvre en azote et en cuivre.', niveauRetenu: $niveau));

    return $service->valider($interpretation->fresh() ?? $interpretation, test()->jeu->technicien24);
}

it('T-40 : ouvre l\'écran dans le périmètre seulement ; les comptes externes attendent la publication', function (string $profil, int $statut) {
    $this->actingAs($this->jeu->{$profil})->get(route('analyses.interpretation', $this->analyse->id))->assertStatus($statut);
})->with([
    ['technicien24', 200], ['chargeDeMission', 200], ['veterinaireConseil24', 200], ['admin24', 200], ['adminGlobal', 200],
    ['technicien47', 404], ['eleveur47', 404], ['eleveur24a', 403], ['veterinaire', 403],
]);

it('T-40 : ouvre l\'interprétation publiée à l\'éleveur et au vétérinaire consenti, jamais hors périmètre', function () {
    $service = app(InterpretationService::class);
    $service->publier(interpretationEcranValidee($this->analyse, 2), $this->jeu->technicien24);
    $url = route('analyses.interpretation', $this->analyse->id);

    $this->actingAs($this->jeu->eleveur24a)->get($url)->assertOk()
        ->assertSee('Foin pauvre en azote et en cuivre.')
        ->assertSee('Publiée')
        ->assertDontSee('Valider l\'interprétation', false)
        ->assertDontSee('Ajouter une alerte');
    $this->actingAs($this->jeu->veterinaire)->get($url)->assertOk();
    $this->actingAs($this->jeu->eleveur47)->get($url)->assertNotFound();
    $this->actingAs($this->jeu->technicien47)->get($url)->assertNotFound();
});

it('T-40 : n\'affiche aucun élevage hors périmètre sur l\'écran', function () {
    $this->actingAs($this->jeu->technicien24)->get(route('analyses.interpretation', $this->analyse->id))
        ->assertOk()
        ->assertSee('<h1 class="text-2xl font-semibold text-quasi-noir">Interprétation d&#039;un bulletin</h1>', false)
        ->assertSee('J4-24A')
        ->assertDontSee('J4-47')
        ->assertDontSee(JeuJ4Seeder::ELEVAGES['J4-47']['raison_sociale']);
});

it('rend chaque résultat avec sa classe, son repère et sa source (EF-GEN-02, EF-GEN-03)', function () {
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->assertSee('Matières azotées totales')
        ->assertSee('Faible')
        ->assertSee('Carence probable')
        ->assertSee('moins de 80 g/kg MS')
        ->assertSee('Source et statut')
        ->assertSee('Préparer l\'interprétation', false);
});

it('mène le technicien du brouillon à la publication d\'un niveau 2', function () {
    $this->actingAs($this->jeu->technicien24);

    $composant = Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->set('categorieCible', 'VA')
        ->call('creer')
        ->assertHasNoErrors()
        ->assertSee('Niveau 2 — alerte')
        ->assertSee('Cu &lt; 7 mg/kg MS', false);

    $interpretation = interpretationDe($this->analyse);
    $cuivre = Alerte::query()->where('interpretation_id', $interpretation?->id)->where('parametre_code', 'CU')->sole();

    $composant->call('ecarter', $cuivre->id)
        ->assertHasErrors(["motifsEcart.{$cuivre->id}"])
        ->set("motifsEcart.{$cuivre->id}", 'Complémentation minérale en cours')
        ->call('ecarter', $cuivre->id)
        ->assertHasNoErrors()
        ->assertSee('Écartée')
        ->call('retenir', $cuivre->id)
        ->set('lectureConservation', 'Foin propre.')
        ->set('lectureMineraux', 'Cuivre en carence probable.')
        ->set('lectureNutritionnelle', 'Pauvre en azote.')
        ->set('synthese', 'Foin pauvre en azote et en cuivre.')
        ->call('valider')
        ->assertHasErrors(['circuit'])
        ->set('niveauRetenu', '2')
        ->set('utilisationDeconseillee', ['VL'])
        ->call('valider')
        ->assertHasNoErrors()
        ->assertSee('Validée')
        ->assertSee('Publier')
        ->call('publier')
        ->assertHasNoErrors()
        ->assertSee('Publiée');

    $interpretation = $interpretation?->fresh();
    expect($interpretation?->statut)->toBe('publie')
        ->and($interpretation?->lecture_mineraux)->toBe('Cuivre en carence probable.')
        ->and($interpretation?->niveau_global)->toBe(2)
        ->and($interpretation?->niveau_propose)->toBe(2);
});

it('T-05 : le technicien ne publie pas un niveau 3 ; le vétérinaire conseil vise, puis le technicien publie', function () {
    $interpretation = interpretationEcranValidee($this->analyse, 3);

    $this->actingAs($this->jeu->technicien24);
    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->assertDontSee('Publier')
        ->assertSee('en attente du visa du vétérinaire conseil')
        ->call('publier')
        ->assertForbidden();
    expect($interpretation->fresh()?->statut)->toBe('valide');

    $this->actingAs($this->jeu->veterinaireConseil24);
    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->assertSee('Viser (niveau 3)')
        ->call('viser')
        ->assertHasNoErrors();
    expect($interpretation->fresh()?->statut)->toBe('visee');

    $this->actingAs($this->jeu->technicien24);
    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->call('publier')
        ->assertHasNoErrors();
    expect($interpretation->fresh()?->statut)->toBe('publie');
});

it('laisse le vétérinaire conseil lire et viser, jamais rédiger', function () {
    $this->actingAs($this->jeu->veterinaireConseil24);

    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->assertDontSee('Préparer l\'interprétation', false)
        ->call('creer')
        ->assertForbidden();

    $interpretation = app(InterpretationService::class)->creerBrouillon($this->analyse, $this->jeu->technicien24);
    $alerte = Alerte::query()->where('interpretation_id', $interpretation->id)->firstOrFail();

    $composant = Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->assertDontSee('Valider l\'interprétation', false)
        ->set('synthese', 'Tentative du vétérinaire conseil');
    $composant->call('enregistrer')->assertForbidden();
    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])->call('valider')->assertForbidden();
    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->set("motifsEcart.{$alerte->id}", 'Tentative du vétérinaire conseil')
        ->call('ecarter', $alerte->id)
        ->assertForbidden();
    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])->call('viser')->assertForbidden();

    expect($interpretation->fresh()?->synthese)->toBeNull()
        ->and($alerte->fresh()?->retenue)->toBeTrue();
});

it('refuse toute modification d\'une interprétation validée, même par le technicien', function () {
    interpretationEcranValidee($this->analyse, 1);
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->assertDontSee('Enregistrer le brouillon')
        ->call('enregistrer')
        ->assertForbidden();
    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])->call('reclasser')->assertForbidden();
});

it('n\'ouvre pas une analyse hors périmètre au composant (404)', function () {
    $this->actingAs($this->jeu->technicien47);

    $this->get(route('analyses.interpretation', $this->analyse->id))->assertNotFound();
});

it('reclasse le bulletin depuis l\'écran quand la catégorie cible change', function () {
    $analyse = AnalyseEssai::creer($this->jeu->elevage24a, ['CU' => 23.0]);
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(InterpretationBulletin::class, ['analyse' => $analyse])
        ->call('creer')
        ->assertSee('Aucune alerte')
        ->set('categorieCible', 'BREBIS_ALL')
        ->call('reclasser')
        ->assertHasNoErrors()
        ->assertSee('Toxicite ovins')
        ->assertSee('Niveau 3 — critique');
});

it('propose le lien « Interpréter » dans E19 pour un bulletin contrôlé', function () {
    ImportBulletin::query()->create([
        'gds_id' => 24, 'depose_par' => $this->jeu->technicien24->id, 'nom_fichier' => 'essai.pdf',
        'hash_sha256' => str_repeat('a', 64), 'taille_octets' => 10, 'statut' => 'importe', 'analyse_id' => $this->analyse->id,
    ]);
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(ImportBulletins::class)
        ->assertSee('Interpréter')
        ->assertSee(route('analyses.interpretation', $this->analyse->id));
});

it('laisse le vétérinaire conseil refuser le visa depuis l\'écran ; le technicien retrouve le motif sur son brouillon', function () {
    $interpretation = interpretationEcranValidee($this->analyse, 3);

    $this->actingAs($this->jeu->technicien24);
    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->assertDontSee('Refuser le visa')
        ->call('refuserVisa')
        ->assertForbidden();

    $this->actingAs($this->jeu->veterinaireConseil24);
    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->assertSee('Refuser le visa')
        ->call('refuserVisa')
        ->assertHasErrors(['motifRefusVisa'])
        ->set('motifRefusVisa', 'Niveau 3 non justifié par les valeurs : à revoir.')
        ->call('refuserVisa')
        ->assertHasNoErrors();

    expect($interpretation->fresh()?->statut)->toBe('brouillon');

    $this->actingAs($this->jeu->technicien24);
    Livewire::test(InterpretationBulletin::class, ['analyse' => $this->analyse])
        ->assertSee('Visa refusé')
        ->assertSee('Niveau 3 non justifié par les valeurs');
});
