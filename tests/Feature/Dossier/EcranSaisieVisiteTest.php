<?php

use App\Livewire\Dossier\Saisie\Collecte;
use App\Livewire\Dossier\Saisie\Eau;
use App\Livewire\Dossier\Saisie\Etiquettes;
use App\Livewire\Dossier\Saisie\Mesures;
use App\Livewire\Dossier\Saisie\Prelevements;
use App\Livewire\Dossier\Saisie\Profils;
use App\Livewire\Dossier\Visites;
use App\Models\Dossier\AnalyseEau;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Dossier\IndicateurSanitaire;
use App\Models\Dossier\MesureTerrain;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\AnalyseAnimale;
use App\Models\Fourrage\Prelevement;
use App\Services\Dossier\VisiteService;
use Database\Seeders\JeuJ4Seeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// E03 côté technicien — saisie d'une visite (EF-M1-04 à EF-M1-07, EF-M1-09, EF-GEN-02, EF-GEN-03, EF-GEN-07) ;
// ENF-SEC-04 (chaque action autorisée côté serveur) ; T-40 (routes /visites et /visites/{visite}/saisie sur J4).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->visite = app(VisiteService::class)->creer($this->jeu->elevage24a, $this->jeu->technicien24, '2026-01-15', 'initiale', 'Troubles autour du vêlage');
});

it('T-40 : ouvre la saisie d\'une visite dans le périmètre seulement ; les comptes externes attendent la publication', function (string $profil, int $statut) {
    $this->actingAs($this->jeu->{$profil})->get(route('visites.saisie', $this->visite->id))->assertStatus($statut);
})->with([
    ['technicien24', 200], ['chargeDeMission', 200], ['veterinaireConseil24', 200], ['admin24', 200], ['adminGlobal', 200],
    ['technicien47', 404], ['eleveur47', 404], ['eleveur24a', 403], ['veterinaire', 403],
]);

it('T-40 : n\'affiche aucun élevage hors périmètre, ni sur la liste ni sur la saisie', function () {
    foreach ([route('visites'), route('visites.saisie', $this->visite->id)] as $url) {
        $this->actingAs($this->jeu->technicien24)->get($url)->assertOk()
            ->assertSee('J4-24A')
            ->assertDontSee('J4-47')
            ->assertDontSee(JeuJ4Seeder::ELEVAGES['J4-47']['raison_sociale']);
    }
});

it('présente la saisie dans l\'ordre des étapes de la démarche', function () {
    $this->actingAs($this->jeu->technicien24)->get(route('visites.saisie', $this->visite->id))->assertOk()
        ->assertSeeInOrder(['1. Collecter', 'Bilan sanitaire', '2. Analyser', 'Fourrages prélevés', 'Eau d\'abreuvement', 'Minéraux et aliments', '3. Mesurer', 'Mesures terrain', 'Profils sanguins']);
});

it('crée une visite depuis la liste de l\'élevage courant, réservée au technicien', function () {
    $this->actingAs($this->jeu->technicien24);
    session(['elevage_courant' => $this->jeu->elevage24a->id]);

    Livewire::test(Visites::class)
        ->assertSee('Visite du 15/01/2026')
        ->set('dateVisite', '2026-02-10')->set('motif', 'suivi')
        ->call('creer')
        ->assertRedirect();

    expect(Visite::query()->where('motif', 'suivi')->value('campagne_code'))->toBe('2025-2026');

    $this->actingAs($this->jeu->veterinaireConseil24);
    Livewire::test(Visites::class)->assertDontSee('Nouvelle visite')->call('creer')->assertForbidden();
});

it('enregistre le compte rendu et le bilan sanitaire, refusés au vétérinaire conseil', function () {
    $this->actingAs($this->jeu->technicien24);
    // Seuils S1/S2 des bovins allaitants (NBVC)
    DB::table('fourrage.elevage')->where('id', $this->jeu->elevage24a->id)->update(['systeme_bovin' => 'viande']);

    Livewire::test(Collecte::class, ['visiteId' => $this->visite->id])
        ->set('compteRendu', 'Non-délivrances et métrites chaque année.')
        ->call('enregistrerVisite')
        ->set('indicateurs.METRITE.nb_cas', '5')->set('indicateurs.METRITE.denominateur', '81')
        ->call('enregistrerIndicateur', 'METRITE')
        ->assertHasNoErrors()
        ->assertSee('S1 5 / S2 10 %')
        ->assertSee('repère à valider');

    expect($this->visite->fresh()?->compte_rendu)->toBe('Non-délivrances et métrites chaque année.')
        ->and(IndicateurSanitaire::query()->where('indicateur_code', 'METRITE')->value('nb_cas'))->toBe(5);

    $this->actingAs($this->jeu->veterinaireConseil24);
    Livewire::test(Collecte::class, ['visiteId' => $this->visite->id])->call('enregistrerVisite')->assertForbidden();
});

