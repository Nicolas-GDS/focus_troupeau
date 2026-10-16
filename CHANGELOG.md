# Journal des versions

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) ; versions sémantiques (chapitre 6.5).
Le numéro de version est affiché dans le pied de page de l'application et dans les PDF (ENF-EXP-03).

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
