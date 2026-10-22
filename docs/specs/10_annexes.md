# 10. Annexes

## 10.1 Glossaire

| Terme | Définition |
|---|---|
| Approche globale | Prestation GDS 24 / GDS 47 de lecture de la santé du troupeau par les cinq piliers (hydratation, nutrition, physique, environnement, bien-être) en six étapes |
| BACA | Balance anions-cations (Na + K) − (Cl + S), en mEq/kg MS ; BE : bilan électrolytique Na + K − Cl |
| BPR | Balance protéique ruminale (INRA 2018), g/kg MS ; cible de ration 0 |
| Campagne | Période de récolte et d'analyse (ex. 2025-2026) |
| Couverture | Apport / besoin, en pourcentage, pour un élément et un lot d'animaux |
| Famille de fourrage | Foin, regain, enrubannage, ensilage d'herbe, ensilage de maïs ou sorgho, paille, vert, concentré |
| Inventaire | Relevé daté des stocks d'un élevage, avec sa période de référence |
| Interprétation | Lecture d'un bulletin (ou d'une visite) par le technicien : alertes retenues, lectures, recommandations, niveau |
| Lot analysé (prélèvement) | Un lot de fourrage prélevé et analysé |
| Lot de stock | Un ensemble homogène de fourrage en stock (silo, balles d'une même coupe) |
| Lot d'animaux | Groupe d'animaux de même catégorie conduits ensemble, avec ses phases et ses rations |
| MS, MSI | Matière sèche ; matière sèche ingérée (kg par animal et par jour) |
| Niveau | Gravité globale 0 RAS, 1 vigilance, 2 alerte, 3 critique (document 03) |
| Positionnement | Place d'un lot dans une population de référence (quartiles) |
| Scénario | Ensemble de leviers appliqué à un inventaire, évalué en coût et en effet |
| Stock utile | Stock brut diminué des pertes de conservation ou de stockage, en t MS |
| UGB | Unité gros bétail (coefficients Idele) |
| UFL, PDI | Unité fourragère lait ; protéines digestibles dans l'intestin (INRA 2018) |

## 10.2 Routes principales

Préfixe `/{gds}` implicite par l'utilisateur ; les identifiants sont des entiers ou des UUID. Les composants Livewire portent la logique ; les routes ci-dessous sont celles des pages.

| Méthode et route | Écran | Rôle |
|---|---|---|
| GET /connexion, /connexion/lien, /activation/{token}, /auth/entra | E01 | public |
| GET /accueil | E02 | eleveur |
| GET /elevages/{elevage}/visites/{visite} | E03 | eleveur, technicien, veterinaire |
| GET /elevages/{elevage}/fourrages, /fourrages/{prelevement} | E04 | idem |
| GET /elevages/{elevage}/mineraux-matrice | E05 | idem |
| GET /elevages/{elevage}/eau ; /animaux ; /aliments ; /sanitaire ; /historique ; /plan-action | E06–E11 | idem |
| GET /elevages/{elevage}/stocks, /stocks/{inventaire} | E12 | eleveur, technicien |
| GET /elevages/{elevage}/troupeau | E13 | eleveur, technicien |
| GET /elevages/{elevage}/autonomie | E14 | eleveur, technicien |
| GET /elevages/{elevage}/approvisionnement, /scenarios/{scenario} | E15 | eleveur, technicien |
| GET /elevages/{elevage}/parametres | E16 | eleveur, technicien |
| GET /compte, /compte/consentements | E17 | eleveur, veterinaire |
| GET /travail | E18 | technicien |
| GET /import/bulletins | E19 | technicien |
| GET /analyses/{analyse}/interpretation | E20 | technicien |
| GET /visites/{visite}/interpretation | E21 | technicien, veterinaire_gds |
| GET /visites/{visite}/note-liaison | E22 | technicien |
| GET /admin/gds, /admin/utilisateurs, /admin/conventions | E23 | admin |
| GET /admin/referentiels/{table} | E24 | admin |
| GET /admin/pilotage | E25 | admin, technicien |
| GET /admin/journal | E23 (journal d'audit, ajout L7) | admin |
| GET /elevages, /elevages/{elevage}/fiche | E23 (élevages, ajout L7) | technicien, admin |
| GET /import/sagema | M9 (import SAGEMA, ajout L7) | technicien |
| GET /elevages/{elevage}/export/{type}.pdf ; /export/donnees.zip | — | selon objet |
| POST /webhooks/brevo | — | Brevo (signature vérifiée) |

## 10.3 Correspondance des paramètres LANO

Reprise du dictionnaire du document 02 (`ref.parametre`) : MS, MM, MO, MAT, CB, DCS, SSR, AMI, MGT, PH, NDF, ADF, ADL, P, CA, MG, K, NA, S, CL, CU, ZN, MN, FE, AL, SE, I, CO, MO_OLIGO, DMO, UFL, UFV, PDIN, PDIE, PDIA, UEL, UEB, UEM, DNDF, DMONA, AMIDR, P_ABS, CA_ABS, BACA, BE, UFL_2018, UFV_2018, PDI, PDIA_2018, BPR, DT6_N, DT6_AMI, AMIDR_2018, AMI_BYPASS, NI, EB, NH3_NTOT, AC_LACT, AC_ACET, AC_BUTYR, SPORES_BUT, DON, ZEA, FUM, AFB1, OTA, NITRATES ; dérivés : K_CAMG_EQ, CA_P, CU_MO, PDI_UFL. Les paramètres d'eau (`EAU_*`) et les mesures terrain (`ref.mesure_terrain`) sont ajoutés par le lot 3.

## 10.4 Journal des décisions

| Date | Décision | Source |
|---|---|---|
| 03/09/2026 | Grille d'interprétation v1, modèle de données PostgreSQL/PostGIS, démarche en huit étapes, prototype de fiche | Documents 01 à 04 |
| 21/09/2026 | Équations Idele 2020 pour les stocks, 13 kg MS/UGB/j, marge de sécurité 15 % (25 % en année sèche), catalogue de leviers | Document 05, calculateur |
| 08/10/2026 | Nommage : concept « Focus » retenu, « Focus Troupeau » recommandé | Cahier d'identité |
| 15/10/2026 | Décisions D1 à D23 (§ 1.3) | Cadrage des spécifications |
| 15/10/2026 | Lot L1 : bases graphiques de SAGEMA (et non Coélab) ; `app.convention` porte `cree_par` et `maj_par` (EF-GEN-06) ; GDS 47 nommé « GDS Lot-et-Garonne » | Revue du lot L1 |
| 15/10/2026 | Référentiel minéraux × troubles (chapitre 11) : rehaussement RC-1.4 par les seuls liens de force ≥ 2 (paramètre réglable) ; colonne `sens` sur les seuils ; repères minéraux reportés au lot L5 ; REG2018_1039 relevée en « vérifiée » | Préparation du lot L2 |
| 15/10/2026 | EF-M2-04 : le visa dépend du niveau retenu (retenir 2 sur un niveau proposé 3 dispense du visa) ; refus de visa motivé, retour en brouillon ; grille version 3 : seuil Ca/P des mâles `BOUC_MALE` ramené à l'espèce caprine (doublon du seuil caprin, clôturé) ; propositions des étapes 5 et 6 validées (lecture minérale, motifs de rehaussement, synthèse ≤ 5 lignes, plage sans borne refusée, libellés) | Revue des étapes 5 et 6 du lot L2 |
| 15/10/2026 | T-04 redéfini (Mg bas + tétanie) pour être cohérent avec le référentiel du chapitre 11 ; extraction LANO en JSON (§ 6.1) | Lot L2, étape 3 |
| 15/10/2026 | T-03 : pas d'alerte K à 18 g (seuil « élevé » de la grille v1 à 30 g) ; RC-1 : ordre de priorité du texte retenu contre celui de `ref.f_classer_valeur`, départage par gravité, chevauchements interdits dans E24 | Préparation du lot L2 |
| 16/10/2026 | Lot L3 : position dans la campagne (RC-3) de E04 reportée au lot L4 ; E08 limité aux étiquettes et à l'apport journalier par élément (dose × composition), la comparaison au besoin (RC-9) reportée au lot L5 ; T-26 limité au gabarit « fiche de visite » (bilan et scénario au lot L6) ; clé étrangère `dossier.mesure_terrain.lot_animaux_id` posée au lot L5 ; repères des points ouverts 2 à 4 transcrits depuis la présentation Forgeneuf et le document 04 fournis par le chargé de mission, au statut [C] ; jeu J3 : élevage ELV-0001 fictif pseudonymisé (neuf lots), à remplacer par les lots du document 04 | Préparation du lot L3 |
| 16/10/2026 | § 9.5 : un volet lu sur des repères [C] ou [CP] (eau, animaux, bilan sanitaire) est publiable ; chaque valeur concernée porte la mention « repère à valider » à l'écran et dans le PDF | Préparation du lot L3 |
| 16/10/2026 | Référentiels du dossier transcrits de la présentation Forgeneuf (GDS 24, 2026), statut [C], non validés : repères PRITIH par stade, lectures combinées, repères sériques Se, I, Fe, Cu, Zn, seuils S1/S2 (NBVC), grille trouble × cause. Urée en mg/l et BOH en mmol/l (la note « 0,1 g/l = 100 mg/dl » de la source est erronée) ; Brix du colostrum 22 % (cible des contrôles ; 25 % cité dans l'action) ; mortalité des veaux : S2 = 5 %, sans S1 ; IVV sans seuil ; RC-1.5 : carence sévère à 50 % de la borne basse ou en dessous. Grille eau : seul le fer (200 µg/l) est sourcé, gravité 1 proposée [CP] ; les autres paramètres sont créés sans seuil (point ouvert 2 maintenu). Précisions [CP] de RC-1.5 (lectures, bilan, croisement, synthèse de visite) | Lot L3, étape 2 |
| 16/10/2026 | Publication d'une visite (EF-M2-04, EF-M2-07, T-06), propositions [CP] : la publication de l'interprétation de visite publie aussi les interprétations de lots publiables et les objets validés de la visite (eau, mesures, profils, étiquettes) ; elle est refusée tant qu'un objet de la visite reste en saisie ou qu'un lot n'est pas publiable ; le visa porte sur le niveau retenu de la visite ; une visite publiée n'accepte plus de saisie, une nouvelle version ne corrige que l'interprétation (texte, messages, causes, plan) et une donnée nouvelle appelle une nouvelle visite ; la version publiée reste visible de l'éleveur jusqu'à la publication de la suivante, qui l'archive ; le suivi du plan publié (actions faites, résultats des contrôles) ne crée pas de version ; un bulletin est visible de l'éleveur dès que son interprétation est publiée | Lot L3, étape 4 |
| 16/10/2026 | Notifications (EF-M8-01, EF-M8-02, EF-M8-05), propositions [CP] : préférences portées par `app.user.pref_email` et `pref_sms` (désinscription globale par canal, ignorée au niveau 3, e-mail comme SMS) ; e-mail par le SMTP Brevo, SMS par l'API Brevo sans SDK ; textes en textes types (`ref.texte_type`) ; consigne du SMS = premier message de gravité 3, tronqué à 120 caractères ; un lot publié avec sa visite n'est pas notifié séparément. Note de liaison (EF-M2-09) : rédigée depuis l'interprétation de visite (E22 intégré à E21), pour un vétérinaire désigné par un consentement actif, retour saisi par le vétérinaire dans son espace. Restent à faire : webhook des statuts Brevo (§ 6.6), rappels EF-M8-04, note depuis une interprétation de bulletin seule | Lot L3, étape 6 |
| 16/10/2026 | Dossier en lecture (E02 à E11, EF-M1-01 à EF-M1-09), propositions [CP] : volets ouverts aux rôles éleveur, vétérinaire d'élevage, technicien et vétérinaire conseil (pas à l'administrateur) ; l'élevage de l'adresse devient l'élevage courant du sélecteur ; une visite se lit dans sa dernière version publiée et un lot dans sa dernière interprétation publiée, y compris pendant la préparation d'une version suivante ; les agents lisent aussi le non publié, marqué « non publié » ; l'accueil liste les actions dont le lecteur est responsable (éleveur ; vétérinaire pour le vétérinaire d'élevage) et les contrôles non réalisés des plans publiés ; le compte rendu de visite du technicien n'est pas montré à l'éleveur ; l'éleveur ajoute une étiquette hors visite depuis E08, validée par le technicien depuis la saisie de visite. Point ouvert : une étiquette hors visite validée n'est publiée par aucune visite (seul son auteur la lit). Reportés : position RC-3 et courbes RC-4 au lot L4, variante ration et comparaison au besoin RC-9 au lot L5, autonomie de l'accueil au lot L6 | Lot L3, étape 7 |
| 16/10/2026 | Jeu J3 : le document 04 étant versé au dépôt, J3 reprend les neuf lots réels pseudonymisés d'ELV-0001 transcrits des figures du prototype (et non un jeu fictif) ; Ca, P, S, Cl absents des figures, donc ratios associés non calculés ; une seule visite au 29/07/2026 regroupe les neuf lots ; agents de J4 ; priorités du plan d'action proposées pour la recette (le prototype n'en donne pas). La grille retrouve les constats du prototype (Cu < 7 sur 9 lots, Zn < 30 sur 8, BPR < −30 sur 2, Fe > 300 sur 1, aucun MM ≥ 100) | Lot L3, étape 8 |
| 16/10/2026 | Lot L4, préparation : populations de référence limitées aux bulletins `valide` (texte de RC-3) ; nouvelles vues `valo` (`v_reference_gds`, `v_reference_litho`, `v_reference_bigds`, `v_historique_elevage`) sans filtre sur n, non matérialisées, filtrant consentement, exclusion et anomalies, le seuil `references.n_min` étant appliqué par le domaine ; vues du socle intactes ; graphiques de positionnement et d'historique en SVG côté serveur (Chart.js réservé au lot L6) ; jeu J5 de population synthétique pour le développement ; précisions [CP] de RC-3 et RC-4 (§ 4.1) ; points ouverts 14 (consentements de J0) et 15 (couches SIG) | Préparation du lot L4 |
| 16/10/2026 | Historique (RC-4, E10) : `valo.v_historique_elevage` donne une ligne par lot avec l'indicateur « publié » ; l'éleveur et le vétérinaire d'élevage ne voient dans les courbes et dans le rappel des campagnes précédentes du positionnement que leurs lots publiés (un lot validé non publié restait sinon visible) ; médiane par le domaine ; courbes MAT, UFL 2018, Cu, Zn par type de fourrage (EF-M1-08) ; frise des décisions par campagne | Lot L4, étape 3 |
| 17/10/2026 | Lot L5, préparation : valeurs par défaut RC-6 à RC-9 reprises de `calculs/` (`DEFAUTS`, qui fait foi) ; tables d'aliments créées vides (point 5), concentrés et minéraux décrits par leur composition saisie (étiquette complétée d'une MS), lot de stock non analysé sans apport nutritionnel (« apports inconnus ») ; besoins limités aux repères de RC-9 (point 6), sans couverture pour une catégorie sans repère ; tables `fourrage.lot_animaux` et `ration*` du socle laissées intactes, `plan.*` fait foi ; import JSON du calculateur (T-28) en fin de lot sur des exemples réels à verser au dépôt (point ouvert 16) | Préparation du lot L5 |
| 17/10/2026 | Lot L5, étape 4 : besoins du socle (`ref.besoin_animal`) transcrits ligne par ligne dans `ref.besoin_journalier` (minimum par kg MS, maximum du cuivre ovin, « debut_lactation » → lactation, ligne sans stade → « tous », références reprises, « à valider ») ; BACA maximal de la vache tarie repris comme contrôle de ration ; seuils des contrôles de RC-9 en paramètres ; Ca/P hors cible hors cas particuliers : information ; encombrement non contrôlé (capacité d'ingestion absente) ; copie d'une période : composant « lot de stock » → « famille de stock », dates décalées d'un intervalle calendaire ; besoin dépendant d'une production non saisie : non calculé | Lot L5, étape 4 |
| 17/10/2026 | Lot L5, étape 5 : T-28 sur le format réel du calculateur versé au dépôt (contenu du stockage local, clé `gds-autonomie-fourragere-v1`, structure de `collect()`), stocks seuls, aperçu modifiable (famille des balles proposée d'après le libellé, forme), marge et période du calculateur reprises, équation herbe au-delà de 36 % refusée (le calculateur l'applique) ; jeu J1 : foin 2025 « 40 t MS déjà calculées » reproduit par 160 balles et les pertes qui y conduisent, teneurs d'essai de `calculs/` pour le foin 2025, l'enrubannage, le correcteur et le minéral, silo présent mais hors ration ; écart signalé : la couverture du § 9.1 (UFL 96 %, PDI, cuivre) est lue sur les besoins d'essai de `calculs/`, l'application sur les repères chargés (point ouvert 6) | Lot L5, étape 5 |
| 17/10/2026 | Lot L6, préparation : projection, sécurité, leviers, proposition et incertitude reproduisant `calculs/` (T-14 à T-16), pertes à la distribution par ration ; graphique de projection en SVG côté serveur (Chart.js abandonné, § 6.1 amendé) ; prix indicatifs de `calculs/` datés et marqués « à valider » (point 8) ; taux de substitution [NV] avec « référence à vérifier » (point 7) ; étape 3 de la proposition limitée au besoin résiduel (point 5) ; J1 : foin consommé par famille, foin 2026 analysé aux teneurs d'essai | Préparation du lot L6 |
| 17/10/2026 | Lot L6, revue avant enchaînement des étapes : silo de J1 dans un inventaire clos distinct ; scénario retenu → lot « achat » et mouvement par ligne d'achat, retirés si un autre scénario est retenu ; technicien prévenu = celui de la dernière visite, à défaut ceux du GDS ; incertitude des cas absents de RC-14 ([CP]) ; gain des leviers sans achat imputé à une famille de la ligne ; paramètres modifiables par élevage (pertes, MS, densité, marges, prix) ; alerte d'autonomie comptée depuis la date du jour (précisions au § 4.3) | Préparation du lot L6 |
| 17/10/2026 | Lot L6, réalisation : bilan recalculé par le job `CalculerBilan` (unique par inventaire, dispatché en fin de transaction) ; l'écran E14 refait le calcul à l'affichage, `plan.bilan` sert à l'accueil et aux alertes ; stock disponible du bilan = stocks de l'inventaire + achats, ventes et ajustements datés de la période (RC-13.4 : S' = S + Σ gains ; EF-M7-05), re-comptage hors apports ; repère d'action de EF-M6-03 (80 %) en paramètre `autonomie.repere_couverture` [C] ; repères de durée de RC-10 et mention de limites des PDF en textes types ; étapes 3 et 4 de la proposition enregistrées comme lignes « correction nutritionnelle » sans quantité ; lot « achat » de priorité de consommation maximale ; PDF du bilan et du scénario produits à la demande, non archivés ; E16 : fraction saisie en pourcentage, bornes contrôlées par unité | Lot L6, étapes 2 à 5 |
| 17/10/2026 | Lot L7, préparation (propositions par défaut validées par l'utilisateur) : partage administrateur / technicien, écran « Élevages » et journal d'audit ajoutés, E17 consentements repris, import SAGEMA avec aperçu, export rationnement en CSV à confirmer, archive RGPD et anonymisation, E24 étendu aux paramètres par défaut et aux textes types (autres référentiels au lot L8), indicateurs de pilotage (§ 3.13, précisions du lot L7) | Lot L7, étape 0 |
| 17/10/2026 | Lot L7, réalisation : code pseudonyme `ELV-nnnn` aussi pour les élevages importés de SAGEMA, département celui du GDS choisi ; compte sans aucun rattachement visible de tous les administrateurs ; un élevage par création ou import sans consentement au traitement des données (à cocher avant l'invitation) ; paramètre modifié deux fois le même jour : la ligne du jour est remplacée ; texte type : variables « {nom} » obligatoirement conservées ; nouvel éleveur : notice RGPD acceptée à la première connexion avant tout export ; indicateurs de pilotage calculés sur la première version publiée des fiches ; seuil de T-26 vérifié en exécution séquentielle | Lot L7, étapes 1 à 6 |
| 19/10/2026 | Lot L8, préparation : découpage en six étapes, k6 en conteneur pour T-42, déploiement Docker générique (hébergeur non choisi), purges de conservation paramétrables sans suppression automatique des dossiers (§ 9.3, précisions du lot L8) | Lot L8, étape 0 |
| 19/10/2026 | Lot L8, réalisation : CSP avec `'unsafe-eval'` (Alpine de Livewire 3) et styles en ligne (SVG) ; supervision et purges en paramètres datés ; T-25 exclut les citations de source (bibliographie, « INRA 2018 via Virbac » d'un repère non affiché du chapitre 11) ; seuil de T-26 vérifié en exécution séquentielle ; T-42 mesuré en développement (95e centile ≈ 1 s, machine partagée), à reprendre sur la recette ; brouillon local limité au formulaire de lot de stock et désactivé hors contexte sécurisé ; aliments de table non versionnés (schéma), journalisés ; super-administrateur initial créé par une commande ; version 1.0.0-rc.1 | Lot L8, étapes 1 à 6 |
| 22/10/2026 | Lecture AGDS par SAGEMA (EF-M9-03, § 6.4, cible « vue SQL ») : vues en lecture seule `focus.agds_elevage` et `focus.agds_bovin_present` exposées par SAGEMA (TASK-510) et compte dédié, plutôt qu'une API HTTP ou un accès direct au serveur AGDS ; volet « Données AGDS » réservé aux agents (`agds.lire`), lecture bornée au département de l'élevage, rien n'est recopié dans l'application ; composition affichée par sexe et tranche d'âge (bornes `agds.tranches_age_mois`, 12 et 24 mois, proposées), sans correspondance automatique avec `ref.categorie_animale` (à valider) | Phase de test, choix validé par l'utilisateur |

## 10.5 Références bibliographiques

Les références sont celles des documents 01, 02 et 05, auxquelles s'ajoutent les références techniques du chapitre 6. Le statut indique si la source a été consultée lors de la rédaction des documents antérieurs : **[consulté]** texte lu ; **[non consulté]** cité par une source secondaire ou ouvrage non accessible. Aucune référence nouvelle n'a été vérifiée pour ce document ; les statuts sont repris tels quels.

**Systèmes d'alimentation et besoins**

- INRA (coord. Nozière P., Sauvant D., Delaby L.). *Alimentation des ruminants*. Éditions Quae, Versailles, 2018, 728 p. ISBN 978-2-7592-2867-6. [non consulté — ouvrage payant ; référence de base des besoins UFL, PDI, capacité d'ingestion]
- Nozière P., Baumont R., Boudon A., Delagarde R., Hassoun P., Lemosquet S., Maxin G., Sepchat B., Delaby L. Le système d'alimentation INRA 2018 pour les ruminants. 1. Bases conceptuelles ; 2. Application au rationnement. *INRAE Productions Animales*, 39(2), 2026. https://productions-animales.org/ [consulté]
- INRAE, CIRAD, AFZ. *Tables INRAE-CIRAD-AFZ de composition et de valeur nutritive des matières premières*. https://www.feedtables.com/ [consulté]
- Meschy F. Alimentation minérale et vitaminique des ruminants : actualisation des connaissances. *INRA Productions Animales*, 20(2), 2007, p. 119-128. [consulté]
- Meschy F. *Nutrition minérale des ruminants*. Éditions Quae, 2010 (nouvelle édition 2023). [non consulté]
- NASEM. *Nutrient Requirements of Dairy Cattle*, 8th rev. ed. National Academies Press, 2021. https://doi.org/10.17226/25806 [secondaire]
- NRC. *Nutrient Requirements of Small Ruminants*. National Academies Press, 2007. https://doi.org/10.17226/11654 [secondaire]
- Morel I., Chassot A., Schlegel P., Jans F., Kessler J. Apports alimentaires recommandés pour la vache allaitante, chap. 8. Agroscope, 2017. [consulté]
- Benoit M., Veysset P. Calcul des Unités Gros Bétail : proposition d'une méthode basée sur les besoins énergétiques. *INRAE Productions Animales*, 34(2), 2021, p. 139-160. [consulté]
- Faverdin P., Dulphy J.-P., Coulon J.-B., Vérité R., Garel J.-P., Rouel J., Marquis B. Les phénomènes de substitution fourrages-concentrés chez la vache laitière. *INRA Productions Animales*. https://productions-animales.org/article/view/4227 [consulté — référence complète (année, volume) à vérifier avant diffusion externe]
- Kemp A., 't Hart M.L. Grass tetany in grazing milking cows. *Neth. J. Agric. Sci.*, 5, 1957, p. 4-17. [consulté]

**Réglementation et valeurs indicatives**

- EFSA FEEDAP Panel. Revision of the currently authorised maximum copper content in complete feed. *EFSA Journal*, 14(8):4563, 2016. [consulté]
- Règlements d'exécution (UE) 2018/1039 (cuivre), 2016/1095 (zinc), 2017/2330 (fer), 2015/861 (iode), 121/2014 (sélénium). JOUE. [consultés, 2018/1039 secondaire]
- Directive 2002/32/CE sur les substances indésirables dans les aliments pour animaux. JO L 140, 30.5.2002. [secondaire]
- Recommandation 2006/576/CE (DON, ZEA, OTA, fumonisines ; mod. 2016/1319) ; Recommandation 2013/165/UE (toxines T-2 et HT-2). [secondaire / consulté]
- Règlement (UE) 2016/679 (RGPD) ; Référentiel général d'amélioration de l'accessibilité (RGAA) 4.1, DINUM. [consultés]

**Interprétation des fourrages, conservation**

- AFSSA. *Bonnes pratiques de fabrication de l'ensilage pour une meilleure maîtrise des risques sanitaires*. 2004. [consulté]
- Chauveau H. (Arvalis). Qualité du maïs fourrage : comment interpréter son bulletin d'analyse ? arvalis.fr, 2024. [consulté]
- Rochereau T. Comprendre son analyse de fourrage — Note technique n° 11. Programme Herbe & Fourrages Centre-Val de Loire, 2024. [consulté]
- Chambre d'agriculture de l'Aveyron. Interpréter une analyse de fourrage. 2024. [consulté]
- Baumont R., Aufrère J., Meschy F. La valeur alimentaire des fourrages : rôle des pratiques de culture, de récolte et de conservation. *Fourrages*, 198, 2009, p. 153-173. [consulté]
- Schlegel P., Wyss U. Teneur en oligo-éléments des fourrages. *Rencontres Recherches Ruminants*, 20, 2013. [consulté]
- Guittard A.-L. (ARAD2 / Cerfrance Normandie Maine). Oligo-éléments, fourrages prairiaux et besoins des bovins. 2023. [consulté]
- Virbac. Importance des oligo-éléments — recommandations INRA 2018. pro-fr.virbac.com, 2022. [secondaire]
- Schaumann / Bonsilage. *Mémento d'ensilage d'herbe*. 2013. [consulté]
- LANO / Chambres d'agriculture de Normandie. Références Normandie : positionnement par quartiles. *L'Agriculteur Normand*, 27/09/2009. [consulté]
- Merck Veterinary Manual : McArt J.A.A., Parturient paresis in cows ; Stewart A.J., Hypomagnesemic tetany in cattle and sheep ; Fritz S., Copper poisoning in animals. 2026. [consultés]
- GDS 24. Campagne d'analyses 2025-2026 : 59 bulletins LANO, 17 élevages. Base interne, 2026.

**Stocks fourragers, densités, pertes, besoins en MS**

- Institut de l'Élevage. *Calcul du bilan fourrager : comment estimer les stocks ?* Fiche technique, 2 p. [consulté]
- Institut de l'Élevage. *Bilan fourrager — classeur de calcul*, version 2020 (équations de densité, coefficients UGB, 13 kg MS/UGB/j, poids de balles). [consulté]
- Corrot G. Actualisation des tables de densité des ensilages de maïs. *Annales de Zootechnie*, 44 (suppl. 1), 1995. [consulté]
- Fougère M., Thébault M. Densité des ensilages de maïs en Loire-Atlantique. Journées AFPF, 2011. [consulté]
- Seuret J.-M., Possémé B. *Bilan fourrager : faites vos comptes !* Chambre d'agriculture de Bretagne. [consulté]
- Chambre d'agriculture de Bretagne. Évaluer ses stocks de maïs ensilage dans les silos couloirs ; Réussir son chantier d'ensilage (2025, m.à.j. 2026). [consultés]
- Chambres d'agriculture de Normandie. Réaliser son bilan fourrager. [consulté]
- Chambre d'agriculture des Pays de la Loire. Déficit fourrager : faire le point au 15 juillet 2026 ; Vendre ou acheter de l'herbe sur pied ou récoltée en 2026 (étude PEREL). [consultés]
- Chambre d'agriculture de la Charente, Service Productions Animales. Classeur « Bilan fourrager simplifié » (base du classeur « Bilan fourrager avec rations »). [consulté]
- Herbe & Fourrages Centre-Val de Loire. Réaliser son bilan fourrager en toute simplicité. 2015. [consulté]
- Uijttewaal A., Moquet M. Et si le bon dimensionnement des silos était le meilleur conservateur ? Arvalis, 2020 ; Arvalis, Réussir l'enrubannage d'herbe, 2021. [consultés]
- Capitain M., Goron J.-P., Bonetti B. *Le bilan fourrager*. FRGDS Auvergne-Rhône-Alpes / Institut de l'Élevage, 2019. [consulté]
- Doligez P., Haeffner L. *Bilan fourrager*. IFCE, 2024. [consulté]
- Inosys – Réseaux d'élevage. Aléas climatiques, quels impacts et leviers ? Idele, 2020 ; Cas-type BV21, Massif central Sud, 2023. [consultés]
- Giraud B. Intégrer la paille dans les rations hivernales des bovins allaitants. PAMAC, 2020. [consulté]
- Léonard É. Éléments de référence – Bœuf Qualité Plus. FPBQ / CDAQ / MAPAQ, 2001. [consulté]
- Agreste Nouvelle-Aquitaine. Alimentation des bovins. *Analyses & Résultats* n° 68, 2019. [consulté]
- Muck R.E., Holmes B.J. Factors affecting bunker silo densities. *Applied Engineering in Agriculture*, 16(6), 2000 ; Density and losses in pressed bag silos, 2001 ; Holmes B.J., Muck R.E., Packing bunker and pile silos, *Focus on Forage*, 10(1), 2004. [consultés]
- Ruppel K.A., Pitt R.E., Chase L.E., Galton D.M. Bunker silo management and its relationship to forage preservation on dairy farms. *J. Dairy Sci.*, 78(1), 1995. [consulté, résumé]
- Borreani G., Tabacco E., Schmidt R.J., Holmes B.J., Muck R.E. Silage review: factors affecting dry matter and quality losses in silages. *J. Dairy Sci.*, 101(5), 2018, p. 3952-3979. [consulté]
- Bolsen R., Bolsen K.K. Managing forage for single digit shrink loss in bunker silos and drive-over piles. University of Florida IFAS, 2012. [consulté]
- Buskirk D.D., Zanella A.J., Harrigan T.M., Van Lente J.L., Gnagey L.M., Kaercher M.J. Large round bale feeder design affects hay utilization and beef cow behavior. *J. Anim. Sci.*, 81(1), 2003, p. 109-115. [consulté]
- Huhnke R.L. Round bale hay storage. Oklahoma State University Extension, BAE-1716, 2017 ; Lemus R. Hay storage: dry matter losses and quality changes. Mississippi State University Extension, P2540, 2023. [consultés]
- Blazek J., Johnson L., Wiegand O. Weigh your hay now, save money later. University of Wisconsin-Extension, 2017 ; Barkley M., Meinen R., Penn State Extension, 2025 ; Kallenbach R., University of Missouri Extension ; Banta J., Texas A&M AgriLife Extension, 2022 ; Schroeder J.W., NDSU Extension, 2012. [consultés]
- Halfman B. et al. Do you have enough forage? UW-Madison Extension, 2020 ; Halfman W., Plan ahead for a successful winter-feeding season, 2025 ; Chase L.E. et al., Cornell PRO-DAIRY, 2011 ; Gessner H., SDSU Extension ; Ishler V.A., Beck T., Penn State Extension, 2025 ; Amaral-Phillips D.M., University of Kentucky. [consultés]
- Köhler B. et al. (2013) ; Robinson P.H. et al. (2016). Pertes de MS en ferme. [non consultés — cités par Borreani et al. 2018]

**Mesures terrain et bilan sanitaire**

- Grilles de lecture PRITIH et NBVC (bilan de santé élevage allaitant), reprises dans la présentation « L'approche globale — exemple EARL Forgeneuf » (GDS 24, 2026). [statut [C] : source et droits d'usage à préciser, point ouvert n° 3 et 4]
- GDS Dordogne. Bilan sanitaire, campagne du 01/08/2025 au 31/07/2026. Base interne.

**Technique**

- Laravel 12, Livewire 3, Laravel Fortify, Laravel Socialite — documentation officielle, laravel.com et livewire.laravel.com. [consultés]
- spatie/laravel-permission ; spatie/browsershot — documentation, spatie.be. [consultés]
- Brevo. API transactionnelle e-mail et SMS — developers.brevo.com. [consulté]
- PostgreSQL 16, PostGIS 3.4 — documentation officielle. [consultés]
- ANSSI. Recommandations relatives à l'authentification multifacteur et aux mots de passe, 2021. [consulté]
- Charte graphique GDS 24 & GDS 47, version 2.0, 2025 ; cahier d'identité Focus Troupeau, octobre 2026. Documents internes.
