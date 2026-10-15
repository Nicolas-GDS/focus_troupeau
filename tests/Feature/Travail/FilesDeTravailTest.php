<?php

use App\Models\App\Convention;
use App\Models\App\ImportBulletin;
use App\Models\App\User;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Interpretation;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use App\Services\Travail\FilesDeTravail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// E18 — files de travail (EF-M2-08, EF-M0-06) : périmètre, droits, liens vers les objets.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->service = app(InterpretationService::class);
});

/**
 * @return array<string, array{cle: string, titre: string, total: int, lignes: list<array<string, mixed>>}>
 */
function filesPour(User $user): array
{
    test()->actingAs($user);

    return collect(app(FilesDeTravail::class)->pour($user))->keyBy('cle')->all();
}

function interpretationAuStatut(Elevage $elevage, int $niveau, bool $valider = true): Interpretation
{
    $service = test()->service;
    $interpretation = $service->creerBrouillon(AnalyseEssai::creer($elevage, ['MAT' => 61, 'CU' => 4.2]), test()->jeu->technicien24);
    $interpretation = $service->enregistrer($interpretation, new RedactionInterpretation(synthese: 'Synthèse d\'essai.', niveauRetenu: $niveau));

    return $valider ? $service->valider($interpretation, test()->jeu->technicien24) : $interpretation;
}

it('présente au technicien les files de son périmètre, chacune avec son nombre et ses liens', function () {
    AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 61]);                                   // à interpréter
    AnalyseEssai::creer($this->jeu->elevage24b, ['NA' => 13.6], Analyse::STATUT_ANOMALIE);       // anomalie
    AnalyseEssai::creer($this->jeu->elevage47, ['MAT' => 70]);                                   // hors périmètre
    interpretationAuStatut($this->jeu->elevage24a, 2, valider: false);                          // brouillon
    interpretationAuStatut($this->jeu->elevage24a, 1);                                          // à publier
    ImportBulletin::query()->create(['gds_id' => 24, 'nom_fichier' => 'b24.pdf', 'hash_sha256' => str_repeat('a', 64), 'taille_octets' => 1, 'statut' => 'a_rapprocher']);
    ImportBulletin::query()->create(['gds_id' => 47, 'nom_fichier' => 'b47.pdf', 'hash_sha256' => str_repeat('b', 64), 'taille_octets' => 1, 'statut' => 'a_rapprocher']);

    $files = filesPour($this->jeu->technicien24);

    expect(array_keys($files))->toBe(['a_rapprocher', 'anomalies', 'a_interpreter', 'brouillons', 'a_publier', 'conventions'])
        ->and($files['a_rapprocher']['total'])->toBe(1)
        ->and($files['anomalies']['total'])->toBe(1)
        ->and($files['a_interpreter']['total'])->toBe(1)
        ->and($files['brouillons']['total'])->toBe(1)
        ->and($files['a_publier']['total'])->toBe(1)
        ->and($files['a_interpreter']['lignes'][0]['route'])->toBe('analyses.interpretation')
        ->and($files['a_interpreter']['lignes'][0]['libelle'])->toContain('EARL Essai Dordogne A');
});

it('présente au vétérinaire conseil les seules interprétations à viser de son GDS', function () {
    interpretationAuStatut($this->jeu->elevage24a, 3);
    interpretationAuStatut($this->jeu->elevage24b, 2);

    $files = filesPour($this->jeu->veterinaireConseil24);

    expect(array_keys($files))->toBe(['a_viser'])
        ->and($files['a_viser']['total'])->toBe(1)
        ->and($files['a_viser']['lignes'][0]['etat'])->toBe('critique');
});

it('signale un brouillon revenu d\'un refus de visa, avec son motif', function () {
    $interpretation = interpretationAuStatut($this->jeu->elevage24a, 3);
    $this->service->refuserVisa($interpretation, 'Niveau 3 à justifier', $this->jeu->veterinaireConseil24);

    $brouillons = filesPour($this->jeu->technicien24)['brouillons'];

    expect($brouillons['lignes'][0]['detail'])->toBe('Visa refusé : Niveau 3 à justifier')
        ->and($brouillons['lignes'][0]['etat'])->toBe('alerte');
});

it('liste les conventions arrivant à échéance dans le délai paramétré (EF-M0-06)', function () {
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage24a->id)->update(['date_fin' => today()->addDays(20)]);
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage24b->id)->update(['date_fin' => today()->addDays(45)]);
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage47->id)->update(['date_fin' => today()->addDays(10)]);

    $conventions = filesPour($this->jeu->technicien24)['conventions'];

    expect($conventions['titre'])->toContain('30 jours')
        ->and($conventions['total'])->toBe(1)
        ->and($conventions['lignes'][0]['libelle'])->toBe('EARL Essai Dordogne A');
});

it('affiche les files sur la page « Mon travail », sans fuite vers l\'autre GDS', function () {
    AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 61]);
    $analyse47 = AnalyseEssai::creer($this->jeu->elevage47, ['MAT' => 70]);

    $this->actingAs($this->jeu->technicien24)->get(route('travail'))
        ->assertOk()
        ->assertSee('Bulletins contrôlés à interpréter')
        ->assertSee('EARL Essai Dordogne A')
        ->assertDontSee($analyse47->num_echantillon_labo)
        ->assertDontSee('EARL Essai Agenais');
});

it('affiche au plus quatre tuiles en tête d\'écran, pour les seules files non vides (§ 8.3)', function () {
    $this->actingAs($this->jeu->technicien24)->get(route('travail'))->assertSee('Rien en attente : toutes les files de travail sont vides.');

    AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 61]);
    $page = $this->actingAs($this->jeu->technicien24)->get(route('travail'))->getContent();

    expect(substr_count((string) $page, 'href="#file-'))->toBe(1);
});
