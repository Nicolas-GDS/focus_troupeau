<?php

use App\Http\Controllers\AiguillageController;
use App\Http\Controllers\AutonomiePdfController;
use App\Http\Controllers\BulletinPdfController;
use App\Http\Controllers\ElevageCourantController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\FicheVisiteController;
use App\Http\Controllers\NoteLiaisonController;
use App\Http\Controllers\NoticeRgpdController;
use App\Http\Controllers\TravailController;
use App\Livewire\Autonomie\Approvisionnement;
use App\Livewire\Autonomie\Bilan as AutonomieBilan;
use App\Livewire\Autonomie\Parametres as ParametresElevageEcran;
use App\Livewire\Autonomie\ScenarioEcran;
use App\Livewire\Dossier\Interpretation\EcranVisite;
use App\Livewire\Dossier\Lecture\Accueil;
use App\Livewire\Dossier\Lecture\Aliments;
use App\Livewire\Dossier\Lecture\Animaux;
use App\Livewire\Dossier\Lecture\Eau;
use App\Livewire\Dossier\Lecture\Fourrage;
use App\Livewire\Dossier\Lecture\Fourrages;
use App\Livewire\Dossier\Lecture\Historique;
use App\Livewire\Dossier\Lecture\Matrice;
use App\Livewire\Dossier\Lecture\PlanAction;
use App\Livewire\Dossier\Lecture\Sanitaire;
use App\Livewire\Dossier\Lecture\SyntheseVisite;
use App\Livewire\Dossier\NoteLiaisonVeterinaire;
use App\Livewire\Dossier\SaisieVisite;
use App\Livewire\Dossier\Visites;
use App\Livewire\Elevages\Fiche as ElevageFiche;
use App\Livewire\Elevages\Liste as ElevagesListe;
use App\Livewire\Import\ImportSagema;
use App\Livewire\Interpretation\InterpretationBulletin;
use App\Livewire\Stocks\Inventaires;
use App\Livewire\Stocks\SaisieInventaire;
use App\Livewire\Troupeau\Troupeau;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Espace connecté (annexe 10)
|--------------------------------------------------------------------------
|
| Toute route ajoutée ici doit être couverte par le test de cloisonnement T-40
| (tests/Feature/Cloisonnement/RoutesTest.php), qui échoue sur une route non déclarée.
|
*/

