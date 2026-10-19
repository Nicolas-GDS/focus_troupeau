# 3. Spécifications fonctionnelles

Les exigences sont numérotées `EF-<module>-<n>`. Chacune est vérifiable par au moins un cas de recette (chapitre 9). Les règles de calcul auxquelles elles renvoient (`RC-n`) sont formalisées au chapitre 4.

## 3.1 Vue d'ensemble des modules

| Module | Nom | Utilisateurs principaux | Dépend de |
|---|---|---|---|
| M0 | Socle : comptes, GDS, conventions, consentements | Admin, technicien | — |
| M1 | Dossier élevage et résultats | Éleveur, technicien, vétérinaires | M0 |
| M2 | Interprétation, préconisations, plan d'action | Technicien, vét. conseil | M1 |
| M3 | Positionnement (références) | Éleveur, technicien | M1 |
| M4 | Stocks fourragers | Éleveur, technicien | M0 |
| M5 | Troupeau : lots d'animaux et rations | Éleveur, technicien | M1 (lots analysés), M4 |
| M6 | Autonomie : bilan, projection, date de rupture | Éleveur, technicien | M4, M5 |
| M7 | Plan d'approvisionnement : proposition et scénarios | Éleveur, technicien | M6 |
| M8 | Notifications | Tous | M2, M6 |
| M9 | Imports et exports | Technicien | M1, M5 |
| M10 | Administration et référentiels | Admin | — |

## 3.2 Exigences générales

| Réf. | Exigence |
|---|---|
| EF-GEN-01 | L'application est en français ; les unités suivent les conventions des bulletins LANO et de l'INRA 2018 (g/kg MS, mg/kg MS, UFL, PDI, mEq/kg MS, t MS, kg MS/j). |
| EF-GEN-02 | Toute valeur affichée à l'éleveur est accompagnée de son repère (seuil, besoin, position dans la campagne) et, au survol ou en dépliant, de la source du repère et de son statut épistémique. |
| EF-GEN-03 | Une classe d'état (couvert, vigilance, alerte, critique ; faible, normal, élevé) est toujours rendue par trois canaux : couleur, libellé, valeur. L'application reste lisible imprimée en noir et blanc et par un lecteur d'écran. |
| EF-GEN-04 | Aucun libellé généré par l'application ne nomme un produit commercial, une marque ou un fournisseur. Les champs libres saisis par l'éleveur (nom d'un minéral, d'un aliment) sont affichés comme tels, jamais dans une préconisation. |
| EF-GEN-05 | Toute donnée estimée (stock, densité, pertes, besoin par défaut) porte un indicateur « estimé / mesuré / analysé » et, pour les stocks, une incertitude affichée (RC-14). |
| EF-GEN-06 | Chaque objet métier porte `cree_le`, `maj_le`, `cree_par`, `maj_par` ; les objets publiés sont versionnés et jamais supprimés physiquement. |
| EF-GEN-07 | Les écrans sont utilisables sur téléphone (largeur 360 px) avec les mêmes fonctions ; la saisie des stocks et des lots est conçue d'abord pour le téléphone (grands champs, clavier numérique, une ligne à la fois). |
| EF-GEN-08 | L'utilisateur peut exporter toute page de résultat en PDF à la charte bi-entité (fiche éleveur, bilan d'autonomie, scénario), avec les mentions de limites et la version de la grille. |

## 3.3 M0 — Socle : comptes, GDS, conventions, consentements

| Réf. | Exigence |
|---|---|
| EF-M0-01 | L'instance héberge plusieurs GDS (`app.gds` : 24, 47, extensible). Chaque élevage, chaque convention et chaque technicien est rattaché à un ou plusieurs GDS ; un utilisateur ne voit jamais un élevage d'un GDS auquel il n'est pas rattaché (chapitre 6, cloisonnement). |
| EF-M0-02 | Le technicien crée l'élevage (ou l'importe depuis SAGEMA, EF-M9-03), saisit l'identité dans `prive.elevage_identite`, crée la convention, puis invite un ou plusieurs comptes éleveurs par e-mail. |
| EF-M0-03 | Connexion éleveur : e-mail + mot de passe (règles ANSSI : 12 caractères minimum, vérification contre les mots de passe compromis) **ou** lien de connexion envoyé par e-mail, valable 15 minutes, usage unique. Une double authentification par code e-mail est proposée (optionnelle). |
| EF-M0-04 | Connexion technicien, vétérinaire conseil et administrateur : Entra ID du tenant GDS (OpenID Connect via Laravel Socialite) ; l'appartenance aux GDS et le rôle sont attribués à la première connexion à partir des groupes Entra, puis gérés dans l'application. |
| EF-M0-05 | Connexion vétérinaire d'élevage : compte propre (comme l'éleveur), activé lorsque au moins un consentement actif le désigne. |
| EF-M0-06 | La convention porte dates, statut et GDS ; à l'échéance, l'accès de l'éleveur passe en lecture seule (D12). Le technicien reçoit un rappel 30 jours avant l'échéance. |
| EF-M0-07 | L'éleveur gère ses consentements « références » et « vétérinaire » (§ 2.5) depuis son espace ; chaque changement est journalisé. |
| EF-M0-08 | Un compte éleveur peut être rattaché à plusieurs élevages (GAEC multi-sites, salarié de deux structures) ; le sélecteur d'élevage est en tête d'écran. |

