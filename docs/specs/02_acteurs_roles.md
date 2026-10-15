# 2. Acteurs, rôles et droits

## 2.1 Acteurs

| Acteur | Qui | Ce qu'il attend de l'application |
|---|---|---|
| Éleveur contractant | Adhérent ayant signé une convention d'approche globale avec le GDS 24 ou le GDS 47 ; une ou plusieurs personnes par exploitation (chaque personne a son compte) | Consulter ses résultats, ses préconisations, sa position ; saisir et actualiser ses stocks et ses lots ; simuler ; être prévenu |
| Technicien GDS | Technicien d'un GDS ou des deux (chargé de mission transversal) | Importer, contrôler, interpréter, rédiger, valider, publier ; suivre les alertes et les contrôles ; saisir les mesures terrain et les inventaires en visite |
| Vétérinaire conseil du GDS | Vétérinaire salarié ou conventionné du GDS | Viser les interprétations de niveau 3 ; relire les plans d'action qui le mentionnent |
| Vétérinaire de l'élevage | Vétérinaire sanitaire ou traitant, sur consentement explicite de l'éleveur | Lire le dossier de l'élevage ; saisir les résultats des analyses animales qu'il a prescrites |
| Administrateur | Un ou deux agents habilités (chargé de mission, direction) | Gérer les GDS, les comptes, les conventions, les référentiels, les paramètres par défaut, les consentements ; consulter les journaux |
| Comité technique | Instance de gouvernance du service (document 03, § 6.2) | N'utilise pas l'application directement ; ses décisions sont saisies par l'administrateur dans les référentiels versionnés |

## 2.2 Rôles applicatifs

Les rôles sont portés par la bibliothèque `spatie/laravel-permission` (rôles et permissions nommées), avec un cloisonnement par GDS décrit au chapitre 6. Un utilisateur peut cumuler des rôles (un chargé de mission est `technicien` et `administrateur` ; un vétérinaire conseil est `veterinaire_gds`).

| Rôle | Code | Portée |
|---|---|---|
| Éleveur | `eleveur` | Les élevages auxquels son compte est rattaché (table `app.elevage_acces`) |
| Technicien | `technicien` | Tous les élevages des GDS auxquels il est rattaché (table `app.user_gds`) |
| Vétérinaire conseil GDS | `veterinaire_gds` | Tous les élevages des GDS auxquels il est rattaché, en lecture, plus le visa des interprétations de niveau 3 |
| Vétérinaire d'élevage | `veterinaire` | Les élevages pour lesquels un consentement actif existe (table `app.consentement`) |
| Administrateur | `admin` | Son ou ses GDS ; un super-administrateur (`admin_global`) voit l'ensemble de l'instance |

## 2.3 Matrice des droits

Légende : **L** lecture · **E** écriture · **V** validation / publication · **—** aucun accès. « Propre élevage » désigne l'élevage auquel le compte est rattaché.

| Objet | Éleveur | Technicien | Vét. conseil | Vét. élevage | Admin |
|---|---|---|---|---|---|
| Dossier élevage (identité, convention) | L propre | L/E | L | L (consenti) | L/E |
| Prélèvements et bulletins de fourrages | L propre (publiés) | L/E | L | L | L |
| Interprétations et alertes | L propre (statut `publie`) | L/E/V (niveaux 0-2) | L/V (niveau 3) | L | L |
| Analyses d'eau | L propre (publiées) | L/E/V | L | L | L |
| Profils sanguins (analyses animales) | L propre (publiés) | L/E/V | L/E | L/E | L |
| Mesures terrain | L propre (publiées) | L/E/V | L | L | L |
| Étiquettes de minéraux et aliments | L/E propre | L/E/V | L | L | L |
| Indicateurs du bilan sanitaire | L propre | L/E (import) | L | L | L |
| Plan d'action et contrôles | L propre ; E sur le statut « fait » des actions qui lui incombent | L/E/V | L/V (actions vétérinaires) | L | L |
| Positionnement (références) | L propre | L | L | L | L |
| Inventaires de stocks | L/E propre | L/E | L | L | L |
| Lots d'animaux et rations | L/E propre | L/E | L | L | L |
| Bilan d'autonomie et projection | L propre | L | L | L | L |
| Scénarios d'approvisionnement | L/E propre | L/E | L | L | L |
| Paramètres de calcul de l'élevage | L/E propre (avec rappel de la référence) | L/E | L | — | L |
| Référentiels (seuils, besoins, valeurs par défaut) | — | L | L | — | L/E (versionné) |
| Comptes, rôles, conventions, consentements | L propre (ses consentements : E) | L | — | — | L/E |
| Journaux d'audit | — | — | — | — | L |

Deux règles complètent la matrice :

- l'éleveur ne voit une interprétation, une analyse ou une mesure que lorsque son statut est `publie` ; les brouillons et les objets `valide` non publiés lui restent invisibles ;
- un objet `publie` n'est plus modifiable : une correction crée une nouvelle version (`version + 1`), l'ancienne passe en `archive` et reste consultable par le technicien.

## 2.4 Cycle de vie du compte éleveur et de la convention

```
Convention signée ──► Compte créé par le technicien (e-mail, EDE) ──► Invitation envoyée
        │                                                                  │
        │                                                   Activation (définition du mot de passe
        │                                                   ou première connexion par lien)
        ▼                                                                  ▼
Convention active ──────────────────────────────────────────► Accès complet
        │
        ▼
Convention échue ou résiliée ──► Accès « lecture seule » : dossier, fiches, historique des bilans ;
                                  module stocks et scénarios fermés (données conservées)
        │
        ▼
Demande de suppression (RGPD) ──► Anonymisation de prive.* et des comptes ; les données
                                  pseudonymisées restent dans les références agrégées
                                  si le consentement « références » était actif
```

Une convention porte : le GDS émetteur, l'élevage, la date de début, la date de fin prévue, le type de prestation, le statut (`active`, `echue`, `resiliee`). Le passage à `echue` est automatique à la date de fin (tâche planifiée quotidienne) ; le technicien peut prolonger. La table `app.convention` est décrite au chapitre 5.

## 2.5 Consentements

Trois consentements distincts sont gérés, chacun avec une date, un auteur de la saisie et une révocation possible :

| Consentement | Porté par | Effet |
|---|---|---|
| Traitement des données (information RGPD) | `prive.elevage_identite.consentement_donnees` (existant) | Condition de création du compte ; affichage de la notice d'information à la première connexion |
| Agrégation dans les références | `app.consentement` type `references` | Les lots de l'élevage entrent dans les quartiles départementaux et pédo-géologiques (toujours sans identité, et seulement si n ≥ 5) |
| Partage avec le vétérinaire | `app.consentement` type `veterinaire`, avec l'identifiant du vétérinaire | Ouvre l'accès en lecture au vétérinaire désigné ; permet l'envoi de la note de liaison |

L'éleveur peut révoquer les deux derniers depuis son espace ; la révocation est horodatée et prend effet immédiatement (le vétérinaire perd l'accès à la session suivante ; les lots sortent des références au prochain rafraîchissement des vues matérialisées).
