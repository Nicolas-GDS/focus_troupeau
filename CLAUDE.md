# Focus Troupeau — consignes pour Claude Code

Application web de la prestation d'approche globale de la santé du troupeau (GDS 24 / GDS 47).
Les spécifications complètes sont dans `docs/specs/` (chapitres 00 à 10) : elles font foi.
Avant toute tâche, lire le chapitre concerné ; ne jamais inventer une règle de calcul ou une valeur de référence
qui n'y figure pas — si une information manque, le dire et proposer, ne pas combler en silence.

## Pile et conventions

- PHP 8.3, Laravel 12, Livewire 3, Blade, Alpine.js, Tailwind (jetons du système de design Coélab, palette charte GDS v2.0).
- PostgreSQL 16 + PostGIS 3.4 ; Redis ; Pest ; Pint ; Larastan niveau 6.
- Le socle de données existant (schémas `ref`, `prive`, `fourrage`, `sig`, `valo`) vient des scripts `database/sql/01..02_*.sql`
  (document 02 du dossier Approche globale) : ils sont exécutés tels quels par la migration `0001_socle_document02`
  et ne sont jamais modifiés. Le script 03 (chargement des données) n'est pas versionné : il est passé directement
  en base par l'administrateur, hors migrations — ne pas le recréer ni en dépendre dans les tests (seeders dédiés). Les extensions vont dans des migrations SQL brutes (`DB::unprepared`) par schéma
  (`app`, `dossier`, `stock`, `plan`), conformément au chapitre 5.
- Toute la logique métier est dans `app/Domain/` (classes PHP pures, DTO, sans Eloquent), testée unitairement.
  Les composants Livewire appellent des services (`app/Services/`), qui appellent le domaine. Aucun calcul dans une vue ou un modèle.
- Aucune constante métier codée en dur : pertes, marges, coefficients, prix, seuils viennent des référentiels
  (`app.parametre_defaut`, tables `ref.*`) via `App\Domain\Referentiels`.
- Les règles de calcul RC-5 à RC-14 ont une implémentation de référence Python dans `calculs/` avec 60 vérifications :
  le code PHP doit reproduire exactement les valeurs attendues de `calculs/test_calculs.py` (tests Pest équivalents, cas T-10 à T-16).
- Cloisonnement : scopes globaux `GdsScope` et `ElevageScope` + policies sur chaque modèle exposé (chapitre 6.3) ;
  un éleveur ne voit que les objets au statut `publie`. Les tests de cloisonnement (T-40) sont obligatoires sur chaque route ajoutée.
- Textes en français ; unités des bulletins LANO et de l'INRA 2018 ; aucun nom de produit ni de marque dans un libellé généré (EF-GEN-04).
- Toute valeur affichée à l'éleveur est accompagnée de son repère et de sa source ; la couleur d'état n'est jamais seule (EF-GEN-02, EF-GEN-03).

## Façon de travailler

- Travailler par lot (chapitre 9.3 : L1 socle → L2 import/interprétation → L3 dossier/publication → L4 positionnement →
  L5 stocks/troupeau → L6 autonomie/approvisionnement → L7 administration → L8 recette). Ne pas commencer un lot avant que
  les tests du précédent passent.
- Pour chaque tâche : citer les identifiants d'exigences (EF-xx) et de règles (RC-n) couverts dans le message de commit.
- Écrire les tests avant ou avec le code ; `php artisan test` doit être vert avant de proposer un commit ; `vendor/bin/pint` et `vendor/bin/phpstan` sans erreur.
- Ne pas installer de dépendance non listée au chapitre 6 sans le signaler explicitement.
- Secrets uniquement dans `.env` (jamais commités) ; `.env.example` à jour.
- Commits en français, petits et fréquents ; une branche par lot (`lot1-socle`, `lot2-import`, …).

## Commandes

- Démarrage : `docker compose up -d` puis `docker compose exec app php artisan migrate --seed`
- Tests : `docker compose exec app php artisan test` ; référence Python : `python3 calculs/test_calculs.py`
- Qualité : `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`
