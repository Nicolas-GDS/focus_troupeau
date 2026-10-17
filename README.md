# Focus Troupeau

Focus Troupeau, l'approche globale de la santé du troupeau : application web de la prestation d'approche globale
du GDS 24 (Dordogne) et du GDS 47 (Lot-et-Garonne).

Les spécifications sont dans [`docs/specs/`](docs/specs/) (chapitres 00 à 10) et font foi.
Les consignes de développement sont dans [`CLAUDE.md`](CLAUDE.md).

## Pile

PHP 8.3, Laravel 12, Livewire 3, Blade, Alpine.js, Tailwind CSS ; PostgreSQL 16 + PostGIS 3.4 ; Redis ;
Pest, Pint, Larastan niveau 6. Tout tourne dans Docker : aucune installation de PHP ni de Composer sur le poste.

## Installation (développement)

Prérequis : Docker Desktop avec l'intégration WSL activée pour la distribution utilisée (sous Windows).

```bash
cp .env.example .env                       # ajuster HOST_UID / HOST_GID (commande `id`) et les ports si besoin
docker compose build
docker compose up -d
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate --seed
docker compose run --rm --no-deps node sh -c "npm install && npm run build"
```

| Service | Rôle | Accès depuis le poste |
|---|---|---|
| `app` | PHP-FPM 8.3 (avec Python 3 et pdftotext pour l'extraction des bulletins LANO) | `docker compose exec app …` |
| `nginx` | Serveur web | http://localhost:8090 |
| `postgres` | PostgreSQL 16 + PostGIS 3.4 ; bases `focustroupeau` et `focustroupeau_test` | `localhost:54320` |
| `redis` | Sessions, cache, files d'attente | — |
| `queue-worker` | `php artisan queue:work` | — |
| `scheduler` | `php artisan schedule:work` | — |
| `python` | Implémentation de référence (`calculs/`) | `docker compose exec python …` |
| `mailpit` | Capture des e-mails en développement | http://localhost:8026 |
| `node` | Vite / Tailwind (profil `front`) | `docker compose --profile front up node` (port 5174) |

### Comptes de démonstration (jeux d'essai J4, J3, J5 et J1, § 9.1)

`migrate --seed` charge le jeu J4, entièrement synthétique, puis le jeu J3 : l'élevage `ELV-0001` et ses neuf lots
du document 04, rattachés au GDS 24 (recette : `docs/recette/L3_recette_J3.md`), et le jeu J5 : une population
de référence synthétique (élevages `J5-…` consentants, lots validés) pour voir le positionnement et l'historique
(`php artisan references:bilan`), et le jeu J1 : l'élevage allaitant synthétique `J1-ALLAITANT` (stocks et
troupeau, recette : `docs/recette/L5_recette_J1.md`). Tous sont interdits en production. Mot de passe commun :
`J4_MOT_DE_PASSE` dans `.env` (par défaut `j4-developpement-seulement`).

| Compte | Rôle | Périmètre |
|---|---|---|
| `technicien.24@j4.test` | technicien | GDS 24 : J4-24A, J4-24B |
| `technicien.47@j4.test` | technicien | GDS 47 : J4-47 |
| `charge.mission@j4.test` | technicien + admin | GDS 24 et 47 |
| `veterinaire.conseil.24@j4.test` | veterinaire_gds | GDS 24 |
| `admin.24@j4.test` | admin | GDS 24 |
| `admin.global@j4.test` | admin_global | toute l'instance |
| `eleveur.24a@j4.test` | eleveur | J4-24A |
| `eleveur.47@j4.test` | eleveur | J4-47 |
| `veterinaire@j4.test` | veterinaire | J4-24A (consentement actif) |
| `eleveur.elv0001@j3.test` | eleveur | ELV-0001 (J3) |
| `eleveur.j1@j1.test` | eleveur | J1-ALLAITANT (J1) |

En production, les agents se connectent par Entra ID et n'ont pas de mot de passe local.

Les ports se changent dans `.env` (`APP_PORT`, `DB_FORWARD_PORT`, `MAILPIT_UI_PORT`, `VITE_PORT`).

Le script `03_chargement_campagne_*.sql` (données nominatives) n'est pas versionné : il est chargé à la main,
en recette uniquement.

### À savoir en développement

- **Files d'attente** : le service `queue-worker` garde le code en mémoire. Après toute modification du code PHP,
  `docker compose exec app php artisan queue:restart` (à faire aussi à chaque déploiement).
- **Styles** : Tailwind ne génère que les classes présentes dans les vues lors de la compilation. Après avoir modifié
  une vue, recompiler (`docker compose run --rm --no-deps node npm run build`) ou garder le serveur Vite actif
  (`docker compose --profile front up node`).
- **Bulletins** : `scripts/parse_lano.py` extrait les PDF LANO (`--json fichier.pdf`) ; bulletins anonymisés
  d'exemple dans `tests/Fixtures/lano/`.
- **Recette** : procédures par lot dans `docs/recette/` (lot L2 : `L2_recette_J0.md`, commandes `bulletins:*`).

## Qualité

```bash
docker compose exec app php artisan test        # Pest, sur la base focustroupeau_test
docker compose exec app vendor/bin/pint --test  # style
docker compose exec app vendor/bin/phpstan analyse --memory-limit=1G
docker compose exec python python3 calculs/test_calculs.py   # référence Python (RC-5 à RC-14)
```

L'intégration continue (`.github/workflows/ci.yml`) exécute Pint, Larastan et Pest à chaque envoi sur `main` et les branches `lot*`.

## Organisation

Développement par lot (chapitre 9.3), une branche par lot (`lot1-socle`, `lot2-import`, …), fusion dans `main`
après revue et tests verts.
