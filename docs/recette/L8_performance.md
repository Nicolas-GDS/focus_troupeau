# Performance et charge (T-42, ENF-PERF-01 à ENF-PERF-04)

## Seuils (ENF-PERF)

| Exigence | Seuil | Vérification |
|---|---|---|
| ENF-PERF-01 | Écran de consultation affiché en moins de 1 s (serveur) | Charge k6 (ci-dessous), 95e centile |
| ENF-PERF-01 | Calcul complet d'un bilan (job) en moins de 5 s | T-20 (`tests/Feature/Autonomie/EcranBilanTest.php`) |
| ENF-PERF-02 | Import d'un bulletin PDF en moins de 30 s | Recette du lot L2 (J0) |
| ENF-PERF-04 | PDF de fiche en moins de 10 s | T-26, `php artisan test --filter=Chromium` (exécution séquentielle) |

## Test de charge : 50 éleveurs simultanés

```sh
docker compose exec app php artisan db:seed                          # J1, J2, J3
docker compose exec app php artisan db:seed --class=JeuChargeSeeder   # 50 comptes charge.NN@charge.test
docker compose --profile charge run --rm k6 run /scripts/consultation.js
```

Chaque utilisateur simulé se connecte avec son propre compte, puis parcourt en boucle l'accueil (E02), les fourrages
(E04), la matrice minérale (E05), l'historique (E10), le plan d'action (E11), les stocks (E12) et l'autonomie (E14),
avec 1 à 3 s de lecture entre deux écrans, pendant 3 minutes (`-e DUREE=…` pour changer). Seuils : 95e centile des
écrans de consultation sous 1 s, moins de 1 % d'erreurs ; le détail est donné par écran.

## Résultats du 19/10/2026 (environnement de développement)

Machine partagée (WSL, 16 cœurs, autres projets actifs, charge moyenne de 14 à 24 pendant les mesures), mode
débogage actif : les chiffres ne préjugent pas de la production et seront repris sur le serveur de recette.

| Étape | Médiane | 95e centile | Erreurs |
|---|---|---|---|
| Pool PHP-FPM par défaut (5 processus) | 306 ms | 1,09 s | 0 % |
| Autorisations sans requête par objet | — | instable (1,1 à 3,7 s) selon la charge de la machine | 0 % |
| Pool de 20 processus | 248 ms | 1,14 s | 0 % |
| Pool de 20 processus, mesure la moins perturbée | 172 ms | 0,997 s | 0 % |

Corrections apportées :

- **N+1 des autorisations** : pour un éleveur, chaque analyse affichée déclenchait trois requêtes (périmètre, élevage
  du prélèvement, interprétation publiée). Ces vérifications sont mémorisées le temps d'une requête ou d'un job
  (`Perimetre`, instance limitée à la requête), et les analyses publiées d'un élevage sont lues en une requête.
  Historique : de 105 à 20 requêtes ; fourrages : de 41 à 23. Le test `tests/Feature/Interface/RequetesTest.php`
  empêche le retour de ces requêtes en boucle.
- **Pool PHP-FPM** : 20 processus (`docker/php/fpm-pool.conf`, variable `PHP_FPM_MAX_CHILDREN`) au lieu de 5 ; à
  dimensionner sur la mémoire du serveur de production (environ 64 Mo par processus).

## À refaire sur le serveur de recette

1. `APP_DEBUG=false`, caches de configuration, de routes et de vues (image de production, étape 6 du lot).
2. Lancer le scénario k6 depuis un poste du réseau du GDS (`-e BASE=https://…`), en dehors des heures d'usage.
3. Consigner médiane, 95e centile et erreurs par écran ; T-42 est acquis si le 95e centile de chaque écran reste sous
   1 s avec 50 utilisateurs.
