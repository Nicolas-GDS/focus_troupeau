# Recette du lot L7 — administration, imports, pilotage

Procédure pour l'environnement de **recette** ou de développement. Livrable de revue visé (§ 9.3) : « administration
complète ». Cas couverts : T-27 (import SAGEMA), T-40 (cloisonnement des routes ajoutées), T-26 (rendus Chromium),
exigences EF-M0-02, EF-M0-06, EF-M0-07, EF-M9-03 à EF-M9-05, EF-M10-01 à EF-M10-04. Les tests
`tests/Feature/Administration/*`, `tests/Feature/Import/ImportSagemaTest.php`,
`tests/Feature/Referentiels/ParametresTextesTest.php` et `tests/Feature/Recette/RecetteL7Test.php` jouent ces cas ;
ils doivent être verts avant la recette. Décisions du lot : § 3.13, « Précisions du lot L7 ».

## 1. Préparer

1. `php artisan migrate --force` (permissions du lot, textes types du rappel de convention).
2. Comptes du jeu J4 (`migrate --seed`) : `admin.24@j4.test`, `admin.global@j4.test`, `technicien.24@j4.test`,
   `eleveur.24a@j4.test` (mot de passe `J4_MOT_DE_PASSE`). Mailpit capte les e-mails (http://localhost:8026).
3. Fichiers d'essai SAGEMA : `tests/Fixtures/sagema/elevages.csv` et `indicateurs.csv`.

## 2. Import SAGEMA (technicien, T-27)

1. Menu « Import SAGEMA » → fichier des élevages → « Voir ce qui sera importé » : 2 créés, 4 rejetés (EDE en double,
   EDE sans « FR », raison sociale absente, courriel invalide), rien n'est encore écrit.
2. « Importer » : rapport identique ; les deux élevages apparaissent dans « Élevages » avec un code `ELV-nnnn`.
3. Réimporter le même fichier : « Inchangé » pour les deux élevages ; modifier un effectif dans le fichier : « Mis à
   jour (effectif_bovins) », le contact déjà saisi n'est pas effacé par une colonne vide.
4. Fichier des indicateurs : 1 créé (non-délivrances : 6 sur 80, soit 7,5 %), IVV créé, rejets motivés (indicateur et
   campagne inconnus, élevage inconnu, cas supérieurs au dénominateur). Une valeur saisie à la main n'est pas écrasée.
5. Avec le compte de l'administrateur : pas d'accès à l'import (réservé au technicien).

## 3. Élevages, conventions, comptes (technicien, EF-M0-02)

1. « Élevages » → « Nouvel élevage » (raison sociale, EDE, code postal) : fiche ouverte.
2. Inviter un éleveur : refusé tant que le consentement au traitement des données n'est pas coché ; cocher,
   enregistrer, inviter : e-mail d'invitation dans Mailpit.
3. Créer la convention (un an) ; une seconde convention active est refusée ; prolonger.
4. Rappel d'échéance : passer la date de fin à J+20, lancer `php artisan schedule:test --name=rappel-conventions`
   (ou attendre la nuit) : un e-mail au technicien, un seul.

## 4. Administration (administrateur, E23)

1. « Utilisateurs » : créer un compte éleveur (invitation envoyée), le rattacher à un élevage du GDS ; un élevage
   d'un autre GDS est refusé ; un rôle de super-administrateur et un GDS hors périmètre ne sont pas proposés.
2. Désactiver puis réactiver un compte ; renvoyer une invitation. Impossible de se retirer son propre rôle
   d'administrateur ou de désactiver son propre compte.
3. « Conventions » : filtrer les conventions actives, échéances proches signalées ; résilier avec un motif.
4. « GDS » : modifier le contact ; avec `admin.global@j4.test`, créer un GDS (numéro, code de 2 caractères,
   couleur).
5. « Journal » : les actions des étapes précédentes (import, création, invitation, convention, consentement, export)
   apparaissent, filtrables par action, compte, élevage et période ; rien d'un autre GDS.
6. « Référentiels » → « Paramètres par défaut » : modifier une valeur (décision obligatoire) ; l'historique daté
   montre l'ancienne valeur close la veille ; un administrateur de GDS ne fixe que la valeur de son GDS. « Textes
   types » : modifier un objet d'e-mail ; une variable `{date}` retirée est refusée.

## 5. Éleveur : consentements et données (E17, EF-M9-05)

1. « Mes consentements » : accepter « références », désigner un vétérinaire (il lit alors le dossier), révoquer (il
   perd l'accès immédiatement) ; historique des consentements révoqués.
2. « Télécharger les données de l'élevage (ZIP) » : CSV par famille de données, bulletins, fiches de visite publiées,
   bilan d'autonomie ; seuls les résultats publiés y figurent.
3. Dossier → Fourrages → « valeurs pour le rationnement (CSV) » : une ligne par lot publié, virgule décimale, unités
   des bulletins en en-tête (format d'INRAtion V5 / Rumin'Al à confirmer, point ouvert).

## 6. Anonymisation (administrateur)

Fiche de l'élevage → « Anonymisation (demande RGPD) » : motif obligatoire, confirmation ; identité effacée (raison
sociale = code pseudonyme), comptes éleveurs anonymisés et désactivés, convention résiliée ; stocks, analyses et
interprétations conservés sous le code pseudonyme ; entrée « anonymisation elevage » au journal. Irréversible.

## 7. Pilotage (E25)

« Pilotage » (administrateur, technicien) : par GDS et campagne, activité, délai médian prélèvement → fiche publiée,
part des bulletins en anomalie, part des alertes automatiques retenues, orientations vétérinaires, couverture des
références (types avec n ≥ 5). Exports CSV des indicateurs (GDS visibles) et des références agrégées (quartiles, sans
identité).

## 8. Points connus au lot L7

- Format d'import d'INRAtion V5 et de Rumin'Al non documenté : CSV proposé, à confirmer sur un fichier d'exemple.
- Document 03, § 6.5 (indicateurs de pilotage) non versé : définitions proposées au § 3.13.
- Édition des besoins, repères des mesures terrain, seuils du bilan sanitaire et aliments de table : lot L8, après
  validation de ces référentiels par le comité technique.
- Lecture planifiée d'un partage réseau pour SAGEMA : non faite (adaptateur prévu, § 6.4).
- `sig.commune` vide en développement : la commune d'un élevage importé n'est rapprochée qu'une fois le socle
  géographique chargé (script 03).
- Seuil de 10 s de T-26 : vérifié en exécution séquentielle (`php artisan test --filter=Chromium`) ; en parallèle,
  la charge des autres processus fausse la mesure.
