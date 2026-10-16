<?php

use App\Models\Dossier\AnalyseEau;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Dossier\IndicateurSanitaire;
use App\Models\Dossier\MesureTerrain;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\AnalyseAnimale;
use App\Services\Dossier\EauService;
use App\Services\Dossier\EtiquettesService;
use App\Services\Dossier\MesuresService;
use App\Services\Dossier\ProfilsService;
use App\Services\Dossier\VisiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\JeuCloisonnement;

// § 2.3, § 6.3, ENF-SEC-04 — policies du dossier : périmètre, permission, statut ; T-40 sur les objets du lot L3.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->actingAs($this->jeu->technicien24);
    $this->visite = app(VisiteService::class)->creer($this->jeu->elevage24a, $this->jeu->technicien24, '2026-09-03', 'initiale');
    $this->eau = app(EauService::class)->creer($this->visite, 'Source', '2026-09-03', null, ['EAU_FER' => ['valeur' => 39]]);
    $this->mesure = app(MesuresService::class)->ajouter($this->visite, $this->jeu->technicien24, '2026-09-03', 'GLYCEMIE', 42, 'BOV', 'prepa_velage');
    $this->profil = app(ProfilsService::class)->ajouter($this->visite, $this->jeu->technicien24, [
        'date_prelevement' => '2026-09-03', 'espece_code' => 'BOV', 'matrice' => 'serum', 'parametre' => 'selenium', 'valeur_moyenne' => 0.03, 'unite' => 'mg/l',
    ]);
    $this->etiquette = app(EtiquettesService::class)->creer($this->jeu->elevage24a, $this->visite->id, ['nature' => 'mineral', 'libelle_eleveur' => 'Sac'], []);
    auth()->logout();
});

it('laisse lire le dossier en cours aux agents du périmètre, pas aux comptes externes ni hors périmètre', function (string $profil, bool $attendu) {
    $user = $this->jeu->{$profil};

    foreach ([$this->visite, $this->eau, $this->mesure, $this->etiquette] as $objet) {
        expect(Gate::forUser($user)->allows('view', $objet))->toBe($attendu, $profil.' : '.$objet::class);
    }
})->with([
    ['technicien24', true], ['veterinaireConseil24', true], ['admin24', true], ['chargeDeMission', true],
    ['technicien47', false], ['eleveur24a', false], ['eleveur47', false], ['veterinaire', false],
]);

it('ouvre à l\'éleveur ses objets publiés seulement', function () {
    $eleveur = $this->jeu->eleveur24a;
    $this->visite->update(['statut' => Visite::STATUT_PUBLIEE]);
    $this->eau->update(['statut' => AnalyseEau::STATUT_PUBLIE]);
    $this->mesure->update(['statut' => MesureTerrain::STATUT_PUBLIEE]);

    expect(Gate::forUser($eleveur)->allows('view', $this->visite))->toBeTrue()
        ->and(Gate::forUser($eleveur)->allows('view', $this->eau))->toBeTrue()
        ->and(Gate::forUser($eleveur)->allows('view', $this->mesure))->toBeTrue()
        ->and(Gate::forUser($eleveur)->allows('view', $this->profil))->toBeFalse()
        ->and(Gate::forUser($this->jeu->eleveur47)->allows('view', $this->visite))->toBeFalse();
});

it('réserve la saisie au technicien du périmètre, tant que la visite est ouverte', function () {
    $tech = Gate::forUser($this->jeu->technicien24);
    $elevage = $this->jeu->elevage24a;

    expect($tech->allows('create', [Visite::class, $elevage]))->toBeTrue()
        ->and(Gate::forUser($this->jeu->technicien47)->allows('create', [Visite::class, $elevage]))->toBeFalse()
        ->and(Gate::forUser($this->jeu->veterinaireConseil24)->allows('create', [Visite::class, $elevage]))->toBeFalse()
        ->and($tech->allows('update', $this->visite))->toBeTrue()
        ->and($tech->allows('valider', $this->eau))->toBeTrue()
        ->and($tech->allows('update', $this->mesure))->toBeTrue()
        ->and($tech->allows('create', [IndicateurSanitaire::class, $elevage]))->toBeTrue()
        ->and(Gate::forUser($this->jeu->eleveur24a)->allows('create', [IndicateurSanitaire::class, $elevage]))->toBeFalse();

    $this->visite->update(['statut' => Visite::STATUT_PUBLIEE]);

    expect($tech->allows('update', $this->visite->fresh()))->toBeFalse()
        ->and($tech->allows('update', $this->mesure->fresh()))->toBeFalse()
        ->and($tech->allows('create', [MesureTerrain::class, $elevage, $this->visite->id]))->toBeFalse()
        ->and($tech->allows('delete', $this->eau->fresh()))->toBeFalse()
        ->and($tech->allows('delete', $this->visite->fresh()))->toBeFalse();
});

it('fige un objet validé : plus de correction ni de suppression', function () {
    $tech = Gate::forUser($this->jeu->technicien24);
    $this->mesure->update(['statut' => MesureTerrain::STATUT_VALIDEE]);
    $this->eau->update(['statut' => AnalyseEau::STATUT_VALIDE]);

    expect($tech->allows('update', $this->mesure->fresh()))->toBeFalse()
        ->and($tech->allows('delete', $this->eau->fresh()))->toBeFalse()
        ->and($tech->allows('valider', $this->eau->fresh()))->toBeFalse();
});

it('laisse les vétérinaires saisir des profils sanguins, la validation restant au technicien (§ 2.3)', function () {
    expect(Gate::forUser($this->jeu->veterinaire)->allows('create', [AnalyseAnimale::class, $this->jeu->elevage24a]))->toBeTrue()
        ->and(Gate::forUser($this->jeu->veterinaire)->allows('create', [AnalyseAnimale::class, $this->jeu->elevage24b]))->toBeFalse()
        ->and(Gate::forUser($this->jeu->veterinaireConseil24)->allows('update', $this->profil))->toBeTrue()
        ->and(Gate::forUser($this->jeu->veterinaireConseil24)->allows('valider', $this->profil))->toBeFalse()
        ->and(Gate::forUser($this->jeu->technicien24)->allows('valider', $this->profil))->toBeTrue();
});

it('laisse l\'éleveur saisir et corriger ses étiquettes, pas celles du technicien (§ 2.3)', function () {
    $eleveur = $this->jeu->eleveur24a;
    $this->actingAs($eleveur);
    $sienne = app(EtiquettesService::class)->creer($this->jeu->elevage24a, null, ['nature' => 'seau', 'libelle_eleveur' => 'Seau'], []);
    auth()->logout();

    expect(Gate::forUser($eleveur)->allows('create', [EtiquetteAliment::class, $this->jeu->elevage24a]))->toBeTrue()
        ->and(Gate::forUser($eleveur)->allows('view', $sienne))->toBeTrue()
        ->and(Gate::forUser($eleveur)->allows('update', $sienne))->toBeTrue()
        ->and(Gate::forUser($eleveur)->allows('valider', $sienne))->toBeFalse()
        ->and(Gate::forUser($eleveur)->allows('update', $this->etiquette))->toBeFalse()
        ->and(Gate::forUser($this->jeu->eleveur47)->allows('view', $sienne))->toBeFalse()
        ->and(Gate::forUser($this->jeu->technicien24)->allows('valider', $sienne))->toBeTrue();
});
