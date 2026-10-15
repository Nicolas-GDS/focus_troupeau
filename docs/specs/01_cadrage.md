# 1. Cadrage

## 1.1 Objet du document

Ce document spécifie **Focus Troupeau**, l'espace en ligne de la prestation d'approche globale de la santé du troupeau proposée par le GDS 24 et le GDS 47. Il est rédigé pour un développement assisté par IA mené en interne : il décrit à la fois ce que l'application doit faire (exigences fonctionnelles, règles métier), comment elle est construite (modèle de données, architecture Laravel) et comment on vérifie qu'elle est conforme (plan de recette). Chaque règle de calcul y est formalisée et rattachée à sa source, avec le statut épistémique hérité des documents 01 et 05 du dossier « Approche globale ».

Le document s'appuie sur les livrables déjà produits et validés en interne, qu'il ne reprend pas mais auxquels il renvoie :

| Document existant | Ce que les spécifications en reprennent |
|---|---|
| 01 — Base de connaissance fourrages | Grille d'interprétation, besoins, références bibliographiques |
| 02 — Modèle de données PostgreSQL/PostGIS | Schémas `ref`, `prive`, `fourrage`, `sig`, `valo` : socle conservé et étendu |
| 03 — Démarche de valorisation | Parcours en huit étapes, niveaux d'alerte 0-3, rôles éleveur / technicien / vétérinaire |
| 04 — Outils de restitution | Fiche éleveur, matrice minérale, positionnement campagne, catalogue d'outils |
| 05 — Fiche pratique stocks fourragers | Équations de cubage et de densité, pertes, voies UGB et ration, stock de sécurité, leviers |
| 06 — Fiche terrain stocks | Structure du relevé en élevage |
| Calculateur d'autonomie fourragère (HTML) | Moteur de calcul v0, valeurs par défaut, catalogue de leviers |
| Classeur « Bilan fourrager avec rations » | Calcul de ration par lot, repères alimentaires, diagnostic par élément |
| Présentation « L'approche globale — exemple EARL Forgeneuf » | Les cinq piliers, les six étapes, les mesures terrain et le plan d'action |
| Cahier d'identité Focus Troupeau | Nom, logotype, couleurs, jeu d'icônes |

## 1.2 La prestation que l'application outille

L'approche globale est une démarche en six étapes (collecter, analyser les fourrages, mesurer les animaux, croiser, agir, suivre) qui lit la santé du troupeau à travers cinq piliers : hydratation, nutrition, physique, environnement, bien-être. Elle produit pour chaque élevage contractant un dossier composé d'analyses de laboratoire (fourrages, eau, profil oligo-éléments sanguin), de mesures terrain (pH et redox des bouses, glycémie, BOH, urée, pH et Brix urinaires, NEC, tamisage), de la lecture de l'étiquette du minéral distribué, des indicateurs du bilan sanitaire GDS, d'un plan d'action priorisé et d'un calendrier de contrôles.

Aujourd'hui, ce dossier est restitué sous forme de fiche PDF et de présentation commentée en visite. L'application a trois raisons d'être :

1. donner à l'éleveur un accès permanent et lisible à son dossier, à ses préconisations et à sa position par rapport aux autres élevages ;
2. transformer le bilan fourrager, aujourd'hui un classeur et un calculateur isolés, en un module vivant relié aux analyses : stocks, lots d'animaux, rations, autonomie projetée et plan d'approvisionnement ;
3. donner aux techniciens un outil de travail unique (import, interprétation, validation, publication, suivi) alimenté par la base de données du service, de sorte qu'un seuil modifié en comité technique se répercute partout.

## 1.3 Décisions de cadrage

Les décisions suivantes ont été prises le 15 octobre 2026 et fixent le périmètre. Elles sont reprises dans le journal des décisions en annexe.