## 3.4 M1 — Dossier élevage et résultats

Le dossier est organisé par **visite d'approche globale** (une visite = une date, un technicien, un motif, un ensemble de prélèvements, mesures et observations) et par **campagne**. L'éleveur entre par la synthèse de sa dernière visite, puis navigue par volet.

| Réf. | Exigence |
|---|---|
| EF-M1-01 | La page d'accueil de l'éleveur affiche : la dernière visite (date, technicien), les messages hiérarchisés de la dernière interprétation publiée (cinq au plus, classés par gravité), l'état de l'autonomie (jours restants, date de rupture prévisionnelle, couverture), les actions du plan d'action qui lui incombent et leur échéance, les contrôles à venir. |
| EF-M1-02 | Volet **Fourrages** : liste des lots analysés (campagne, type, nom donné par l'éleveur, date de prélèvement, statut), avec pour chaque lot le tableau des valeurs (MS, UFL, PDI, BPR, MAT, NDF, minéraux, oligo-éléments, mycotoxines et conservation si dosés), la classe par paramètre (RC-1), la position campagne (RC-3), l'utilisation conseillée par lot d'animaux (saisie par le technicien). Le bulletin PDF original est téléchargeable. |
| EF-M1-03 | Volet **Matrice de vigilance minérale** : tableau lots × éléments (Cu, Zn, Mn, Na, Mg, K, Fe, MM, BACA, Se/I/Co si dosés) avec valeur et classe ; variante « ration » agrégeant fourrages, concentrés et minéral déclaré par lot d'animaux (RC-9). |
| EF-M1-04 | Volet **Eau** : analyses d'eau (point de prélèvement, date, paramètres physico-chimiques et bactériologiques), classées selon la grille eau d'abreuvement (`ref.seuil_interpretation` nature `eau`, à alimenter : voir § 4.1.3). |
| EF-M1-05 | Volet **Animaux** : profils sanguins (`fourrage.analyse_animale`) par date et lot d'animaux, avec la référence par élément et la lecture (carence sévère / carence / normal partie basse / normal / excès) ; mesures terrain par animal ou par lot (pH et redox des bouses, tamisage, pH, Brix et densité urinaires, glycémie, BOH, urée, NEC, remplissage du rumen, Brix du colostrum, protéines totales des veaux), avec les valeurs repères selon le stade (table `ref.repere_mesure_terrain`). |
| EF-M1-06 | Volet **Minéraux et aliments** : étiquettes des minéraux, bolus, seaux et aliments distribués (photo, composition par kg, dose déclarée, date limite), avec le calcul de l'apport journalier par élément et sa comparaison au besoin (RC-9). L'éleveur peut ajouter une étiquette ; le technicien la valide. |
| EF-M1-07 | Volet **Bilan sanitaire** : indicateurs importés ou saisis (intervalle vêlage-vêlage, mortalité des veaux, taux de non-délivrances, métrites, avortements, etc.), positionnés par rapport aux seuils S1/S2 de la grille du bilan de santé élevage (table `ref.seuil_bilan_sanitaire`), et les événements sanitaires déclarés (`fourrage.evenement_sanitaire`). |
| EF-M1-08 | Volet **Historique** : toutes les campagnes et visites de l'élevage ; pour chaque paramètre suivi, une courbe pluriannuelle (MAT et UFL des foins, Cu et Zn, alertes) avec les décisions prises (RC-4). |
| EF-M1-09 | Chaque résultat est rattaché à une visite et à une campagne ; la vue par visite reconstitue l'ordre des six étapes (collecter, analyser, mesurer, croiser, agir, suivre). |

## 3.5 M2 — Interprétation, préconisations, plan d'action

Le circuit est celui du document 03 : import et contrôle de cohérence, alertes automatiques, lecture du technicien, croisement avec le troupeau, restitution, suivi.

| Réf. | Exigence |
|---|---|
| EF-M2-01 | À l'import d'un bulletin (EF-M9-01), l'application exécute le contrôle de plausibilité (RC-2). Toute valeur hors plage bloque l'interprétation : le bulletin passe en statut `anomalie` avec le détail, et le technicien est notifié. |
| EF-M2-02 | Pour un bulletin `controle`, l'application génère les alertes automatiques (RC-1) en appliquant la grille active, et propose un niveau global (RC-1.4). Les alertes sont affichées au technicien avec le seuil, la source et le message proposé. |
| EF-M2-03 | Le technicien crée une interprétation : il retient ou écarte chaque alerte (avec motif), rédige la synthèse (3 à 5 lignes), la lecture sanitaire, la lecture nutritionnelle et les recommandations, fixe la catégorie cible et l'utilisation conseillée du lot, confirme ou modifie le niveau global, coche l'orientation vétérinaire. L'ordre imposé des trois lectures (propreté et conservation ; minéraux ; valeur nutritionnelle) structure le formulaire. |
| EF-M2-04 | Une interprétation de niveau 3 ne peut être publiée qu'après visa du vétérinaire conseil (statut `valide` → `visee` → `publie`). Les niveaux 0 à 2 sont publiés par le technicien. Le niveau qui compte est le niveau **retenu** par le technicien : retenir 2 sur un lot proposé au niveau 3 dispense du visa, le niveau proposé restant conservé et affiché (décision du 15/10/2026). Le vétérinaire conseil peut **refuser** le visa avec un motif : l'interprétation revient en brouillon chez le technicien, motif affiché (décision du 15/10/2026). |
| EF-M2-05 | L'**interprétation de visite** agrège les interprétations des lots, les lectures de l'eau, des profils sanguins, des mesures terrain et du bilan sanitaire en une synthèse unique : les messages hiérarchisés (cinq au plus), les causes communes identifiées (étape « croiser » : grille trouble × famille de cause, table `ref.grille_trouble_cause`), le niveau global de la visite. C'est cet objet qui est publié à l'éleveur. |
| EF-M2-06 | Le **plan d'action** est rattaché à l'interprétation de visite : actions priorisées (priorité 1, 2, 3), chacune avec un responsable (éleveur, technicien, vétérinaire), un « comment », un « quand » (date ou jalon du calendrier d'élevage : tarissement, 6 semaines avant vêlage, vêlage, mise à l'herbe…), un contrôle associé (mesure, cible, échéance). L'éleveur peut marquer une action « faite » ; le technicien saisit les résultats des contrôles. |
| EF-M2-07 | La publication crée une version figée de la fiche (HTML et PDF archivés, version de la grille mémorisée) et déclenche la notification EF-M8-01. Toute modification ultérieure crée une nouvelle version. Précisions du 16/10/2026 (§ 10.4) : la publication de la visite publie ses lots et ses objets validés ; une nouvelle version ne corrige que l'interprétation, la version publiée restant visible jusqu'à la publication de la suivante. |
| EF-M2-08 | Le technicien dispose d'un **tableau de bord** : bulletins à contrôler, bulletins en anomalie, interprétations en brouillon, interprétations à viser (niveau 3), fiches à publier, contrôles échus, conventions arrivant à échéance, élevages dont l'autonomie prévisionnelle passe sous le seuil d'alerte. Chaque ligne mène à l'objet. |
| EF-M2-09 | La **note de liaison vétérinaire** (document 04, § 4.1) est générée à partir de l'interprétation pour les niveaux 2-3 : élevage (avec consentement), lot d'animaux, troubles déclarés, tableau des alertes, question posée, retour attendu. Elle est envoyée au vétérinaire désigné (e-mail avec lien sécurisé) et archivée. |

## 3.6 M3 — Positionnement

| Réf. | Exigence |
|---|---|
| EF-M3-01 | Pour chaque lot analysé et chaque paramètre suivi (liste configurable : MS, MAT, UFL, PDI, BPR, NDF, Cu, Zn, Mn, Fe, MM, Ca, P, Mg, K, Na, BACA), l'application affiche la position du lot dans la population de référence : faible (< Q1), normal (Q1-Q3), élevé (> Q3), avec n, la médiane et les quartiles (RC-3). |
| EF-M3-02 | Trois populations de référence sont calculées, et affichées si n ≥ 5 : la campagne du GDS par type de fourrage (`valo.v_reference_locale`) ; la même population restreinte à la classe lithologique du prélèvement (`valo.v_reference_par_litho`) ; la population bi-GDS 24+47 par type (nouvelle vue), affichée seulement quand la population départementale est insuffisante. |
| EF-M3-03 | Le graphique de positionnement reprend celui du prototype (document 04, figure 4) : points de la campagne, bande Q1-Q3, médiane, lot de l'élevage mis en évidence, campagnes précédentes de l'élevage en rappel. |
| EF-M3-04 | Les références ne contiennent que des lots d'élevages ayant le consentement « références » actif, hors bulletins en anomalie et hors lots exclus par le comité technique (`fourrage.analyse.exclu_references`). |
| EF-M3-05 | L'historique pluriannuel (RC-4) compare, pour l'élevage, chaque paramètre à ses propres campagnes passées par type de fourrage : « ce qui a changé depuis l'an dernier ». |

## 3.7 M4 — Stocks fourragers

Le module reprend la fiche terrain (document 06) et le calculateur : un **inventaire** est daté, rattaché à l'élevage et à une période de référence ; il contient des **ouvrages** (silos) et des **lots de stock** (balles, bottes, vrac, silos). Les mouvements ultérieurs (achats, ventes, re-comptages, ajustements) modifient le stock sans effacer l'inventaire initial.

| Réf. | Exigence |
|---|---|
| EF-M4-01 | L'éleveur ou le technicien crée un inventaire : date, type (fin de récolte, mi-hiver, crise), période de référence (RC-10 : phases hivernage pleine ration, transition, complémentation estivale, avec les repères FRGDS AURA / Idele affichés). |
| EF-M4-02 | **Silos** : désignation, fourrage (maïs, herbe brins courts / longs, sorgho), ouvrage (couloir, taupinière), L1, L2, largeur à mi-hauteur, hauteur moyenne (nombre de mesures saisi), MS %, amidon % (ou % de grain), méthode de densité (équation, tamisage, carottage avec la valeur mesurée), date de fermeture (correction de tassement RC-5.3), pertes de conservation (défaut selon conduite). Calculs : volume, densité, stock brut, stock utile (RC-5, RC-6). Les quatre croquis de cubage du document 05 sont accessibles depuis le formulaire. |
| EF-M4-03 | **Balles, bottes, vrac** : fourrage (famille et type), format, nombre (ou m³), poids unitaire brut (pesé ou estimé : indicateur), MS % (défaut : foin 85, paille 90, enrubannage : analyse ou table), mode de stockage (pertes par défaut RC-6), pertes modifiables. Calcul du stock utile (RC-7). |
| EF-M4-04 | Chaque lot de stock peut être **relié à un prélèvement analysé** (`fourrage.prelevement`) : la MS et les valeurs nutritionnelles du bulletin remplacent alors les valeurs par défaut (indicateur « analysé »). Un lot non analysé reçoit des valeurs de table (`ref.aliment_reference`, statut « table ») et une incitation à l'analyser. |
| EF-M4-05 | **Mouvements** : achat (quantité, date, prix, fourrage), vente, re-comptage (nouveau nombre de balles ou nouvelles cotes du silo à une date), ajustement libre (motif). Le stock à une date est l'inventaire corrigé des mouvements et de la consommation projetée (RC-11). |
| EF-M4-06 | Le tableau de bord des stocks affiche par famille de fourrage (foin, regain, enrubannage, ensilage d'herbe, ensilage de maïs/sorgho, paille, autre) : stock utile, part analysée, incertitude (RC-14), dernière mise à jour. |
| EF-M4-07 | Les valeurs par défaut (pertes, MS, densités, marge) viennent de `app.parametre_defaut` ; une valeur modifiée par l'éleveur est enregistrée au niveau de l'élevage avec la valeur de référence affichée à côté (D10) ; le technicien voit la liste des écarts. |

## 3.8 M5 — Troupeau : lots d'animaux et rations

| Réf. | Exigence |
|---|---|
| EF-M5-01 | Un **lot d'animaux** porte : libellé, espèce, catégorie (`ref.categorie_animale`), effectif, poids vif moyen, production (lait kg/j ou GMQ) le cas échéant, et une ou plusieurs **phases** datées (stade physiologique : entretien, fin de gestation, préparation au vêlage, lactation, croissance ; effectif pouvant varier par phase). Les phases couvrent la période de référence sans chevauchement. |
| EF-M5-02 | Chaque phase porte une **ration** : liste de composants avec kg bruts ou kg MS par animal et par jour ; chaque composant référence soit un lot de stock (lui-même éventuellement analysé), soit un aliment de table (`ref.aliment_reference` : céréales, correcteurs, tourteaux, pulpes…), soit un minéral/étiquette déclaré (dose en g/j). Le pâturage est un composant « herbe pâturée » avec une part de la ration estimée. |
| EF-M5-03 | Pour chaque ration, l'application calcule et affiche : kg MS ingérés par animal et par jour ; apports en UFL, PDI, MAT, NDF, Ca, P, Mg, K, Na, Cu, Zn, Mn, Se, I, Co, BACA (si S et Cl connus) ; les ratios Ca/P, K/Mg, PDI/UFL ; la comparaison aux besoins de la catégorie et du stade (RC-8, RC-9) avec classe de couverture ; le besoin résiduel par élément en g ou mg par animal et par jour ; le contrôle des maxima (Cu ovins). |
| EF-M5-04 | La **voie UGB** (RC-8.1) reste disponible comme cadrage rapide : catégorie, coefficient UGB, effectif, jours → t MS. L'écart entre voie UGB et voie ration est affiché quand les deux existent (repère de cohérence). |
| EF-M5-05 | L'**affectation** fourrage → lot d'animaux est explicite (c'est la décision la plus concrète d'une analyse, document 04) : la vue « affectation » montre, par lot de stock, quels lots d'animaux le consomment et à quel rythme ; les utilisations conseillées par le technicien (EF-M1-02) sont rappelées en regard, et un écart est signalé (ex. : foin à K élevé affecté aux vaches en préparation au vêlage). |
| EF-M5-06 | Les lots et rations sont copiables d'une période à l'autre (« repartir de l'hiver dernier »). |

## 3.9 M6 — Autonomie : bilan, projection, date de rupture

| Réf. | Exigence |
|---|---|
| EF-M6-01 | Le bilan d'autonomie est calculé pour la période de référence de l'inventaire courant : stock utile total et par famille, besoins totaux et par famille (somme des rations, pertes à la distribution incluses, RC-8.3), stock de sécurité (RC-12), bilan, taux de couverture, autonomie en jours (RC-11). |
| EF-M6-02 | La **projection mensuelle** (RC-11) affiche, mois par mois, par famille de fourrage et pour le total : stock début, consommation, mouvements, stock fin ; la date de rupture prévisionnelle par famille et par lot de stock ; la date à laquelle le stock de sécurité est entamé. Le graphique superpose stock projeté et besoin cumulé. |
| EF-M6-03 | Le verdict est exprimé en trois indicateurs (document 05, § 7.1) : couverture (%), autonomie (jours ; date), déficit ou excédent (t MS), avec un repère d'action : au-dessus de 80 % de couverture, l'ajustement de ration suffit généralement ; en dessous, l'achat extérieur devient nécessaire. |
| EF-M6-04 | Chaque indicateur porte son incertitude (RC-14) : fourchette basse / haute du stock et de la date de rupture. |
| EF-M6-05 | Un changement d'inventaire, de mouvement, de lot ou de ration recalcule le bilan immédiatement (Livewire) ; le dernier bilan calculé est mémorisé avec sa date pour l'accueil et les notifications (EF-M8-03). |
| EF-M6-06 | Le bilan est exportable en PDF (tableaux, graphique, hypothèses et valeurs par défaut employées, sources). |

## 3.10 M7 — Plan d'approvisionnement : proposition et scénarios

| Réf. | Exigence |
|---|---|
| EF-M7-01 | Lorsque le bilan présente un déficit (en MS sur une famille, ou en couverture nutritionnelle sur un lot d'animaux), l'application génère une **proposition initiale** (RC-13) : réaffectation des lots de stock excédentaires, puis achats de fourrages, puis correction nutritionnelle par concentrés ou correcteurs, avec pour chaque ligne la quantité, le coût au prix saisi et l'effet sur le bilan. |
| EF-M7-02 | L'utilisateur peut dupliquer la proposition en **scénario**, y ajouter, modifier ou supprimer des **leviers** parmi le catalogue (achat de foin, d'enrubannage, de paille, de concentré avec taux de substitution, décapitalisation, génisses supprimées, pâturage allongé ou dérobée, changement de râtelier, autre), et comparer jusqu'à quatre scénarios côte à côte : coût total, couverture, date de rupture, couverture nutritionnelle des lots, points de vigilance. |
| EF-M7-03 | Les prix sont saisis par l'éleveur (ou le technicien) ; l'application propose des valeurs par défaut issues de `app.parametre_defaut` (prix indicatifs révisés par le GDS, datés) et les signale comme indicatives. |
| EF-M7-04 | Un levier a toujours un effet sur la MS **et** une conséquence nutritionnelle affichée (la paille baisse l'UFL moyenne ; le concentré se substitue au fourrage selon RC-13.3) ; la proposition ne doit jamais aboutir à une ration dont l'encombrement ou la couverture s'écarte des repères sans l'indiquer. |
| EF-M7-05 | Un scénario peut être **retenu** par l'éleveur (statut `retenu`) ; le technicien en est notifié ; ses achats, une fois réalisés, sont saisis comme mouvements de stock (EF-M4-05) qui mettent le bilan à jour. |
| EF-M7-06 | Aucune ligne de scénario ne nomme un produit : les concentrés sont décrits par catégorie et valeurs (ex. « correcteur azoté, 400 g PDI/kg MS »). |

## 3.11 M8 — Notifications

| Réf. | Exigence |
|---|---|
| EF-M8-01 | E-mail à l'éleveur à la publication d'une fiche de visite ou d'un résultat (objet, trois lignes, lien) ; copie au technicien. |
| EF-M8-02 | SMS à l'éleveur et au technicien pour toute interprétation publiée de niveau 3 (texte court : lot concerné, consigne immédiate, contact). |
| EF-M8-03 | Alerte d'autonomie : e-mail à l'éleveur et au technicien lorsque la date de rupture prévisionnelle passe à moins de N jours (N = 60 par défaut, paramétrable) ou lorsque le stock de sécurité est entamé ; une seule alerte par franchissement. |
| EF-M8-04 | Rappels au technicien : contrôles échus, conventions à échéance, bulletins en anomalie non traités depuis 7 jours. |
| EF-M8-05 | Toutes les notifications passent par Brevo (API transactionnelle e-mail et SMS), sont journalisées (`app.notification` : destinataire, canal, objet, statut d'envoi) et respectent les préférences de l'utilisateur (désinscription possible sauf pour le niveau 3). |

## 3.12 M9 — Imports et exports

| Réf. | Exigence |
|---|---|
| EF-M9-01 | **Import des bulletins LANO** : dépôt d'un ou plusieurs PDF ; extraction structurée (chaîne `parse_lano.py` existante, pilotée par un job) ; rapprochement avec un prélèvement existant par numéro d'échantillon ou numéro client, sinon création d'un prélèvement à compléter ; contrôle de plausibilité (RC-2) ; stockage du PDF et de son empreinte SHA-256. Un export structuré du laboratoire, lorsqu'il sera conventionné, remplacera l'extraction PDF sans changer la suite du circuit. |
| EF-M9-02 | **Saisie manuelle** des bulletins d'autres laboratoires (eau, profils sanguins, mycotoxines) avec les mêmes contrôles. |
| EF-M9-03 | **Import SAGEMA** : fichier d'export (CSV ou vue) des élevages adhérents (EDE, raison sociale, coordonnées, espèces, effectifs) et des indicateurs du bilan sanitaire par campagne ; rapprochement par EDE ; aucune écriture vers SAGEMA. La structure d'échange est décrite au chapitre 6. |
| EF-M9-04 | **Export rationnement** : fichier CSV/Excel des valeurs INRA 2018 des lots (MS, UFL, PDI, BPR, NDF, minéraux) au format d'import INRAtion V5 / Rumin'Al (document 04, outil J). |
| EF-M9-05 | **Export des données de l'élevage** (RGPD, portabilité) : archive ZIP contenant les fiches PDF, les bulletins et un export CSV des données structurées de l'élevage. |
| EF-M9-06 | **Reprise de l'existant** : chargement de la campagne 2025-2026 (59 bulletins, script `03_chargement_campagne_2025-2026.sql`) et import des calculs d'autonomie réalisés avec le calculateur HTML (format JSON `gds-autonomie-fourragere-v1`). |

## 3.13 M10 — Administration et référentiels

| Réf. | Exigence |
|---|---|
| EF-M10-01 | Gestion des GDS, des utilisateurs, des rôles, des rattachements, des conventions ; journal des connexions et des actions sensibles (publication, révocation de consentement, modification de référentiel). |
| EF-M10-02 | Édition **versionnée** des référentiels : grille de seuils (`ref.seuil_interpretation`), besoins (`ref.besoin_animal`, `ref.besoin_journalier`), repères des mesures terrain, seuils du bilan sanitaire, aliments de table, paramètres par défaut. Chaque modification porte auteur, date, commentaire (décision du comité technique) ; les interprétations publiées conservent la version de grille utilisée. |
| EF-M10-03 | Édition des textes types : messages proposés par seuil, gabarits de recommandations, mentions de limites, notice RGPD. |
| EF-M10-04 | Indicateurs de pilotage du service (document 03, § 6.5) : activité, délais prélèvement → fiche publiée, part des bulletins en anomalie, part des alertes retenues, orientations vétérinaires, couverture des références (types avec n ≥ 5). |

### Précisions du lot L7 (décisions du 17/10/2026, propositions [CP] validées par défaut)

- *Partage des rôles* (§ 2.3 et § 2.4, qui divergent) : l'administrateur gère les GDS, les comptes (rôles, rattachements, activation), les conventions (y compris la résiliation), les consentements et consulte le journal d'audit (`/admin/journal`, ajouté). Le technicien crée l'élevage, son identité et sa convention, prolonge une convention et invite les comptes éleveurs des élevages de son périmètre (EF-M0-02, § 2.4), depuis un écran « Élevages » (`/elevages`, ajouté, technicien et administrateur) ; il ne modifie ni les rôles ni les rattachements aux GDS.
- *Comptes* : un compte n'est jamais supprimé (désactivation) ; les agents connectés par Entra ID gardent leurs groupes à la connexion, l'administrateur peut compléter rôles et GDS ; toute modification de rôle, de rattachement ou d'activation est journalisée. Rappel d'échéance de convention (EF-M0-06) : e-mail au technicien de la dernière visite (à défaut, aux techniciens du GDS) `convention.rappel_jours` avant la date de fin, une fois par convention, par la tâche nocturne.
- *Consentements* (E17, EF-M0-07, resté à faire au lot L3) : l'éleveur accorde ou révoque « références » et « vétérinaire » (vétérinaire choisi parmi les comptes actifs du rôle vétérinaire), chaque changement journalisé ; l'administrateur les voit dans la fiche de l'élevage.
- *Import SAGEMA* (EF-M9-03, T-27) : deux fichiers CSV séparés par « ; », encodage UTF-8 (ou Windows-1252 détecté), colonnes du § 6.4 ; rapprochement par EDE dans le périmètre du technicien ; un élevage absent est créé (code pseudonyme `ELV-nnnn` comme à la création manuelle, GDS choisi par l'importateur, département de ce GDS ; commune rapprochée de `sig.commune` par son nom) ; une valeur vide ne remplace pas une valeur existante ; aperçu avant écriture puis rapport (créés, mis à jour, inchangés, rejetés avec motif) ; indicateurs du bilan sanitaire écrits avec la source `sagema` (une saisie manuelle n'est pas écrasée) ; aucune écriture vers SAGEMA. Lecture planifiée d'un partage réseau : hors lot (adaptateur prévu).
- *Export rationnement* (EF-M9-04) : le format d'import d'INRAtion V5 et de Rumin'Al n'est pas décrit dans les documents ; CSV séparé par « ; », virgule décimale, UTF-8 avec BOM, une ligne par lot analysé non annulé (bulletins publiés pour l'éleveur), colonnes : lot, type, campagne, date de prélèvement, bulletin, MS puis les valeurs INRA 2018 disponibles (UFL, UFV, PDI, BPR, NDF, minéraux) avec l'unité des bulletins en en-tête ; à confirmer sur un fichier d'exemple (point ouvert).
- *Export RGPD* (EF-M9-05) : archive ZIP produite à la demande (éleveur pour son élevage, technicien, administrateur), contenant les fiches de visite publiées (PDF figés), les PDF des bulletins, le bilan d'autonomie en PDF s'il existe et un CSV par famille de données (identité, conventions, consentements, prélèvements et résultats, interprétations publiées, visites, stocks, troupeau, scénarios) ; journalisée. *Anonymisation* sur demande (§ 2.4) : action de l'administrateur, confirmée, qui vide les champs de `prive.elevage_identite` (raison sociale remplacée par le code pseudonyme), anonymise et désactive les comptes éleveurs rattachés à ce seul élevage, résilie la convention ; les données pseudonymisées restent ; journalisée, irréversible.
- *Référentiels* (EF-M10-02, EF-M10-03) : E24 s'étend aux paramètres par défaut (`app.parametre_defaut` : nouvelle ligne datée du jour, l'ancienne close la veille, valeur nationale ou propre au GDS) et aux textes types (version du référentiel, comme la grille) ; l'édition des besoins, des repères des mesures terrain et des profils sanguins, des seuils du bilan sanitaire (versionnés) et des aliments de table (journalisés, non versionnés au schéma) est faite au lot L8 ; la validation de leur contenu reste au comité technique (points ouverts 3, 5, 6).
- *Pilotage* (E25, EF-M10-04, document 03 § 6.5 non versé) : indicateurs par GDS et par campagne, calculés à la volée : activité (élevages suivis, visites, bulletins, fiches publiées), délai médian prélèvement → fiche publiée, part des bulletins en anomalie, part des alertes automatiques retenues, orientations vétérinaires (notes de liaison, interprétations de niveau 3), couverture des références (types de fourrage avec n ≥ `references.n_min`) ; export CSV des indicateurs et des références agrégées (quartiles, n ≥ n_min, sans identité). Ouvert à l'administrateur et au technicien (son GDS).

## 3.14 Parcours utilisateurs de référence

**P1 — Restitution d'une visite (technicien → éleveur).** Visite créée ; prélèvements et mesures saisis sur le téléphone ; bulletins importés à réception ; contrôle de cohérence ; alertes automatiques ; interprétations des lots ; interprétation de visite avec causes communes ; plan d'action ; visa vétérinaire si niveau 3 ; publication ; notification ; restitution commentée en visite ; l'éleveur consulte, marque les actions faites ; contrôles saisis à l'échéance.

**P2 — Bilan fourrager à la mise en stock (éleveur, puis technicien).** L'éleveur crée l'inventaire sur son téléphone depuis le silo (cotes, MS de l'analyse, méthode de densité), compte ses balles ; relie les lots aux analyses ; décrit ses lots d'animaux et leurs rations pour l'hiver ; lit le bilan, la projection et la date de rupture ; le technicien vérifie en visite, corrige, et commente la proposition d'approvisionnement.

**P3 — Mi-hiver.** L'éleveur recompte (mouvement « re-comptage ») ; la projection se recale ; une alerte d'autonomie part si la rupture se rapproche ; il compare deux scénarios (achat de foin contre vente anticipée) et en retient un.

**P4 — Bilan de campagne (technicien).** Au printemps, le technicien consulte les indicateurs de pilotage, exporte les références (n ≥ 5), prépare la révision de la grille ; l'administrateur saisit la nouvelle version après le comité technique.

## 3.15 Inventaire des écrans

| Code | Écran | Rôle | Contenu principal |
|---|---|---|---|
| E01 | Connexion / activation / lien de connexion | Tous | Formulaires d'authentification, choix GDS implicite |
| E02 | Accueil éleveur | Éleveur | Messages, autonomie, actions, contrôles, sélecteur d'élevage |
| E03 | Dossier — visite | Éleveur, tech. | Synthèse de la visite en six étapes |
| E04 | Fourrages — liste et détail d'un lot | Éleveur, tech. | Valeurs, classes, position, utilisation conseillée, PDF |
| E05 | Matrice de vigilance minérale | Éleveur, tech. | Lots × éléments ; variante ration |
| E06 | Eau | Éleveur, tech. | Analyses d'eau et lecture |
| E07 | Animaux : profils sanguins et mesures terrain | Éleveur, tech., vét. | Tableaux par date, repères par stade |
| E08 | Minéraux et aliments | Éleveur, tech. | Étiquettes, apports calculés, comparaison aux besoins |
| E09 | Bilan sanitaire et événements | Éleveur, tech. | Indicateurs vs seuils, événements déclarés |
| E10 | Historique pluriannuel | Éleveur, tech. | Courbes par paramètre, décisions |
| E11 | Plan d'action et contrôles | Éleveur, tech., vét. | Actions par priorité, statut, contrôles |
| E12 | Stocks — inventaire (silos, balles, mouvements) | Éleveur, tech. | Formulaires mobiles, calculs en ligne, croquis |
| E13 | Troupeau — lots, phases, rations | Éleveur, tech. | Composants, apports, couverture, affectation |
| E14 | Autonomie — bilan et projection | Éleveur, tech. | Trois indicateurs, tableau mensuel, graphique, incertitude |
| E15 | Approvisionnement — proposition et scénarios | Éleveur, tech. | Lignes de levier, comparaison, scénario retenu |
| E16 | Paramètres de l'élevage | Éleveur, tech. | Valeurs par défaut et écarts |
| E17 | Consentements et compte | Éleveur | Consentements, préférences de notification, mot de passe |
| E18 | Tableau de bord technicien | Technicien | Files de travail |
| E19 | Import de bulletins | Technicien | Dépôt, rapprochement, anomalies |
| E20 | Interprétation d'un bulletin | Technicien | Alertes, trois lectures, niveau, catégorie cible |
| E21 | Interprétation de visite et publication | Technicien, vét. conseil | Messages, causes communes, plan d'action, visa, publication |
| E22 | Note de liaison vétérinaire | Technicien | Génération et envoi |
| E23 | Administration : GDS, comptes, conventions | Admin | Gestion |
| E24 | Administration : référentiels versionnés | Admin | Édition et historique |
| E25 | Indicateurs de pilotage | Admin, tech. | Tableaux et exports |
