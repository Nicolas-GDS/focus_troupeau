<?php

/*
|--------------------------------------------------------------------------
| Jetons de marque (§ 8.1)
|--------------------------------------------------------------------------
|
| Aucun écran n'écrit le nom en dur : si le conseil d'administration retient un autre nom
| (point ouvert n° 12 du § 9.4), seuls ces jetons et les fichiers du signe changent.
|
*/

return [

    'nom' => env('APP_NAME', 'Focus Troupeau'),

    // Première mention dans l'application (page de connexion, pied de page).
    'baseline' => 'l\'approche globale de la santé du troupeau',

    // Signature, en Source Serif 4 italique Violet Santé.
    'signature' => 'Voir large, agir juste.',

    // Nom de fichier et d'URL.
    'slug' => 'focustroupeau',

];
