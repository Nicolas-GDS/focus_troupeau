# 6. Architecture technique

## 6.1 Pile retenue

| Couche | Choix | Motif |
|---|---|---|
| Langage et cadre | PHP 8.3, Laravel 12 | Cohérence avec Coélab (D16) ; écosystème mature pour l'authentification, les files d'attente, les notifications |
| Interface | Blade + Livewire 3 (composants réactifs côté serveur), Alpine.js pour les interactions locales, Tailwind CSS avec les jetons du système de design de Coélab adaptés à la charte bi-entité | Recalcul immédiat des bilans sans API séparée ; même langage de composants que Coélab |
| Base de données | PostgreSQL 16 + PostGIS 3.4 | Socle existant (document 02) ; quantiles, EXCLUDE, GIST, jsonb |
| Cache, files, sessions | Redis | Jobs d'import, calculs longs, notifications, rafraîchissement des vues |
| Authentification | Laravel Fortify (mot de passe, 2FA par e-mail), lien de connexion signé (`URL::temporarySignedRoute`, 15 min), Laravel Socialite fournisseur Microsoft (Entra ID, OpenID Connect) | D14 |
| Autorisations | `spatie/laravel-permission` + policies Laravel + scopes globaux de cloisonnement | Chapitre 2 |
| PDF | Browsershot (Chromium headless) rendant les vues Blade de restitution à la charte ; même chaîne que les fiches HTML → PDF existantes | EF-GEN-08 |
| Extraction des bulletins LANO | Script Python `scripts/parse_lano.py` existant (pdftotext), appelé par un job via `Symfony\Process` en mode `--json` (un bulletin, sortie JSON ingérée par PHP ; le mode `--lot` historique produit un CSV) | EF-M9-01 ; réécriture en PHP non justifiée tant que le format LANO n'est pas conventionné ; JSON plutôt que CSV : censure et unités portées sans échappement (décision du lot L2) |
| Notifications | Canal Laravel `mail` via Brevo SMTP/API et canal `sms` via l'API Brevo ; journalisation dans `app.notification` | EF-M8 |
| Graphiques | Chart.js (lignes, bandes Q1-Q3, projections) rendu côté client, données fournies par Livewire ; export SVG pour le PDF | Chapitre 8 |
| Tests | Pest (PHP), jeux de données de référence partagés avec `calculs/` (Python) | Chapitre 9 |
| Qualité | Pint (style), Larastan niveau 6, GitHub Actions ou équivalent local | — |

## 6.2 Organisation du code

```
app/
  Domain/                      # logique métier pure, testée unitairement, sans Eloquent
    Interpretation/            # Classeur (RC-1), Plausibilite (RC-2), Positionnement (RC-3), NiveauGlobal
    Stocks/                    # VolumeSilo, Densite, StockUtile, Pertes (RC-5..7), Incertitude (RC-14)
    Troupeau/                  # BesoinUgb, Ration, Apports, Couverture (RC-8, RC-9)
    Autonomie/                 # Projection (RC-11), Securite (RC-12), Periode (RC-10)
    Approvisionnement/         # Proposition, Leviers, EvaluationScenario (RC-13)
    Referentiels/              # accès en lecture aux paramètres par défaut et aux référentiels versionnés (interface + implémentation BDD)
  Models/                      # Eloquent, par schéma : App\Models\App\User, App\Models\Stock\LotStock…
  Livewire/                    # composants d'écran (E01..E25), un dossier par module
  Services/                    # orchestration : ImportBulletinService, PublicationService, NotificationService, SagemaImportService, FichePdfService
  Jobs/                        # ExtraireBulletinLano, CalculerBilan, RafraichirReferences, EnvoyerNotification, ClotureConventions
  Policies/                    # une policy par modèle exposé
  Scopes/                      # GdsScope, ElevageScope
database/
  migrations/                  # 0001 socle (scripts sql/), puis app, dossier, stock, plan, valo
  seeders/ref/*.csv            # référentiels versionnés
resources/views/               # Blade : layouts bi-entité, composants de restitution, gabarits PDF
calculs/                       # implémentation de référence Python + jeux d'essai (hors runtime)
```

