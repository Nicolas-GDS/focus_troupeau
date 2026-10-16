# Recette du lot L3 — dossier et publication (jeu J3, ELV-0001)

Procédure pour l'environnement de **recette**. Livrable de revue visé (§ 9.3) : « première fiche de visite publiée
(J3) ». Cas de recette couverts : T-06, T-22, T-26, T-40 (§ 9.2), plus la lecture du dossier par l'éleveur (E02 à E11).
Le test automatisé `tests/Feature/Recette/RecetteJ3Test.php` rejoue ce circuit : il doit être vert avant la recette.

J3 reprend les neuf lots **réels pseudonymisés** de l'élevage ELV-0001 (document 04, prototype de fiche, figures 2 et
3) : ne pas diffuser les fiches produites hors de l'équipe de recette.

## 1. Préparer l'environnement

1. Base à la dernière version du code : `php artisan migrate --force`.
2. Charger J3 : `php artisan db:seed --class=JeuJ3Seeder --force`. Le seeder charge aussi les comptes de J4 (le
   technicien et le vétérinaire conseil du GDS 24 servent à la recette) ; il est rejouable et refusé en production.
   Mot de passe des comptes : `J4_MOT_DE_PASSE`.
3. Notifications réelles (EF-M8-05) : dans `.env`, identifiants SMTP Brevo (`MAIL_HOST=smtp-relay.brevo.com`,
   `MAIL_PORT=587`, `MAIL_USERNAME`, `MAIL_PASSWORD`), `BREVO_CLE_API`, `SMS_PILOTE=brevo`. Donner au compte
   `eleveur.elv0001@j3.test` un mobile de l'équipe de recette (écran E17, « Mon compte ») et, pour recevoir l'e-mail,
   une adresse de l'équipe (`php artisan tinker`, l'administration des comptes arrivant au lot L7) : sinon l'e-mail
   part vers le domaine réservé `.test`. Sans Brevo, lire les e-mails dans Mailpit.
4. Files : `php artisan queue:restart` ; un worker doit tourner (production du PDF, envois).
5. Chromium présent dans l'image applicative (`config('fiches.chrome')`), indispensable au PDF.

| Compte | Rôle |
|---|---|
| `technicien.24@j4.test` | technicien, GDS 24 |
| `veterinaire.conseil.24@j4.test` | vétérinaire conseil, GDS 24 (visa, T-22) |
| `eleveur.elv0001@j3.test` | éleveur d'ELV-0001 |

## 2. Contrôler le chargement

Avec le technicien : « Mon travail » (E18) doit lister **9 bulletins contrôlés à interpréter** (J3-01 à J3-09) ;
« Visites » montre la visite du 29/07/2026, en cours, avec les neuf prélèvements rattachés (campagne 2025-2026).

## 3. Interpréter les neuf lots (E20)

Pour chaque lot : relire les alertes automatiques, rédiger la synthèse, saisir l'**utilisation conseillée** du
prototype, retenir le niveau 2, valider. Alertes attendues de la grille en vigueur (niveau proposé 2 pour tous) :

| Lot | Nom | Alertes automatiques | Utilisation conseillée (document 04) |
|---|---|---|---|
| J3-01 | Ensilage Sorgho BMR Fermente | Cu carence probable (2) ; MAT faible, Mg bas, Na bas, Zn insuffisant (1) | Bovins à besoins modérés ; azote à compléter |
| J3-02 | Foin Dactyle / Luzerne | Cu carence probable, Zn très bas (2) ; Mg bas (1) | Vaches en lactation, brebis fin de gestation ; apporte l'azote soluble qui manque aux foins |
| J3-03 | Foin de Prairie Naturelle | Cu, Zn (2) ; MAT faible, Mg bas, Na bas, BPR déficit d'azote soluble (1) | Génisses, vaches taries, brebis à l'entretien ; à associer à une légumineuse |
| J3-04 | Enrubannage Dactyle / Luzerne | Cu, Zn (2) ; Mg bas (1) | comme J3-02 |
| J3-05 | Enrubannage de Ray Grass | Cu, Zn (2) ; Mg bas, Na bas, Fe contamination possible (1) | Vaches allaitantes en lactation, génisses ; vérifier la conservation |
| J3-06 | Enrubannage Dactyle Luzerne | Cu, Zn (2) ; MS trop sec, Mg bas, BPR excès d'azote (1) | comme J3-02 |
| J3-07 | Foin de Prairie Permanente | Cu, Zn (2) ; MAT faible, Mg bas, UFL faible, BPR déficit (1) | comme J3-03 |
| J3-08 | Foin de Luzerne | Cu, Zn (2) ; MAT faible pour une luzerne, Mg bas (1) | comme J3-02 |
| J3-09 | Luzerne et Dactyle 2025 | Cu, Zn (2) ; Mg bas (1) | comme J3-02 |

Constats du prototype à retrouver : Cu sous 7 mg/kg MS sur 9 lots, Zn sous 30 sur 8, BPR sous −30 sur 2, fer au-dessus
de 300 sur 1 (Ray Grass), aucun lot à 100 g de cendres ou plus. Le magnésium sort « bas » (seuil 2,0 g/kg MS) sur les
neuf lots : le prototype colorait ces cases en vigilance sans en faire un message — à commenter avec le vétérinaire
conseil.

