<?php

use Laravel\Fortify\Features;

/*
|--------------------------------------------------------------------------
| Fortify (chapitre 6.1, EF-M0-03)
|--------------------------------------------------------------------------
|
| Les routes de Fortify sont désactivées (Fortify::ignoreRoutes) : l'application
| déclare ses propres routes en français (routes/authentification.php, annexe 10)
| sur les contrôleurs de Fortify. Pas d'inscription libre (les comptes sont créés
| par le technicien, EF-M0-02) ; la double authentification est un code par e-mail
| (ENF-SEC-03), pas le TOTP de Fortify ; pas de passkeys.
|
*/

return [

    'guard' => 'web',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'lowercase_usernames' => true,

    'home' => '/accueil',

    'prefix' => '',

    'domain' => null,

    'middleware' => ['web'],

    // Le verrouillage progressif (ENF-SEC-02) est géré par App\Services\Authentification\VerrouillageConnexion.
    'limiters' => [
        'login' => null,
    ],

    'views' => true,

    'features' => [
        Features::resetPasswords(),
        Features::updatePasswords(),
    ],

];
