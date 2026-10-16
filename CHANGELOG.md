# Journal des versions

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) ; versions sémantiques (chapitre 6.5).
Le numéro de version est affiché dans le pied de page de l'application et dans les PDF (ENF-EXP-03).

## [Non publié] — lot L3 (dossier et publication)

### Ajouté
- Schéma `dossier` (§ 5.3) : visites, eau, mesures terrain, étiquettes, bilan sanitaire, interprétation de visite,
  messages, causes communes, plan d'action, contrôles, note de liaison ; profils sanguins rattachés à la visite.
- Structures des référentiels du dossier (repères des mesures terrain et des profils sanguins, indicateurs et seuils
  du bilan sanitaire, nature « eau » de la grille) ; permissions du lot L3 (§ 2.3).

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
