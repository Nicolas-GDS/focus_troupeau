<?php

/*
|--------------------------------------------------------------------------
| Lecture AGDS par SAGEMA (EF-M9-03, § 6.4 « vue SQL (cible) »)
|--------------------------------------------------------------------------
|
| Identité de l'éleveur et bovins présents d'un cheptel, lus dans les vues en lecture seule
| du schéma « focus » de SAGEMA (connexion « sagema » de config/database.php). Aucune écriture
| vers SAGEMA ni AGDS. Désactivée tant que SAGEMA_DB_HOST n'est pas renseigné.
|
*/

return [

    'actif' => filled(env('SAGEMA_DB_HOST')),

    'connexion' => 'sagema',

    // Bornes (en mois) des tranches d'âge de la composition affichée : regroupement d'affichage proposé
    // (moins de 12 mois, 12 à 24 mois, 24 mois et plus), à valider par le comité technique.
    'tranches_age_mois' => [12, 24],

];
