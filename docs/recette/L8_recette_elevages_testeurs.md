# Recette avec les élevages testeurs (lot L8, § 9.3)

Objectif : vérifier, avec 3 à 5 élevages réels et leurs techniciens, que l'application rend le service attendu avant
l'ouverture à tous les élevages conventionnés. La recette se fait sur l'environnement de recette (même image que la
production, campagne 2025-2026 chargée).

## 1. Choix des élevages

| Critère | Pourquoi |
|---|---|
| Au moins un élevage du GDS 24 et un du GDS 47 | cloisonnement et charte des deux entités |
| Au moins un bovin allaitant, un bovin laitier, un ovin ou caprin | besoins, repères et alertes par espèce (cuivre ovin) |
| Au moins un élevage avec un vétérinaire désigné | consentement, note de liaison, lecture du vétérinaire |
| Au moins un élevage qui saisit lui-même ses stocks au téléphone | saisie mobile, brouillon local, couverture réseau |
| Éleveurs volontaires, prévenus du caractère d'essai | les données saisies sont réelles et conservées |

Pour chaque élevage : convention de recette, consentement au traitement des données recueilli (§ 2.5), compte créé
par le technicien et invitation envoyée.

## 2. Scénarios (sur quatre à six semaines)

| N° | Qui | Scénario | Écrans | Attendu |
|---|---|---|---|---|
| R1 | Technicien | Créer l'élevage (ou l'importer de SAGEMA), convention, inviter l'éleveur | Élevages, Import SAGEMA | Invitation reçue, compte activé |
| R2 | Éleveur | Activer son compte, lire la notice RGPD, régler ses consentements | E01, E17 | Consentements journalisés |
| R3 | Technicien | Visite : prélèvements et mesures au téléphone ; import des bulletins LANO | E21, E19 | Bulletins rattachés, contrôles RC-2 |
| R4 | Technicien, vétérinaire conseil | Interprétation, visa si niveau 3, publication | E20, E21 | Fiche PDF à la charte, e-mail (et SMS au niveau 3) |
| R5 | Éleveur | Lire la fiche, le positionnement, l'historique ; marquer une action faite | E02 à E11 | Compréhension sans aide (questionnaire) |
| R6 | Éleveur (avec le technicien la première fois) | Inventaire des stocks au silo, au téléphone | E12 | Stock utile cohérent avec l'estimation du technicien (écart noté) |
| R7 | Éleveur, technicien | Lots d'animaux et rations, bilan d'autonomie | E13, E14 | Date de rupture jugée plausible par le technicien |
| R8 | Éleveur, technicien | Proposition, scénarios, retenue d'un scénario | E15, E16 | Achats inscrits ; technicien prévenu |
| R9 | Vétérinaire désigné | Lire le dossier ; recevoir et répondre à une note de liaison | E03 à E11, note de liaison | Accès limité à l'élevage consenti |
| R10 | Administrateur | Journal, pilotage, exploitation, export RGPD d'un élevage testeur | E23, E25 | Traces complètes ; archive lisible |

## 3. Fiche de retour (une par scénario et par personne)

- Date, élevage (code pseudonyme), rôle, appareil (téléphone, ordinateur), réseau (4G, Wi-Fi, faible).
- Le scénario a-t-il abouti ? Sinon, à quelle étape et avec quel message ?
- Temps passé ; ce qui a été difficile à comprendre ; mots à changer (langage éleveur, ENF-ACC-04).
- Gravité : **bloquant** (le service n'est pas rendu), **majeur** (contournement possible), **mineur** (confort).
- Copie d'écran sans donnée nominative.

Les retours sont saisis dans le suivi d'anomalies du projet ; chaque correction cite le numéro de retour.

## 4. Critères de sortie

- Aucun retour bloquant ouvert ; les retours majeurs ont une correction livrée ou une décision écrite.
- Tests automatisés verts, audit d'accessibilité sans violation, charge sous les seuils sur la recette.
- Pour R6 et R7 : écart entre stock saisi et estimation du technicien expliqué ; dates de rupture jugées plausibles.
- Comité technique : validation des référentiels utilisés par les élevages testeurs, ou maintien assumé de la mention
  « repère à valider » (§ 9.5).
- Décision de mise en production signée par la direction (version `1.0.0`).
