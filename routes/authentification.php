<?php

use App\Http\Controllers\Authentification\ActivationController;
use App\Http\Controllers\Authentification\CodeConnexionController;
use App\Http\Controllers\Authentification\EntraController;
use App\Http\Controllers\Authentification\LienConnexionController;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Controllers\NewPasswordController;
use Laravel\Fortify\Http\Controllers\PasswordController;
use Laravel\Fortify\Http\Controllers\PasswordResetLinkController;

/*
|--------------------------------------------------------------------------
| E01 — Connexion, activation, lien de connexion (annexe 10, EF-M0-03 à EF-M0-05)
|--------------------------------------------------------------------------
|
| Routes publiques de Fortify déclarées en français ; les noms (login, password.*) sont ceux
| qu'attendent Fortify et Laravel.
|
*/

Route::middleware('guest')->group(function () {
    Route::get('/connexion', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/connexion', [AuthenticatedSessionController::class, 'store'])->name('login.store');

    // Double authentification : code reçu par e-mail (ENF-SEC-03)
    Route::get('/connexion/code', [CodeConnexionController::class, 'create'])->name('connexion.code');
    Route::post('/connexion/code', [CodeConnexionController::class, 'store'])->middleware('throttle:10,1')->name('connexion.code.verifier');
    Route::post('/connexion/code/renvoyer', [CodeConnexionController::class, 'renvoyer'])->middleware('throttle:3,1')->name('connexion.code.renvoyer');

    // Lien de connexion : 15 minutes, usage unique (T-30)
    Route::get('/connexion/lien', [LienConnexionController::class, 'create'])->name('connexion.lien');
    Route::post('/connexion/lien', [LienConnexionController::class, 'store'])->middleware('throttle:5,1')->name('connexion.lien.demander');
    Route::get('/connexion/lien/{jeton}', [LienConnexionController::class, 'confirmer'])->middleware('signed')->name('connexion.lien.confirmer');
    Route::post('/connexion/lien/{jeton}', [LienConnexionController::class, 'connecter'])->middleware(['signed', 'throttle:10,1'])->name('connexion.lien.connecter');

    // Activation du compte à partir de l'invitation (§ 2.4)
    Route::get('/activation/{jeton}', [ActivationController::class, 'create'])->middleware('signed')->name('activation');
    Route::post('/activation/{jeton}', [ActivationController::class, 'store'])->middleware(['signed', 'throttle:10,1'])->name('activation.enregistrer');

    // Mot de passe oublié
    Route::get('/mot-de-passe/oublie', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/mot-de-passe/oublie', [PasswordResetLinkController::class, 'store'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/mot-de-passe/reinitialisation/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/mot-de-passe/reinitialisation', [NewPasswordController::class, 'store'])->name('password.update');

    // Agents GDS : Entra ID (EF-M0-04, T-31)
    Route::get('/auth/entra', [EntraController::class, 'rediriger'])->name('entra');
    Route::get('/auth/entra/retour', [EntraController::class, 'retour'])->name('entra.retour');
});

Route::middleware('auth')->group(function () {
    Route::post('/deconnexion', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::put('/compte/mot-de-passe', [PasswordController::class, 'update'])->name('user-password.update');
});
