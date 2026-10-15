# 9. Recette et plan de réalisation

## 9.1 Jeux d'essai

| Jeu | Contenu | Usage |
|---|---|---|
| J0 — Campagne 2025-2026 | Les 59 bulletins réels chargés par `03_chargement_campagne_2025-2026.sql` (données nominatives : environnement de recette uniquement, accès restreint) | Import, classement, positionnement, anomalie HA25-42515 |
| J1 — Élevage allaitant synthétique | Décrit ci-dessous ; reproduit dans `calculs/test_calculs.py` | Stocks, besoins, couverture, projection, leviers, incertitude |
| J2 — Élevage ovin synthétique | 300 brebis, foin et seau « bovins » à 300 mg Cu/kg | Maximum réglementaire cuivre (niveau 3), SMS |
| J3 — ELV-0001 (pseudonymisé) | Les neuf lots du prototype de fiche (document 04) | Fiche de visite complète, PDF, positionnement |
| J4 — Cloisonnement | Deux GDS, trois élevages, un technicien par GDS, un chargé de mission bi-GDS, un vétérinaire consenti sur un seul élevage | Tests d'accès |

**J1 en détail.** Inventaire au 1er novembre 2026, période du 1er novembre 2026 au 31 mars 2027 (151 jours), pertes à la distribution 13 % (déroulé au sol), marge de sécurité 15 %. Stocks : un silo de maïs couloir L1 30 m, L2 6 m, largeur 8 m, hauteur 2 m, MS 33 %, amidon 30 % (non consommé dans l'exemple, pour vérifier RC-5 seul) ; foin 2025 : 40 t MS utiles (lot déjà calculé) ; foin 2026 : 400 balles de 300 kg, MS 85 %, sous toit (3 %) ; enrubannage 2026 : 150 balles de 650 kg, MS 45 % (analyse), sous toit ; paille : 100 balles de 280 kg, MS 90 %, sous toit. Lots d'animaux : 100 vaches allaitantes en lactation (foin 4 kg MS, enrubannage 7 kg MS, correcteur azoté 0,88 kg MS, minéral 100 g) ; 30 génisses 1-2 ans (foin 5 kg MS, paille 1 kg MS). Résultats attendus : stock utile 205,94 t MS ; besoin prélevé 222,16 t MS ; sécurité 33,32 t ; bilan − 49,54 t ; autonomie 139 jours ; rupture de l'enrubannage le 23 décembre 2026 ; foin 2025 épuisé le 3 janvier 2027 sans rupture de la famille foin ; un achat de 30 t d'enrubannage livré le 15 décembre repousse la rupture au 30 janvier 2027 ; un re-comptage du foin 2026 à 50 t le 15 janvier produit un écart de recalage de 41,53 t. Couverture du lot des vaches : UFL 96 % (limite), PDI couvert, Ca/P 2,50, cuivre 23 mg/kg MS (excès informatif).

## 9.2 Cas de recette

Les cas `T-10` à `T-16` sont exécutables (`python3 calculs/test_calculs.py`, 60 vérifications) et doivent être portés en tests Pest sur `app/Domain`. Les autres sont des scénarios manuels ou des tests d'intégration.

| Cas | Exigence / règle | Scénario | Résultat attendu |
|---|---|---|---|
| T-01 | EF-M9-01, RC-1 | Importer les 59 PDF de J0 | 59 analyses créées, 58 en `controle`, ratios calculés, alertes générées conformes à la campagne (93 % des lots sous 7 mg Cu) |
| T-02 | RC-2 | Bulletin HA25-42515 | Statut `anomalie`, quatre résultats `flag_anomalie`, exclu des références, technicien notifié |
| T-03 | RC-1, EF-M2-02 | Foin MAT 61 g, Cu 4,2 mg, Fe 166 mg, K 18 g | Alertes : MAT faible (1), Cu carence probable (2) ; Fe et K sans alerte (K 18 g sous le seuil « élevé » de 30 g de la grille v1) ; niveau proposé 2 |
| T-04 | RC-1.4 | Même lot avec un événement « non-délivrance » enregistré dans les 12 mois | Rehaussement proposé affiché, niveau retenu laissé au technicien |
| T-05 | EF-M2-04 | Interprétation de niveau 3 publiée par un technicien | Refus ; publication possible seulement après visa vétérinaire conseil |
| T-06 | EF-M2-07 | Publication d'une visite puis modification | Version 2 créée, version 1 archivée et consultable, PDF v1 conservé |
| T-07 | RC-3 | Type de fourrage avec 4 lots | « Référence insuffisante (n = 4) » ; avec 5 lots : quartiles affichés |
| T-08 | RC-3, EF-M3-04 | Révocation du consentement « références » | Les lots sortent des vues après rafraîchissement ; n diminue |
| T-09 | RC-4 | Élevage avec deux campagnes de foin | Variation affichée en valeur et pourcentage, sans classe |
| T-10 | RC-5 | Silo J1 | Volume 528 m³, densité 223,245 kg MS/m³, stock utile 111,98 t MS ; refus de l'équation herbe à 40 % MS ; tassement 5 % à 10 jours |
| T-11 | RC-7 | Balles J1 | 98,94 ; 42,559 ; 24,444 t MS ; vrac 3,7485 t |
| T-12 | RC-8 | Voie UGB J1 sur 160 jours | 110 UGB, 228,8 t MS ; besoin prélevé 200/(1−0,13) |
| T-13 | RC-9 | Ration des vaches J1 ; ration ovine J2 | MSI 11,98 ; UFL limite, résiduel 0,34 ; PDI couvert ; Cu excès informatif ; Ca/P 2,50 ; J2 : `maximum_depasse`, gravité 3 |
| T-14 | RC-10, RC-11, RC-12 | Projection J1, puis achat et re-comptage | Valeurs du § 9.1 ; cinq mois ; cohérence stock début/fin |
| T-15 | RC-13 | Leviers et proposition initiale | Gains et coûts du tableau RC-13.3 ; réaffectation avant achat |
| T-16 | RC-14 | Incertitudes J1 | Silo 10,8 % ; balles estimées 16,2 % ; composition du total |
| T-20 | EF-M4-05, EF-M6-05 | Saisie d'un mouvement | Bilan recalculé, `plan.bilan` mis à jour en moins de 5 s |
| T-21 | EF-M8-03 | Rupture prévisionnelle à 59 jours | Une alerte e-mail éleveur + technicien ; aucune seconde alerte au recalcul suivant |
| T-22 | EF-M8-02 | Publication niveau 3 (J2) | SMS éleveur et technicien, journalisé |
| T-23 | EF-M7-02 | Quatre scénarios | Comparaison côte à côte ; un seul scénario `retenu` à la fois |
| T-24 | EF-M7-05 | Scénario retenu avec achat | Mouvement `achat` créé à la date de livraison, lié à la ligne |
| T-25 | EF-GEN-04 | Libellés générés | Aucun nom de produit dans les messages, propositions et PDF (recherche automatique sur une liste de marques courantes) |
| T-26 | EF-GEN-08 | Export PDF des trois gabarits | Charte bi-entité, mentions de limites, version de grille, en moins de 10 s |
| T-27 | EF-M9-03 | Import SAGEMA (fichier de test) | Création et mise à jour par EDE, rapport d'import, aucune écriture externe |
| T-28 | EF-M9-06 | Import JSON du calculateur HTML | Inventaire créé avec les mêmes stocks utiles que le calculateur |
| T-30 | EF-M0-03 | Lien de connexion | Expiré après 15 minutes ; inutilisable deux fois |
| T-31 | EF-M0-04 | Connexion Entra ID | Rôles dérivés des groupes ; refus pour un compte hors groupes |
| T-32 | EF-M0-06, D12 | Convention échue | Lecture seule : écriture refusée sur `stock.*` et `plan.*` |
| T-40 | § 6.3 | J4 : chaque route, chaque rôle | Aucun accès transversal entre GDS ni entre élevages ; vétérinaire limité à l'élevage consenti ; révocation effective |
| T-41 | ENF-ACC-01 | Audit RGAA des écrans éleveur | Pas de non-conformité bloquante ; contrastes vérifiés |
| T-42 | ENF-PERF-01 | 50 utilisateurs simultanés simulés | Temps de réponse sous les seuils |

## 9.3 Ordre de construction

La livraison se fait en une version (D21), mais l'ordre de construction suit les dépendances et permet des revues intermédiaires avec les techniciens.

| Lot | Contenu | Livrable de revue |
|---|---|---|
| L1 — Socle | Projet Laravel, Docker, migration du socle (document 02), schémas `app`, authentification (mot de passe, lien, Entra), rôles, cloisonnement, tests T-30 à T-40, design system bi-entité | Connexion et navigation vide, tests d'accès verts |
| L2 — Import et interprétation | E19, E20, RC-1, RC-2, référentiels versionnés (E24), J0 chargé, T-01 à T-05 | Les 59 bulletins interprétables |
| L3 — Dossier et publication | `dossier.*`, E03 à E11, E21, PDF fiche, notifications e-mail et SMS, note de liaison, T-06, T-22, T-26 | Première fiche de visite publiée (J3) |
| L4 — Positionnement | Vues de référence, E04 positionnement, historique, T-07 à T-09 | Graphiques de positionnement sur J0 |
| L5 — Stocks et troupeau | `stock.*`, `plan.*`, E12, E13, RC-5 à RC-9, import JSON du calculateur, T-10 à T-13, T-28 | Saisie mobile d'un inventaire et d'une ration |
| L6 — Autonomie et approvisionnement | RC-10 à RC-14, E14, E15, E16, bilan en job, alertes d'autonomie, PDF bilan et scénario, T-14 à T-16, T-20, T-21, T-23, T-24 | Bilan J1 conforme aux valeurs attendues |
| L7 — Administration, imports, pilotage | E23, E25, import SAGEMA, exports (rationnement, RGPD), T-27 | Administration complète |
| L8 — Recette et mise en production | Recette avec 3 à 5 élevages testeurs, audit d'accessibilité, performance, documentation, déploiement chez l'hébergeur | Mise en production |

Estimation indicative pour un développement interne assisté par IA, à affiner après le lot 1 : L1 8 jours, L2 8, L3 12, L4 4, L5 10, L6 10, L7 6, L8 8, soit environ 65 jours de travail, hors validation des référentiels par le comité technique (temps du vétérinaire conseil et des techniciens non compté).

## 9.4 Points ouverts à trancher avant ou pendant le lot concerné

| N° | Point | Lot | Qui tranche |
|---|---|---|---|
| 1 | Plages de plausibilité RC-2 (valeurs proposées [CP]) | L2 | Comité technique |
| 2 | Grille eau d'abreuvement (paramètres et seuils) | L3 | Vétérinaire conseil |
| 3 | Repères des mesures terrain et des profils sanguins : sources à citer ou valeurs à remplacer (statut [C] actuel) | L3 | Vétérinaire conseil |
| 4 | Seuils S1/S2 du bilan sanitaire et grille trouble × cause (NBVC) : droits d'usage et source à citer | L3 | Chargé de mission |
| 5 | Chargement des tables INRAE-CIRAD-AFZ (licence d'utilisation des Feedtables pour un usage applicatif) | L5 | Chargé de mission |
| 6 | Besoins journaliers INRA 2018 par catégorie et stade : périmètre de la table à charger ; remplacement des repères [C] des vaches allaitantes | L5 | Vétérinaire conseil, technicien référent |
| 7 | Référence complète de Faverdin et al. (taux de substitution) | L6 | Chargé de mission |
| 8 | Prix indicatifs GDS des fourrages et concentrés (source et fréquence de révision) | L6 | Comité technique |
| 9 | Durées de conservation RGPD et registre des traitements | L1 | Direction, DPO |
| 10 | Nom de domaine et hébergeur | L8 | Direction |
| 11 | Convention LANO pour un export structuré | L2 (ultérieur) | Direction |
| 12 | Nom définitif (Focus Troupeau / Mise au point) | L1 (jetons) | Conseil d'administration |

## 9.5 Risques

| Risque | Effet | Parade |
|---|---|---|
| Référentiels non validés à temps (points 1 à 6) | Calculs justes sur des repères contestables | Statut épistémique affiché ; valeurs [C] et [CP] marquées « à valider » dans l'interface technicien ; aucune publication éleveur sur un repère non validé |
| Extraction PDF LANO fragile (changement de maquette du bulletin) | Imports en échec | Tests sur J0 à chaque mise à jour ; saisie manuelle de secours ; négociation d'un export structuré |
| Saisie des stocks trop lourde pour l'éleveur | Module non utilisé | Saisie mobile minimale (nombre de balles, poids, cotes), valeurs par défaut, inventaire fait avec le technicien la première fois |
| Confusion entre estimation et mesure | Décisions d'achat sur des stocks surestimés | Incertitude affichée, indicateurs `estime`/`mesure`, marge de sécurité majorée si estimations majoritaires |
| Livewire et réseau mobile médiocre | Saisie interrompue au silo | Brouillons locaux, mode hors ligne en V2 |
| Dépendance à une personne (développement interne) | Maintenance | Documentation dans le dépôt, tests, implémentation de référence, code domaine indépendant du cadre |