| N° | Sujet | Décision |
|---|---|---|
| D1 | Périmètre des résultats | Tout le dossier d'approche globale est restitué ; les fourrages constituent le module le plus abouti |
| D2 | Préconisations | Alertes automatiques issues de la grille + commentaire du technicien ; validation avant publication ; niveau 3 visé par le vétérinaire conseil |
| D3 | Références de positionnement | Campagne départementale par type de fourrage (n ≥ 5) ; région pédo-géologique quand n suffit ; historique pluriannuel de l'élevage |
| D4 | Socle de données | Base PostgreSQL/PostGIS du document 02 conservée et étendue |
| D5 | Saisie des stocks | Éleveur et technicien ; mises à jour en cours de période |
| D6 | Besoins des lots | Tonnage MS et couverture nutritionnelle par lot (ration détaillée reliée aux lots analysés) |
| D7 | Temporalité | Projection mensuelle avec date de rupture prévisionnelle |
| D8 | Approvisionnement | Proposition initiale puis simulation de scénarios ; prix saisis par l'éleveur ; aucun produit nommé |
| D9 | Leviers | Achats de fourrages, achats de concentrés/correcteurs, leviers de conduite, réaffectation des lots |
| D10 | Paramètres de calcul | Valeurs GDS par défaut, modifiables par l'éleveur avec rappel de la référence |
| D11 | Profils | Éleveur contractant, technicien GDS, vétérinaire sur consentement (pas de délégués en V1) |
| D12 | Fin de convention | Historique conservé en lecture seule ; module stocks et simulations fermés |
| D13 | Multi-GDS | Instance commune cloisonnée par GDS, extensible |
| D14 | Authentification | Comptes propres éleveurs (e-mail + mot de passe ou lien de connexion), identifiant EDE ; techniciens via Entra ID ; import SAGEMA par interface |
| D15 | Notifications | E-mail à la publication ; SMS pour le niveau 3 ; alerte d'autonomie (Brevo) |
| D16 | Pile technique | Laravel + Blade/Livewire + PostgreSQL, en cohérence avec Coélab et son système de design |
| D17 | Hébergement | Machine virtuelle locale pour le développement, puis hébergeur français mutualisé en production |
| D18 | Mobile | Responsive dès la V1 ; saisie hors ligne reportée en V2 |
| D19 | Développement | Interne, assisté par IA ; spécifications fonctionnelles et techniques détaillées |
| D20 | Nom | Focus Troupeau, charte bi-entité GDS 24 / GDS 47 |
| D21 | Phasage | Livraison en une seule version ; ordre de construction proposé au chapitre 9 |
| D22 | Référentiels de besoins | Idele (bilan fourrager 2020) pour la matière sèche ; INRA 2018 pour UFL, PDI et minéraux |
| D23 | Livrables de spécification | Document Word à la charte et version Markdown pour le dépôt de code |

## 1.4 Ce que l'application fait et ne fait pas

**Elle fait** : restituer, comparer, contextualiser, projeter et tracer. Elle calcule des alertes à partir d'une grille sourcée, des positions par rapport à des références, des bilans de stocks, des besoins, des couvertures et des scénarios. Elle enregistre qui a décidé quoi, quand, sur quelle version des seuils.

**Elle ne fait pas** : prescrire, formuler ou nommer un produit, remplacer le calcul de ration complet d'un nutritionniste ou le diagnostic d'un vétérinaire. Elle exprime des besoins en éléments (g ou mg par animal et par jour, ou par kg de MS de ration) et des quantités de fourrages ou de concentrés par catégorie, jamais une marque. Cette frontière, fixée dans le document 03, est reprise comme exigence (EF-GEN-04) et comme règle de rédaction des libellés.

Elle ne remplace pas non plus la visite : la publication d'une fiche déclenche une notification, mais la restitution reste commentée par le technicien (visite ou téléphone), conformément à l'étape 6 de la démarche.

## 1.5 Hors périmètre de cette version

Les éléments suivants sont explicitement exclus de la version spécifiée ici et notés comme évolutions possibles :

- la saisie hors ligne (PWA) : reportée en V2, avec les contraintes d'architecture décrites au chapitre 7 ;
- la délégation d'accès par l'éleveur à des tiers (associé, salarié, nutritionniste) ;
- le calcul de ration complet au sens INRA 2018 (optimisation, réponses multiples, encombrement fin) : l'application calcule des apports et des couvertures, elle ne rationne pas ;
- l'optimisation économique automatique du plan d'approvisionnement (la proposition initiale est heuristique) ;
- la gestion des prophylaxies, de la facturation ou de tout autre service du GDS : l'application est dédiée à l'approche globale ;
- l'import automatique des profils sanguins depuis le laboratoire d'analyses départemental (saisie manuelle en V1) ;
- les cartes interactives sol–fourrage (les couches `sig` servent au positionnement par région pédo-géologique, pas à une cartographie dans l'application).

## 1.6 Principes de conception

Cinq principes gouvernent les choix qui suivent ; ils sont hérités des documents 03 et 04 et deviennent des exigences vérifiables au chapitre 3.

1. **Partir de ce que l'éleveur doit décider**, pas de ce que le laboratoire a mesuré : chaque écran de résultat commence par les messages hiérarchisés, les tableaux de valeurs viennent ensuite.
2. **Un chiffre n'est jamais seul** : il est accompagné de son repère (seuil, besoin, quartile) et de la source de ce repère ; la couleur d'état est toujours doublée d'un libellé et de la valeur.
3. **Distinguer ce qui est mesuré de ce qui est estimé** : les stocks, densités, pertes et besoins portent une incertitude affichée ; les valeurs par défaut sont toujours identifiées comme telles.
4. **Une seule base, une seule grille** : tous les calculs lisent `ref.seuil_interpretation`, `ref.besoin_animal` et les paramètres GDS ; aucune valeur de référence n'est codée en dur dans l'interface.
5. **Traçabilité** : chaque interprétation, chaque inventaire, chaque scénario est versionné (auteur, date, statut, version de la grille) ; rien n'est supprimé, tout est archivé.
