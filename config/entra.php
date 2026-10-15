<?php

use App\Services\Authentification\GroupesEntra;

/*
|--------------------------------------------------------------------------
| Connexion des agents GDS par Entra ID (EF-M0-04)
|--------------------------------------------------------------------------
|
| Les GDS et le rôle sont attribués à la première connexion à partir des groupes
| Entra présents dans le jeton d'identité (revendication « groups »), puis gérés
| dans l'application. Un compte dont aucun groupe n'est déclaré ici est refusé (T-31).
|
| ENTRA_GROUPES : liste « id_groupe:role:gds » séparée par des virgules, où gds vaut
| 24, 47 ou * (tous les GDS). Exemple :
|   ENTRA_GROUPES="1111…:technicien:24,2222…:technicien:47,3333…:veterinaire_gds:24,4444…:admin_global:*"
|
*/

return [

    'actif' => filled(env('MICROSOFT_CLIENT_ID')),

    // Rôles qu'un groupe Entra peut attribuer (§ 2.2) : les comptes éleveur et vétérinaire d'élevage sont des comptes propres.
    'roles_autorises' => ['technicien', 'veterinaire_gds', 'admin', 'admin_global'],

    'groupes' => GroupesEntra::lire((string) env('ENTRA_GROUPES', '')),

];