## 4. Interpréter la visite et publier (E21)

Depuis la visite, « Interprétation de la visite » → créer le brouillon, puis :

- **Titre** : « Vos fourrages 2025-2026 : ce qu'ils apportent, ce qu'ils ne couvrent pas » ; niveau retenu 2.
- **Messages** (cinq, par gravité) : reprendre `JeuJ3Seeder::MESSAGES` (cuivre et zinc ; azote des foins de prairie ;
  traces de terre ; cendres ; ce que l'analyse ne dit pas).
- **Plan d'action** (cinq points, `JeuJ3Seeder::ACTIONS`) : associer foin de prairie et luzerne (éleveur) ;
  calculer la complémentation minérale sur les teneurs mesurées (technicien) ; profil métabolique ciblé si troubles
  (vétérinaire) ; limiter la terre à la récolte (éleveur) ; analyser le foin de 1re coupe dès la mise en stock
  (technicien, campagne suivante). Ajouter un contrôle à une action de l'éleveur pour vérifier l'étape « suivre ».
- Valider, puis **publier**.

## 5. Vérifier la fiche et les notifications

| Cas | Attendu | Où le lire |
|---|---|---|
| T-26 | PDF produit en moins de 10 s ; charte GDS 24 (logo, marque co-brandée, réseau GDS France) ; neuf lots, matrice minérale ; limites : Se, I, Co non dosés, « ne remplace ni un calcul de ration ni un diagnostic vétérinaire », « repère à valider » ; pied de page : versions de l'application, de la grille et de la fiche, date, pagination | Lien « Télécharger la fiche (PDF) » dans E21 et en E03 |
| EF-GEN-03 | Chaque état lisible imprimé en noir et blanc (pictogramme et libellé) | Impression du PDF |
| EF-M8-01 | E-mail à l'éleveur (objet, trois lignes, lien) et copie au technicien ; aucun SMS au niveau 2 | Boîte de recette ou Mailpit ; `app.notification` |

## 6. Lire le dossier avec le compte de l'éleveur (E02 à E11)

- **Accueil (E02)** : dernière visite, cinq messages par gravité, actions de l'éleveur (2), contrôle à venir.
- **Synthèse (E03)** : six étapes ; chaque valeur avec sa classe, son repère et sa source dépliable.
- **Volets** : fourrages et détail d'un lot (bulletin PDF original seulement si un fichier est rattaché : J3 n'en a
  pas), matrice de la campagne 2025-2026, eau et animaux vides (J3 n'en contient pas), bilan sanitaire, historique.
- **Plan d'action (E11)** : marquer une action « faite » ; elle disparaît de l'accueil.
- **Minéraux et aliments (E08)** : ajouter une étiquette ; le technicien la retrouve à valider dans la saisie de la
  visite suivante.

## 7. Nouvelle version (T-06) et niveau 3 (T-22)

- **T-06** : dans E21, « Nouvelle version », corriger la synthèse, valider, publier. Attendu : version 2 visible de
  l'éleveur, version 1 archivée, PDF v1 toujours téléchargeable par le technicien ; pendant la préparation de la
  version 2, l'éleveur continue de lire la version 1.
- **T-22** (le § 9.2 prévoit J2, non construit au lot L3) : sur une nouvelle version, retenir le niveau 3 ; vérifier
  que le technicien ne peut pas publier avant le visa (T-05) ; viser avec le vétérinaire conseil ; publier. Attendu :
  SMS à l'éleveur et au technicien (objet, consigne = premier message de gravité 3, contact), journalisés. Republier
  ensuite une version au niveau 2.

## 8. Cloisonnement (T-40)

`php artisan test --filter=RoutesTest` : chaque route, chaque profil J4, chaque volet du dossier sur chaque élevage
J4 (200, 403 ou 404, aucun élevage hors périmètre affiché). À la main : `eleveur.24a@j4.test` ne voit pas ELV-0001 ;
`technicien.47@j4.test` reçoit 404 sur `/elevages/{ELV-0001}/fourrages`.

## 9. Points connus au lot L3

- Position dans la campagne (RC-3) et courbes pluriannuelles (RC-4) : lot L4 ; variante « ration » de la matrice et
  comparaison au besoin (RC-9) : lot L5 ; autonomie de l'accueil : lot L6.
- J3 ne contient ni Ca, ni P, ni S, ni Cl (absents des figures du prototype) : ratios Ca/P, K/(Ca+Mg) et BACA calculé
  non disponibles ; le BACA du laboratoire est repris tel quel.
- Une étiquette ajoutée hors visite et validée n'est publiée par aucune visite (point ouvert, § 10.4).
- Restent à faire : webhook des statuts Brevo (§ 6.6), rappels EF-M8-04, note de liaison depuis un bulletin seul.
- Les repères des mesures terrain, des profils sanguins, du bilan sanitaire et de l'eau sont au statut [C] « repère à
  valider » (points ouverts 2 à 4, § 9.4).