Route::middleware(['auth', 'compte.actif'])->group(function () {
    // ENF-RGPD-02 : notice d'information à la première connexion
    Route::get('/notice-rgpd', [NoticeRgpdController::class, 'show'])->name('notice-rgpd');
    Route::post('/notice-rgpd', [NoticeRgpdController::class, 'store'])->name('notice-rgpd.accepter');

    Route::middleware('notice.rgpd')->group(function () {
        Route::get('/', AiguillageController::class)->name('tableau-de-bord');
        Route::view('/compte/sans-role', 'espace.sans-role')->name('compte-sans-role');

        // EF-M0-08 : sélecteur d'élevage en tête d'écran
        Route::post('/elevage-courant', ElevageCourantController::class)->name('elevage-courant');

        // E02 — Accueil de l'éleveur et du vétérinaire d'élevage (EF-M1-01)
        Route::get('/accueil', Accueil::class)->middleware('role:eleveur|veterinaire')->name('accueil');

        // E03 à E11 — dossier élevage en lecture (annexe 10) : élevage lié à travers les scopes (404 hors périmètre),
        // objets lus selon les policies (un compte externe ne lit que le publié, § 6.3)
        Route::middleware('role:eleveur|veterinaire|technicien|veterinaire_gds')->prefix('elevages/{elevage}')->name('dossier.')->group(function () {
            Route::get('/visites/{visite}', SyntheseVisite::class)->name('visite');
            Route::get('/fourrages', Fourrages::class)->name('fourrages');
            Route::get('/fourrages/{prelevement}', Fourrage::class)->name('fourrage');
            Route::get('/mineraux-matrice', Matrice::class)->name('matrice');
            Route::get('/eau', Eau::class)->name('eau');
            Route::get('/animaux', Animaux::class)->name('animaux');
            Route::get('/aliments', Aliments::class)->name('aliments');
            Route::get('/sanitaire', Sanitaire::class)->name('sanitaire');
            Route::get('/historique', Historique::class)->name('historique');
            Route::get('/plan-action', PlanAction::class)->name('plan');
        });

        // E17 — Compte et consentements
        Route::middleware('role:eleveur|veterinaire')->group(function () {
            Route::view('/compte', 'espace.compte')->name('compte');
            Route::view('/compte/consentements', 'espace.consentements')->name('compte.consentements');
        });

        // E18 — Travail du technicien (le vétérinaire conseil y trouve ses visas, E21)
        Route::get('/travail', TravailController::class)->middleware('role:technicien|veterinaire_gds')->name('travail');

        // E19 — Import des bulletins (EF-M9-01) ; PDF servis par route signée et policy (ENF-SEC-05)
        Route::view('/import/bulletins', 'espace.import.bulletins')->middleware('permission:bulletin.importer')->name('import.bulletins');
        // EF-M9-03 — import SAGEMA (élevages adhérents, bilan sanitaire ; T-27)
        Route::get('/import/sagema', ImportSagema::class)->middleware('permission:sagema.importer')->name('import.sagema');
        Route::get('/import/bulletins/{import}/pdf', [BulletinPdfController::class, 'import'])->middleware('signed')->name('import.bulletins.pdf');
        Route::get('/bulletins/{analyse}/pdf', [BulletinPdfController::class, 'analyse'])->middleware('signed')->name('bulletins.pdf');

        // E20 — Interprétation d'un bulletin (EF-M2-02 à EF-M2-04) ; analyse liée à travers les scopes (404 hors périmètre)
        Route::get('/analyses/{analyse}/interpretation', InterpretationBulletin::class)->middleware('permission:interpretation.lire')->name('analyses.interpretation');

        // Lot L3 — visites de l'élevage courant et saisie d'une visite (E03, EF-M1-09) ; visite liée à travers les
        // scopes (404 hors périmètre), chaque volet autorise ses actions côté serveur (ENF-SEC-04)
        Route::get('/visites', Visites::class)->middleware('permission:visite.lire')->name('visites');
        Route::get('/visites/{visite}/saisie', SaisieVisite::class)->middleware('permission:visite.lire')->name('visites.saisie');

        // E21 — interprétation de visite et publication (EF-M2-04 à EF-M2-07)
        Route::get('/visites/{visite}/interpretation', EcranVisite::class)->middleware('permission:interpretation.lire')->name('visites.interpretation');
        // Fiche de visite figée (EF-M2-07, EF-GEN-08) : lien signé et policy (ENF-SEC-05)
        Route::get('/visites/{visite}/fiches/{version}/pdf', [FicheVisiteController::class, 'pdf'])->middleware('signed')->name('visites.fiche.pdf');

        // Note de liaison vétérinaire (EF-M2-09) : vétérinaire destinataire et agents du périmètre (policy)
        Route::get('/notes-liaison/{note}', NoteLiaisonVeterinaire::class)->name('notes-liaison.voir');
        Route::get('/notes-liaison/{note}/pdf', [NoteLiaisonController::class, 'pdf'])->middleware('signed')->name('notes-liaison.pdf');

        // E12, E13 — stocks fourragers et troupeau (EF-M4, EF-M5, annexe 10 : éleveur, technicien) ; élevage lié à travers les scopes (404 hors
        // périmètre), écriture autorisée par StockPolicy (convention active, T-32)
        Route::middleware('role:eleveur|technicien')->prefix('elevages/{elevage}')->group(function () {
            Route::get('/stocks', Inventaires::class)->name('stocks');
            Route::get('/stocks/{inventaire}', SaisieInventaire::class)->name('stocks.inventaire');
            // E13 — troupeau : lots d'animaux, phases, rations (EF-M5)
            Route::get('/troupeau', Troupeau::class)->name('troupeau');
            // E14 — autonomie, bilan et projection (EF-M6)
            Route::get('/autonomie', AutonomieBilan::class)->name('autonomie');
            Route::get('/autonomie/pdf', [AutonomiePdfController::class, 'bilan'])->name('autonomie.pdf');
            // E15 — approvisionnement : proposition initiale, scénarios, comparaison (EF-M7)
            Route::get('/approvisionnement', Approvisionnement::class)->name('approvisionnement');
            Route::get('/scenarios/{scenario}', ScenarioEcran::class)->name('scenarios.voir');
            Route::get('/scenarios/{scenario}/pdf', [AutonomiePdfController::class, 'scenario'])->name('scenarios.pdf');
            // E16 — paramètres de l'élevage (EF-M4-07, D10)
            Route::get('/parametres', ParametresElevageEcran::class)->name('parametres');
        });

        // EF-M9-04, EF-M9-05 — exports de l'élevage (rationnement, portabilité RGPD)
        Route::middleware('permission:donnees.exporter')->prefix('elevages/{elevage}/export')->name('export.')->group(function () {
            Route::get('/rationnement.csv', [ExportController::class, 'rationnement'])->name('rationnement');
            Route::get('/donnees.zip', [ExportController::class, 'donnees'])->name('donnees');
        });

        // E23 — élevages : création, identité, convention, invitation des éleveurs (EF-M0-02 ; technicien, administrateur)
        Route::middleware('permission:elevage.ecrire')->group(function () {
            Route::get('/elevages', ElevagesListe::class)->name('elevages');
            Route::get('/elevages/{elevage}/fiche', ElevageFiche::class)->name('elevages.fiche');
        });

        // E23 — Administration : GDS, comptes, conventions
        Route::middleware('role:admin|admin_global')->prefix('admin')->name('admin.')->group(function () {
            Route::view('/gds', 'espace.admin.gds')->name('gds');
            Route::view('/utilisateurs', 'espace.admin.utilisateurs')->name('utilisateurs');
            Route::view('/conventions', 'espace.admin.conventions')->name('conventions');
            Route::view('/journal', 'espace.admin.journal')->name('journal');

            // E24 — Référentiels versionnés (EF-M10-02) : {table} ∈ seuils, plages (404 sinon) ; l'écriture
            // (permission referentiel.ecrire) est autorisée côté serveur dans chaque action du composant (ENF-SEC-04)
            Route::view('/referentiels/{table}', 'espace.admin.referentiels')->middleware('permission:referentiel.lire')->name('referentiels');
        });
    });
});
