<?php

/*
|--------------------------------------------------------------------------
| Paramètres de sécurité de l'authentification (EF-M0-03, ENF-SEC-02, ENF-SEC-03)
|--------------------------------------------------------------------------
|
| Seule la durée du lien de connexion (15 minutes) figure dans les spécifications.
| Les autres valeurs sont des propositions du lot L1, à valider (signalées « [proposé] »).
|
*/

return [

    // Lien de connexion : 15 minutes, usage unique (EF-M0-03, ENF-SEC-02).
    'lien_connexion_minutes' => 15,

    // [proposé] Validité du lien d'activation envoyé à la création du compte (§ 2.4).
    'activation_jours' => (int) env('AUTH_ACTIVATION_JOURS', 7),

    // [proposé] Code de double authentification par e-mail : validité et nombre d'essais.
    'code_minutes' => (int) env('AUTH_CODE_MINUTES', 10),
    'code_tentatives' => (int) env('AUTH_CODE_TENTATIVES', 5),

    // Verrouillage progressif après 5 échecs (ENF-SEC-02) ; [proposé] durée initiale doublée
    // à chaque nouvel échec, plafonnée.
    'verrouillage' => [
        'seuil_echecs' => 5,
        'duree_initiale_secondes' => (int) env('AUTH_VERROUILLAGE_INITIAL', 60),
        'duree_max_secondes' => (int) env('AUTH_VERROUILLAGE_MAX', 3600),
        'memoire_heures' => 24,
    ],

    // Mot de passe des comptes du jeu d'essai J4 (*@j4.test), développement uniquement (§ 9.1).
    'mot_de_passe_j4' => env('J4_MOT_DE_PASSE') ?: 'j4-developpement-seulement',

];
