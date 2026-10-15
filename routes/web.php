<?php

use App\Http\Controllers\AiguillageController;
use App\Http\Controllers\ElevageCourantController;
use App\Http\Controllers\NoticeRgpdController;
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

        // E23 — Administration : GDS, comptes, conventions
        Route::middleware('role:admin|admin_global')->prefix('admin')->name('admin.')->group(function () {
            Route::view('/gds', 'espace.admin.gds')->name('gds');
            Route::view('/utilisateurs', 'espace.admin.utilisateurs')->name('utilisateurs');
            Route::view('/conventions', 'espace.admin.conventions')->name('conventions');
        });
    });
});
