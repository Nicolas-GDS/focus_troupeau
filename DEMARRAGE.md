# Démarrer le développement avec Claude Code

## 1. Préparer le dépôt (une fois, à la main)

```
mkdir focustroupeau && cd focustroupeau && git init
mkdir -p docs/specs calculs database/sql
copier  V:\Approche globale\Livrables\07_Specifications_Focus_Troupeau\docs\*.md        → docs/specs/
copier  V:\Approche globale\Livrables\07_Specifications_Focus_Troupeau\calculs\*.py     → calculs/
copier  V:\Approche globale\Livrables\sql\01_schema_fourrages_gds24.sql
        V:\Approche globale\Livrables\sql\02_referentiels_interpretation.sql            → database/sql/
copier  V:\Approche globale\Livrables\07_Specifications_Focus_Troupeau\pour_le_depot\CLAUDE.md → CLAUDE.md
git add -A && git commit -m "Spécifications, socle SQL et implémentation de référence"
```

Ne pas copier `03_chargement_campagne_2025-2026.sql` dans le dépôt : il contient des données nominatives.
Le charger à la main dans l'environnement de recette seulement.

Si le système de design de Coélab est réutilisable, copier aussi ses jetons Tailwind dans `resources/css/` dès le départ,
et le dire dans le premier prompt.

## 2. Premier prompt (lot L1 — socle)

Lancer `claude` à la racine, puis :

> Lis `CLAUDE.md`, puis `docs/specs/01_cadrage.md`, `02_acteurs_roles.md`, `05_modele_donnees.md` (§ 5.1, 5.2, 5.7) et `06_architecture.md`.
> Nous commençons le lot L1 (chapitre 9.3). Propose-moi d'abord un plan en étapes courtes, sans coder, pour :
> 1. créer le projet Laravel 12 avec Livewire 3, Pest, Pint, Larastan, Tailwind, et le `docker-compose.yml` du § 6.5
>    (php-fpm, nginx, postgres/postgis, redis, queue worker, scheduler, conteneur python avec pdftotext) ;
> 2. la migration `0001_socle_document02` qui exécute `database/sql/01_*.sql` et `02_*.sql` de façon idempotente ;
> 3. la migration du schéma `app` (§ 5.2) en SQL brut, avec les modèles Eloquent correspondants ;
> 4. l'authentification : Fortify (mot de passe, 2FA e-mail), lien de connexion signé 15 min à usage unique, Socialite Microsoft ;
> 5. rôles spatie/laravel-permission (§ 2.2), `GdsScope`, `ElevageScope`, policies (§ 6.3) ;
> 6. les tests Pest du jeu J4 (§ 9.1) et les cas T-30, T-31, T-32, T-40 (§ 9.2).
> Je validerai le plan avant que tu codes. Une étape à la fois, tests verts à chaque étape.

Valider le plan, puis demander « Fais l'étape 1 », « Fais l'étape 2 », etc. Relire les diffs à chaque étape ;
tout ce qui touche à la sécurité (auth, scopes, policies) mérite une relecture ligne à ligne.

## 3. Prompts types pour la suite

- Lot L2 : « Lis `04_regles_calcul.md` (RC-1, RC-1.4, RC-2) et `03_fonctionnel.md` (M2, M9). Implémente `App\Domain\Interpretation`
  (Classeur, Plausibilite, NiveauGlobal) en PHP pur, avec les tests T-02 et T-03, puis le service d'import et l'écran E19. »
- Lot L5 : « Porte `calculs/focus_troupeau_calculs.py` en PHP dans `App\Domain\Stocks` et `App\Domain\Troupeau`
  (RC-5 à RC-9). Les tests Pest doivent reproduire les 60 vérifications de `calculs/test_calculs.py` ; commence par les tests. »
- Lot L6 : « Même chose pour `App\Domain\Autonomie` et `App\Domain\Approvisionnement` (RC-10 à RC-14) ; projection journalière,
  agrégation mensuelle, dates de rupture, écarts de recalage, leviers, proposition initiale. »
- Revue : « Relis le lot en cours contre les exigences EF-M4-* et dis-moi ce qui manque ou s'écarte des specs, sans corriger. »

## 4. Règles de prudence

- Garder Claude Code sur une branche par lot ; fusionner dans `main` après revue et tests verts.
- Ne jamais lui donner les données nominatives (campagne 2025-2026, SAGEMA) : jeux synthétiques J1, J2, J4 pour le développement.
- Quand il propose une valeur de référence absente des specs, la refuser et l'ajouter d'abord dans `docs/specs/04_regles_calcul.md`
  avec sa source — les specs restent la source de vérité, le code suit.
