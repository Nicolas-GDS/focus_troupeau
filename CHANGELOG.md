# Journal des versions

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) ; versions sémantiques (chapitre 6.5).
Le numéro de version est affiché dans le pied de page de l'application et dans les PDF (ENF-EXP-03).

## [Non publié]

### Ajouté
- Volet « Données AGDS » du dossier de l'élevage (EF-M9-03, § 6.4 « vue SQL (cible) ») : identité de l'éleveur et
  composition du troupeau bovin présent (sexe, tranche d'âge, race ; liste des bovins par numéro national), lues à
  l'ouverture dans les vues en lecture seule de SAGEMA (connexion `sagema`, `SAGEMA_DB_*`), par le numéro EDE et
  bornées au département de l'élevage. Agents GDS seulement (permission `agds.lire`) ; aucune écriture.
- Création d'un élevage (E23, EF-M0-02) : bouton « Pré-remplir depuis AGDS » ; à partir du numéro EDE et du GDS
  choisi, la raison sociale, le code postal, le téléphone et l'e-mail sont repris d'AGDS (cheptel cherché dans le
  département du GDS, EDE déjà suivi refusé, activité cessée signalée). Le technicien relit puis crée.

## [1.0.0-rc.1] — lot L8 (recette et mise en production), candidate à la recette des élevages testeurs

### Ajouté
- Sécurité : en-têtes CSP, HSTS (derrière le proxy TLS), X-Frame-Options, nosniff, Referrer-Policy,
  Permissions-Policy (ENF-SEC-01).
- Exploitation : purges de conservation paramétrables (ENF-RGPD-05), supervision horaire avec alerte aux
  administrateurs et écran « Exploitation » (ENF-EXP-02), sauvegarde chiffrée et restauration de contrôle
  (ENF-SEC-07).
- Jeu J2 ovin (T-13, T-22) ; recherche automatique de marques dans les libellés générés (T-25).
- Audit d'accessibilité automatisé des écrans éleveur (axe-core, `accessibilite:auditer`, T-41).
- Test de charge k6 de 50 utilisateurs (T-42) ; comptes de charge.
- E24 : besoins journaliers, repères des mesures terrain et des profils sanguins, seuils du bilan sanitaire,
  aliments de table.
- Brouillon local chiffré du formulaire de lot de stock (ENF-ACC-03).
- Image de production (`docker/production/`), Compose de production, procédure de déploiement, commande
  `compte:super-administrateur`, guides éleveur, technicien et administrateur, protocole de recette des élevages
  testeurs.

### Modifié
- Autorisations mémorisées le temps d'une requête (historique : 105 → 20 requêtes SQL) ; pool PHP-FPM de 20
  processus.
- Zones défilantes atteignables au clavier (RGAA 7.3).
- nginx de développement : les en-têtes de sécurité des pages sont posés par l'application seule.

## [Non publié] — lot L7 (administration, imports, pilotage)

### Ajouté
- E23 — administration : comptes (création, invitation, rôles et GDS dans le périmètre, rattachements, activation),
  conventions (prolongation, résiliation), GDS, journal d'audit filtrable (`/admin/journal`).
- Élevages (EF-M0-02) : création d'un élevage et de son identité, convention, invitation des éleveurs (`/elevages`) ;
  rappel d'échéance de convention au technicien (EF-M0-06).
- E17 — consentements de l'éleveur (« références », « vétérinaire »), effet immédiat, journalisés.
- Import SAGEMA (T-27) : élevages adhérents et bilan sanitaire, aperçu, rapport, aucune écriture externe.
- Exports : valeurs pour le rationnement (CSV), données de l'élevage (ZIP, portabilité) ; anonymisation sur demande.
- E24 étendu aux paramètres par défaut (datés, nationaux ou par GDS) et aux textes types.
- E25 — indicateurs de pilotage par GDS et campagne, exports CSV des indicateurs et des références agrégées.
- Procédure `docs/recette/L7_recette.md`.

### Modifié
- Tests T-26 : rendus Chromium sérialisés, seuil de 10 s vérifié en exécution séquentielle.

## [Non publié] — lot L6 (autonomie et approvisionnement)

### Ajouté
- Domaine de l'autonomie (RC-10 à RC-14) : projection journalière par lot et par famille, sécurité, ruptures,
  incertitude, leviers et proposition initiale ; tests T-14 à T-16.
- Bilan mémorisé (`plan.bilan`), recalculé par le job `CalculerBilan` après toute saisie des stocks ou du troupeau
  (T-20) ; scénarios appliqués virtuellement (RC-13.4) ; incertitude relative de chaque lot de stock.
- E14 — bilan et projection : trois indicateurs et leur fourchette, repère d'action, tableau mensuel, graphique SVG
  (`x-projection`), ruptures par famille et par lot, phases de la période de référence ; autonomie sur l'accueil.
- Alerte d'autonomie par e-mail (EF-M8-03, T-21), évaluée à chaque recalcul et chaque nuit.
- E15 — approvisionnement : proposition initiale, scénarios et leviers, comparaison de quatre scénarios (T-23),
  scénario retenu avec lots et mouvements d'achat liés (T-24), technicien prévenu.
- E16 — paramètres de l'élevage (`app.parametre_elevage`) : pertes, MS par défaut, densité du vrac, marges, prix
  indicatifs ; référence affichée, écarts listés au technicien.
- PDF du bilan et du scénario (T-26).
- Jeu J1 aligné sur T-14 ; procédure `docs/recette/L6_recette_J1.md`.

### Modifié
- Le bilan compte les achats, ventes et ajustements datés de la période (RC-13.4, EF-M7-05).

## [Non publié] — lot L5 (stocks et troupeau)

### Ajouté
- Schémas `stock` et `plan` (§ 5.4, § 5.5), référentiels version 5 : valeurs par défaut de RC-6 à RC-9 et RC-12,
  coefficients UGB, besoins journaliers (repères de RC-9 et besoins du socle transcrits), seuils des contrôles de ration ;
  tables d'aliments créées vides.
- Domaine des stocks et du troupeau (RC-5 à RC-9) : silos, balles et vrac, besoins par les deux voies, couverture,
  contrôles de la ration ; tests T-10 à T-13.
- E12 — inventaire des stocks sur téléphone : lots calculés à l'enregistrement, aperçu en ligne, MS d'une analyse
  reliée, mouvements, tableau de bord par famille ; reprise d'un bilan du calculateur HTML (T-28).
- E13 — troupeau : lots d'animaux, phases, rations, besoins, apports et couverture, contrôles, affectation, copie d'une
  période.
- Jeu J1 et procédure `docs/recette/L5_recette_J1.md`.

## [Non publié] — lot L4 (positionnement)

### Ajouté
- Populations de référence (RC-3, EF-M3-01 à EF-M3-04) : vues `valo.v_reference_population`, `v_reference_gds`,
  `v_reference_litho`, `v_reference_bigds` (bulletins validés, consentement « références », hors exclusions et
  anomalies, n toujours donné) ; paramètres suivis configurables ; `references.n_min` ; domaine `Positionnement`.
- Graphiques de positionnement en SVG côté serveur (`x-positionnement`, EF-M3-03) dans E04, E03 et la fiche de
  visite figée ; pastilles de position UFL et MAT ; rafraîchissement nocturne du contexte pédo-géologique.
- Historique pluriannuel (RC-4, EF-M1-08, EF-M3-05) : `valo.v_historique_elevage` par lot, courbes `x-historique`
  (MAT, UFL, Cu, Zn), variation sans classe, frise des décisions (E10) ; l'éleveur ne voit que ses lots publiés.
- Jeu J5 (population synthétique de développement), commande `references:bilan`, procédure
  `docs/recette/L4_recette_J0.md` (T-07 à T-09).

## [Non publié] — lot L3 (dossier et publication)

### Ajouté
- Schéma `dossier` (§ 5.3) : visites, eau, mesures terrain, étiquettes, bilan sanitaire, interprétation de visite,
  messages, causes communes, plan d'action, contrôles, note de liaison ; profils sanguins rattachés à la visite.
- Structures des référentiels du dossier (repères des mesures terrain et des profils sanguins, indicateurs et seuils
  du bilan sanitaire, nature « eau » de la grille) ; permissions du lot L3 (§ 2.3).
- Référentiels du dossier (version 4) transcrits de la présentation Forgeneuf, statut [C], « repère à valider » :
  mesures terrain et repères PRITIH par stade, lectures combinées, repères sériques, seuils S1/S2 du bilan
  sanitaire, grille trouble × cause ; paramètres de l'eau (seul le fer a un seuil).
- Domaine du dossier (RC-1.5, EF-M1-04, EF-M1-07, EF-M2-05) : lecture des mesures terrain, lectures combinées,
  profils sanguins, position du bilan sanitaire, croisement trouble × cause, lecture de l'eau, synthèse de visite.
- Saisie d'une visite par le technicien (E03, EF-M1-04 à EF-M1-07, EF-M1-09) : liste des visites de l'élevage,
  étapes « collecter » (compte rendu, bilan sanitaire, événements), « analyser » (prélèvements, eau, étiquettes et
  apports journaliers) et « mesurer » (mesures terrain une ligne à la fois, lectures combinées, profils sanguins) ;
  policies du dossier, mention « repère à valider ».
- Interprétation de visite et publication (E21, EF-M2-04 à EF-M2-07) : éléments à croiser et familles de cause
  proposées, synthèse et niveau retenu, messages hiérarchisés, causes communes, plan d'action et contrôles, visa du
  niveau 3, publication de la visite et de ses objets, nouvelle version (T-06), suivi du plan publié ; files de
  travail des visites et des contrôles échus (E18, EF-M2-08).
- Fiche de visite figée (EF-M2-07, EF-GEN-08, T-26) : HTML autonome et PDF à la charte bi-entité archivés à la
  publication (Browsershot, Chromium), téléchargement par lien signé ; textes types des mentions de limites
  (`ref.texte_type`, EF-M10-03).
- Notifications (EF-M8-01, EF-M8-02, EF-M8-05, T-22) : e-mail à la publication d'une fiche ou d'un résultat, copie au
  technicien, SMS au niveau 3 toujours envoyé ; journal `app.notification`, envoi par job (SMTP Brevo, API SMS
  Brevo, pilote « journal » en développement) ; préférences et numéro de mobile, changement de mot de passe (E17).
- Note de liaison vétérinaire (EF-M2-09, gabarit du document 04) : rédaction et envoi depuis E21 au vétérinaire désigné
  par consentement, PDF archivé, e-mail avec lien, écran du vétérinaire et retour, technicien prévenu.
- Dossier élevage en lecture (E02 à E11, EF-M1-01 à EF-M1-09, EF-M2-06) : accueil de l'éleveur, synthèse de visite
  en six étapes, fourrages et détail d'un lot, matrice minérale, eau, animaux, minéraux et aliments (étiquette
  ajoutée par l'éleveur), bilan sanitaire, historique, plan d'action ; l'éleveur lit la dernière version publiée ;
  composants `x-limites`, `x-message-hierarchise`, `x-valeur-repere` (§ 8.3) ; T-40 sur chaque volet.
- Jeu d'essai J3 (§ 9.1) : `ELV-0001` et les neuf lots du prototype du document 04 ; test et procédure de recette
  du lot (`docs/recette/L3_recette_J3.md` : première fiche de visite publiée, T-06, T-22, T-26, T-40).

### Dépendances
- `spatie/browsershot` (prévue au § 6.1) ; dans l'image applicative : `chromium`, `nodejs`, `npm` et `puppeteer`
  23.11.1 (global, sans téléchargement de navigateur).

## [Non publié] — lot L2 (import et interprétation)

### Ajouté
- Extension du schéma (§ 5.3) : versions des référentiels, plages de plausibilité, référentiel minéraux × troubles
  (chapitre 11), suivi des dépôts de bulletins, visa et refus de visa.
- Domaine d'interprétation : classement par la grille (RC-1), ratios, plausibilité (RC-2), niveau proposé et
  compatibilité avec les troubles sanitaires (RC-1.4).
- Import des bulletins LANO (EF-M9-01) : extraction par `scripts/parse_lano.py`, rapprochement, contrôle, écran E19.
- Interprétation d'un bulletin et circuit de visa (EF-M2-02 à EF-M2-04) : écran E20.
- Référentiels versionnés (EF-M10-02) : écran E24 (seuils, plages de plausibilité).
- Files de travail du technicien et du vétérinaire conseil (EF-M2-08) : écran E18.
- Outillage de recette J0 : `bulletins:importer`, `bulletins:controler-charges`, `bulletins:bilan`.

## [Non publié] — lot L1 (socle)

### Ajouté
- Projet Laravel 12, environnement Docker (chapitre 6.5), intégration continue.
- Migration du socle du document 02 (schémas `ref`, `prive`, `fourrage`, `sig`, `valo`).
- Schéma `app` : GDS, comptes, rattachements, conventions, consentements, paramètres, notifications, journal.
- Authentification : mot de passe, code par e-mail, lien de connexion, activation, Entra ID (EF-M0-03 à EF-M0-05).
- Rôles, cloisonnement par GDS et par élevage, policies (chapitre 6.3).
- Mise en page bi-entité, navigation, sélecteur d'élevage, notice RGPD (§ 8.1, § 8.2, EF-M0-08, ENF-RGPD-02).
- Kit graphique Focus Troupeau v1 : logotypes (dont variantes co-brandées GDS 24 / GDS 47), signe, icônes, puces, favicons, polices auto-hébergées, jetons de couleur.
- Logos GDS 24, GDS 47 et GDS France.
- Jeu d'essai J4 (seeder) et tests de cloisonnement T-40 sur chaque route et chaque rôle, avec registre obligatoire des routes.
