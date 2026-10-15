<?php

use App\Http\Controllers\AiguillageController;
use App\Http\Controllers\BulletinPdfController;
use App\Http\Controllers\ElevageCourantController;
use App\Http\Controllers\NoticeRgpdController;
use App\Livewire\Interpretation\InterpretationBulletin;
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

        // E02 — Accueil éleveur (contenu aux lots suivants)
        Route::view('/accueil', 'espace.accueil')->middleware('role:eleveur|veterinaire')->name('accueil');

        // E17 — Compte et consentements
        Route::middleware('role:eleveur|veterinaire')->group(function () {
            Route::view('/compte', 'espace.compte')->name('compte');
            Route::view('/compte/consentements', 'espace.consentements')->name('compte.consentements');
        });

        // E18 — Travail du technicien (le vétérinaire conseil y trouve ses visas, E21)
        Route::view('/travail', 'espace.travail')->middleware('role:technicien|veterinaire_gds')->name('travail');

        // E19 — Import des bulletins (EF-M9-01) ; PDF servis par route signée et policy (ENF-SEC-05)
        Route::view('/import/bulletins', 'espace.import.bulletins')->middleware('permission:bulletin.importer')->name('import.bulletins');
        Route::get('/import/bulletins/{import}/pdf', [BulletinPdfController::class, 'import'])->middleware('signed')->name('import.bulletins.pdf');
        Route::get('/bulletins/{analyse}/pdf', [BulletinPdfController::class, 'analyse'])->middleware('signed')->name('bulletins.pdf');

        // E20 — Interprétation d'un bulletin (EF-M2-02 à EF-M2-04) ; analyse liée à travers les scopes (404 hors périmètre)
        Route::get('/analyses/{analyse}/interpretation', InterpretationBulletin::class)->middleware('permission:interpretation.lire')->name('analyses.interpretation');

        // E23 — Administration : GDS, comptes, conventions
        Route::middleware('role:admin|admin_global')->prefix('admin')->name('admin.')->group(function () {
            Route::view('/gds', 'espace.admin.gds')->name('gds');
            Route::view('/utilisateurs', 'espace.admin.utilisateurs')->name('utilisateurs');
            Route::view('/conventions', 'espace.admin.conventions')->name('conventions');

            // E24 — Référentiels versionnés (EF-M10-02) : {table} ∈ seuils, plages (404 sinon) ; l'écriture
            // (permission referentiel.ecrire) est autorisée côté serveur dans chaque action du composant (ENF-SEC-04)
            Route::view('/referentiels/{table}', 'espace.admin.referentiels')->middleware('permission:referentiel.lire')->name('referentiels');
        });
    });
});
