# Journal des versions

Format inspiré de [Keep a Changelog](https://keepachangelog.com/fr/1.1.0/) ; versions sémantiques (chapitre 6.5).
Le numéro de version est affiché dans le pied de page de l'application et dans les PDF (ENF-EXP-03).

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
