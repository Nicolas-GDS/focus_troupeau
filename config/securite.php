<?php

/*
| En-têtes de sécurité (ENF-SEC-01) : mode de la politique de sécurité du contenu, durée HSTS, sources du serveur
| Vite en développement (séparées par des virgules, vides en production).
*/
return [
    'csp' => env('SECURITE_CSP', 'enforce'),
    'hsts_secondes' => (int) env('SECURITE_HSTS_SECONDES', 31536000),
    'csp_sources_dev' => env('SECURITE_CSP_SOURCES_DEV', ''),
];
