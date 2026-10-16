<?php

/*
|--------------------------------------------------------------------------
| Fiches et PDF à la charte (EF-GEN-08, § 8.4, chapitre 6.1)
|--------------------------------------------------------------------------
|
| Chromium et Puppeteer sont installés dans l'image applicative (docker/php/Dockerfile). Les fiches figées (HTML
| autonome et PDF) sont archivées sur le disque privé, jamais servies directement (ENF-SEC-05).
|
*/

return [
    'chrome' => env('BROWSERSHOT_CHROME_PATH'),
    'node_modules' => env('BROWSERSHOT_NODE_MODULES'),
    'delai_secondes' => (int) env('FICHES_DELAI_SECONDES', 60),
    'disque' => env('FICHES_DISQUE', 'local'),
    'dossier' => 'fiches',
];
