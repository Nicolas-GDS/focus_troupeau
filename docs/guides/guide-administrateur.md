# Focus Troupeau — guide de l'administrateur

L'administrateur gère son ou ses GDS ; le super-administrateur gère toute l'instance.

## Comptes et GDS (E23)

- **Utilisateurs** : créer un compte (un éleveur ou un vétérinaire reçoit une invitation ; un agent se connecte par
  Entra ID), attribuer rôles et GDS, rattacher un éleveur à un élevage, désactiver ou réactiver, renvoyer une
  invitation. Un compte n'est jamais supprimé. Toute modification est journalisée.
- **Conventions** : liste par statut et échéance, prolongation, **résiliation** (motif obligatoire ; l'éleveur passe
  en lecture seule).
- **GDS** : coordonnées ; le super-administrateur crée un GDS et règle sa couleur de charte.
- **Élevages** : comme le technicien ; l'administrateur peut aussi **anonymiser** un élevage sur demande RGPD
  (irréversible : exporter d'abord les données si l'éleveur les demande).

## Référentiels (E24)

Chaque modification crée une nouvelle version des référentiels et porte la **décision du comité technique** ; les
fiches publiées gardent la version de leur grille.

- Grille de seuils, plages de plausibilité, besoins journaliers, repères des mesures terrain et des profils
  sanguins, seuils du bilan sanitaire : créer, modifier, retirer une ligne, indiquer la source et le statut ; cocher
  « validé » quand le comité a validé un repère (la mention « repère à valider » disparaît alors).
- Aliments de table : en attendant les tables INRAE (licence).
- **Paramètres par défaut** : valeur nationale (super-administrateur) ou propre au GDS ; datés, historique conservé
  (pertes, prix, marges, durées de conservation, seuils d'alerte et de supervision).
- **Textes types** : objets et corps des e-mails et SMS, mentions de limites ; garder les variables `{…}`.

## Journal et exploitation

- **Journal** : connexions, publications, visas, consentements, référentiels, exports, rôles ; filtres par action,
  compte, élevage, période.
- **Exploitation** : état de la base, de la file d'attente, du disque, de la dernière sauvegarde, du rafraîchissement
  des références ; purges de conservation ; dossiers arrivés au terme de leur durée de conservation (à examiner).
  Une alerte e-mail arrive une fois par jour si un point est à vérifier.
- **Pilotage** : indicateurs et exports pour le bilan de campagne et la révision de la grille.

Procédures techniques : `docs/exploitation/` (déploiement, sauvegardes).
