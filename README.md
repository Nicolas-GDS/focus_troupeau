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

Les ports se changent dans `.env` (`APP_PORT`, `DB_FORWARD_PORT`, `MAILPIT_UI_PORT`, `VITE_PORT`).

Le script `03_chargement_campagne_*.sql` (données nominatives) n'est pas versionné : il est chargé à la main,
en recette uniquement.

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