it('rattache un prélèvement de l\'élevage et saisit une analyse d\'eau lue avec repère et source', function () {
    $this->actingAs($this->jeu->technicien24);
    $prelevement = AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 61])->prelevement_id;

    Livewire::test(Prelevements::class, ['visiteId' => $this->visite->id])
        ->set('rattaches', [(string) $prelevement])->call('rattacher')->assertHasNoErrors();
    expect(Prelevement::query()->find($prelevement)?->visite_id)->toBe($this->visite->id);

    Livewire::test(Eau::class, ['visiteId' => $this->visite->id])
        ->set('point', 'Source')->set('valeurs.EAU_FER', '39')->set('valeurs.EAU_NITRATES', '12')
        ->call('ajouter')
        ->assertHasNoErrors()
        ->assertSee('Conforme')
        ->assertSee('repère à valider')
        ->assertSee('Repère non disponible');

    $analyse = AnalyseEau::query()->sole();
    Livewire::test(Eau::class, ['visiteId' => $this->visite->id])->call('valider', $analyse->id);
    expect($analyse->fresh()?->statut)->toBe(AnalyseEau::STATUT_VALIDE);
});

it('saisit une étiquette et affiche l\'apport journalier ; la refuse hors visite ouverte', function () {
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(Etiquettes::class, ['visiteId' => $this->visite->id])
        ->set('libelle', 'Minéral du sac')->set('dose', '100')->set('dateLimite', '2025-04-13')
        ->set('composition', [['code' => 'SE', 'valeur' => '40', 'unite' => 'mg/kg']])
        ->call('ajouter')
        ->assertHasNoErrors()
        ->assertSee('« Minéral du sac »', false)
        ->assertSee('4 mg')
        ->assertSee('Date limite dépassée à la date de la visite');

    expect(EtiquetteAliment::query()->count())->toBe(1);

    $this->visite->update(['statut' => Visite::STATUT_PUBLIEE]);
    Livewire::test(Etiquettes::class, ['visiteId' => $this->visite->id])
        ->assertDontSee('Saisir une étiquette')
        ->set('libelle', 'Autre')->call('ajouter')->assertForbidden();
});

it('saisit les mesures une ligne à la fois et propose la lecture combinée du groupe', function () {
    $this->actingAs($this->jeu->technicien24);
    $ecran = Livewire::test(Mesures::class, ['visiteId' => $this->visite->id])->set('stade', 'prepa_velage');

    foreach (['GLYCEMIE' => 42, 'UREE' => 100, 'PH_BOUSE' => 5.8] as $code => $valeur) {
        $ecran->set('mesure', $code)->set('valeur', (string) $valeur)->call('ajouter')->assertHasNoErrors()->assertSet('valeur', null);
    }

    $ecran->assertSee('Sous le repère')
        ->assertSee('de 60 à 70 mg/dl')
        ->assertSee('Manque d\'azote avec excès relatif de sucres probable')
        ->call('validerTout');

    expect(MesureTerrain::query()->where('statut', MesureTerrain::STATUT_VALIDEE)->count())->toBe(3);
});

it('laisse le vétérinaire d\'élevage consenti voir la page en lecture ; le refuse avant publication', function () {
    $this->actingAs($this->jeu->veterinaire);

    Livewire::test(Profils::class, ['visiteId' => $this->visite->id])->assertForbidden();
});

it('saisit un profil sanguin, lu par rapport au repère, validé par le technicien seulement', function () {
    $this->actingAs($this->jeu->veterinaireConseil24);
    Livewire::test(Profils::class, ['visiteId' => $this->visite->id])
        ->set('element', 'selenium')->set('moyenne', '0.03')->set('nbAnimaux', '5')
        ->call('ajouter')
        ->assertHasNoErrors()
        ->assertSee('Carence sévère')
        ->assertSee('de 0,06 à 0,36 mg/l');

    $profil = AnalyseAnimale::query()->sole();
    Livewire::test(Profils::class, ['visiteId' => $this->visite->id])->call('valider', $profil->id)->assertForbidden();

    $this->actingAs($this->jeu->technicien24);
    Livewire::test(Profils::class, ['visiteId' => $this->visite->id])->call('valider', $profil->id);
    expect($profil->fresh()?->statut)->toBe(AnalyseAnimale::STATUT_VALIDEE);
});

it('rend une visite d\'un autre GDS introuvable dans chaque volet', function () {
    $visite47 = app(VisiteService::class)->creer($this->jeu->elevage47, $this->jeu->technicien47, '2026-01-15', 'initiale');
    $this->actingAs($this->jeu->technicien24);

    foreach ([Collecte::class, Prelevements::class, Eau::class, Etiquettes::class, Mesures::class, Profils::class] as $volet) {
        expect(fn () => Livewire::test($volet, ['visiteId' => $visite47->id]))->toThrow(ModelNotFoundException::class);
    }
});