Règle : aucune règle de calcul dans un composant Livewire ou un modèle ; les composants appellent des services, qui appellent le domaine avec des DTO. Les constantes viennent toujours de `Referentiels` (lecture de `app.parametre_defaut` et des tables `ref.*`, avec cache).

## 6.3 Cloisonnement multi-GDS et par élevage

- `GdsScope` : scope global sur tous les modèles portant `gds_id` (élevage, convention, utilisateur rattaché) ; il restreint aux GDS de l'utilisateur courant (`app.user_gds`) ; le super-administrateur le désactive explicitement.
- `ElevageScope` : pour un utilisateur de rôle `eleveur` ou `veterinaire`, scope global sur tous les modèles portant `elevage_id`, restreint aux élevages de `app.elevage_acces` (avec, pour le vétérinaire, un consentement non révoqué).
- Les policies vérifient en plus le **statut** (un éleveur ne lit que les objets `publie`) et la **convention** (écriture dans `stock.*` et `plan.*` seulement si une convention `active` existe).
- Les requêtes de référence (RC-3) s'exécutent hors scope, dans des vues qui ne contiennent aucune donnée nominative ni identifiant d'élevage en clair (seulement `code_pseudo`).
- Tests obligatoires : un jeu de tests Pest vérifie, pour chaque route, qu'un utilisateur du GDS 47 ne peut atteindre aucun objet du GDS 24, et qu'un éleveur ne peut atteindre aucun objet d'un autre élevage (chapitre 9, T-40).

## 6.4 Flux principaux

**Import d'un bulletin.** Dépôt du PDF (E19) → stockage sur disque privé (`storage/app/bulletins/{gds}/{elevage}/`) avec SHA-256 → job `ExtraireBulletinLano` (Python, CSV) → `ImportBulletinService` : rapprochement (`num_echantillon_labo`, `num_client_labo`), création de `fourrage.analyse` et `fourrage.resultat`, calcul des ratios, `Plausibilite` (RC-2) → statut `controle` ou `anomalie` → génération des alertes (`Classeur`) en mémoire, affichées dans E20 et persistées à la création de l'interprétation.

**Publication d'une visite.** `PublicationService` : vérifie le statut de chaque objet (interprétations `valide` ou `visee` selon le niveau, mesures et analyses `validee`), fige la version de la grille (`ref.version_referentiel` courante), rend la fiche HTML (Blade) et le PDF (Browsershot), stocke les deux, passe les objets en `publie`, écrit dans `app.journal`, dispatche `EnvoyerNotification` (e-mail ; SMS si niveau 3), déclenche `RafraichirReferences`.

