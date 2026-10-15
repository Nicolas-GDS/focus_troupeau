<?php

use Illuminate\Support\Facades\Route;

Route::redirect('/', '/accueil');

// E02 — Accueil (page provisoire : mise en page bi-entité et navigation à l'étape 6 du lot L1)
Route::view('/accueil', 'accueil')->middleware('auth')->name('accueil');