**Recalcul du bilan.** Tout enregistrement dans `stock.*` ou `plan.*` dispatche `CalculerBilan(inventaire)` ; le job charge l'inventaire (lots, mouvements, phases, rations), les référentiels, appelle `Autonomie\Projection` et `Troupeau\Couverture`, écrit `plan.bilan` et compare la date de rupture au seuil d'alerte (EF-M8-03). Les composants Livewire affichent le bilan en lecture ; pour la réactivité de saisie (E12, E13), le calcul par lot (RC-5, RC-7, RC-9 d'une ration) est fait en ligne dans le composant, le bilan complet en job (sous une seconde pour un élevage courant ; le pas journalier sur 365 jours × 20 lots × 10 composants reste trivial).

**Import SAGEMA.** Fichier CSV déposé par le technicien (ou lu depuis un partage réseau par tâche planifiée) : colonnes `num_ede;siret;raison_sociale;contact;adresse;cp;commune;telephone;courriel;especes;effectif_bovins;effectif_ovins;effectif_caprins;adherent` pour les élevages, et `num_ede;campagne;indicateur;nb_cas;denominateur;valeur` pour le bilan sanitaire. Rapprochement par EDE ; création ou mise à jour de `fourrage.elevage` + `prive.elevage_identite` ; rapport d'import. Aucune écriture vers SAGEMA ; l'évolution vers une lecture directe (vue SQL exposée par SAGEMA après la migration SharePoint) ne change que l'adaptateur.

## 6.5 Environnements et déploiement

| Environnement | Hébergement | Caractéristiques |
|---|---|---|
| Développement | Machine virtuelle locale sur le poste du chargé de mission (D17) | Docker Compose : `php-fpm`, `nginx`, `postgres` (image PostGIS), `redis`, `queue-worker`, `scheduler`, `python` (pdftotext) ; données de test pseudonymisées ; `.env` local |
| Recette | Même image, base de recette avec la campagne 2025-2026 chargée | Accès technicien et éleveurs testeurs (3 à 5 élevages) |
| Production | Hébergeur français mutualisé (OVHcloud, Scaleway ou équivalent ; données en France ; DPA signé) | VPS ou conteneurs managés + PostgreSQL managé avec PostGIS ; sauvegardes quotidiennes chiffrées, rétention 30 jours ; TLS ; pare-feu ; journaux centralisés |

Déploiement par image Docker versionnée (tag = version sémantique), migrations exécutées au déploiement, bascule bleu/vert ou maintenance courte annoncée. Secrets (Brevo, Entra, base) hors dépôt, dans l'environnement. Le nom de domaine est à choisir (sous-domaine de `gds24.fr` ou domaine propre « focus-troupeau ») ; le certificat est géré par l'hébergeur ou Let's Encrypt.

## 6.6 Interfaces externes

| Système | Sens | Mode | Données |
|---|---|---|---|
| LANO (laboratoire) | entrant | PDF déposés (V1) ; export structuré conventionné (cible) | Bulletins d'analyses de fourrages |
| LDAR 24 et autres laboratoires | entrant | Saisie manuelle (V1) | Profils sanguins, eau |
| SAGEMA | entrant | CSV (V1), vue SQL (cible) | Élevages adhérents, bilan sanitaire |
| Entra ID (tenant GDS) | entrant | OpenID Connect | Identité des agents ; groupes → rôles |
| Brevo | sortant | API HTTPS | E-mails et SMS transactionnels ; statuts d'envoi par webhook |
| INRAtion / Rumin'Al | sortant | CSV/Excel | Valeurs des lots analysés (EF-M9-04) |
| Calculateur HTML existant | entrant | JSON `gds-autonomie-fourragere-v1` | Reprise des bilans déjà réalisés (EF-M9-06) |
| Couches `sig` (BRGM, RRP, RPG, BAN) | interne | Chargées par ogr2ogr (document 02) | Positionnement par région pédo-géologique |

## 6.7 Mobile et hors ligne

La V1 est un site responsive : Livewire exige la connexion. La saisie au silo se fait sur téléphone avec le réseau mobile ; une perte de réseau en cours de saisie conserve le brouillon côté navigateur (Alpine + `localStorage`, chiffré, purgé à l'envoi). Le mode hors ligne complet (V2) demandera un formulaire autonome en JavaScript (Alpine + IndexedDB) avec un point de synchronisation `POST /api/sync/inventaire` idempotent et une résolution de conflit « dernière saisie gagne, écarts journalisés » ; cette architecture est réservée dans le code (les formulaires de stock exposent déjà un schéma JSON d'échange identique à celui du calculateur HTML).

## 6.8 Journalisation et supervision

Journaux applicatifs (Laravel log, niveau `info` en production) ; journal d'audit métier `app.journal` ; file d'attente surveillée (Horizon) ; alerte technicien si un job d'import échoue ; tableau de bord d'exploitation minimal (jobs en échec, dernière sauvegarde, dernier rafraîchissement des références).
