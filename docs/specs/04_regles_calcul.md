# 4. Règles métier et règles de calcul

Chaque règle porte un identifiant `RC-n`, ses entrées, sa formule, ses valeurs par défaut, sa source et le statut épistémique de cette source. Les statuts reprennent la convention des documents 01 et 05 : **[T]** valeur lue dans la source primaire ; **[V]** source vérifiée en ligne ; **[M]** valeur mesurée publiée ; **[C]** consensus professionnel ou estimation ; **[CP]** convention proposée pour le GDS, à valider par le comité technique ; **[NV]** référence non vérifiée. Une implémentation de référence en Python, avec ses tests, accompagne ce chapitre (dossier `calculs/`) ; le code PHP doit reproduire ses résultats à 10⁻⁶ près.

Toutes les constantes citées sont des **valeurs par défaut** stockées dans `app.parametre_defaut` ou dans les tables `ref.*` ; aucune n'est codée en dur.

## 4.1 Interprétation des analyses

### RC-1 — Classement d'une valeur et alertes automatiques

**Entrées** : un résultat (`parametre_code`, `valeur`, `censure`), le type et la famille du fourrage, l'espèce et la catégorie animale cible (facultatives), la version active de `ref.seuil_interpretation`.

**Règle** (fonction `ref.f_classer_valeur` existante, portée en PHP dans `Domain\Interpretation\Classeur`) : la valeur est classée par le premier seuil actif dont l'intervalle `[borne_min ; borne_max[` la contient, en parcourant les seuils du plus spécifique au plus général selon l'ordre : (type × espèce × catégorie) > (type × espèce) > (type) > (famille × espèce × catégorie) > (famille) > (tous fourrages × espèce × catégorie) > (tous fourrages). Une borne `NULL` est non bornée. Un résultat censuré `<` est classé sur sa limite de quantification avec le drapeau `censure` reporté dans l'alerte.

**Départage et cohérence de la grille** (décision du 15/10/2026) : cet ordre de priorité fait foi ; il diffère de celui de la fonction SQL `ref.f_classer_valeur` du document 02, qui place la catégorie avant la famille — l'application suit le présent texte. À précision égale, le seuil de gravité la plus forte l'emporte. L'édition des référentiels (E24) refuse l'enregistrement de deux seuils actifs d'un même paramètre dont les intervalles se chevauchent au même niveau de précision (même famille, type, espèce et catégorie), de sorte que ce départage ne serve qu'en dernier recours.

Chaque classement de gravité ≥ 1 produit une alerte automatique (`fourrage.alerte`, origine `automatique`, `retenue = true` par défaut) avec le message du seuil, la référence et le statut épistémique. Les seuils de gravité 0 ne produisent pas d'alerte mais alimentent l'affichage de la classe.

**Ratios dérivés** calculés avant classement, si les composants sont présents : `CA_P = CA / P` ; `K_CAMG_EQ = (K/39,1) / (CA/20,04 + MG/12,15)` (équivalents) ; `PDI_UFL = PDI / UFL_2018` ; `BACA = (NA/23 + K/39,1) − (CL/35,5 + S/16) × 1000` en mEq/kg MS, calculée seulement si S et Cl sont dosés, sinon affichée comme « BE partielle (Na + K − Cl) » ou non calculée. Source des facteurs : masses atomiques ; formule BACA : Meschy 2007 [V].

### RC-1.4 — Niveau global proposé

`niveau_propose = max(gravite des alertes retenues)`, avec deux rehaussements proposés (jamais imposés) : si un événement sanitaire compatible avec le paramètre en alerte est enregistré pour l'élevage dans les 12 mois précédant l'interprétation (table `ref.compatibilite_parametre_trouble`), un niveau 1 devient 2 ; si le lot est destiné à des ovins et qu'une valeur de cuivre dépasse 15 mg/kg MS, le niveau est 3 (règle réglementaire, EFSA 2016 [V]). Le technicien confirme ou modifie le niveau ; le niveau retenu et le niveau proposé sont tous deux conservés.

**Compatibilité** (décision du 15/10/2026, d'après le chapitre 11) : un lien de `ref.compatibilite_parametre_trouble` est retenu si (1) son paramètre est celui de l'alerte ; (2) son sens correspond au sens du seuil classant (colonne `sens` de `ref.seuil_interpretation` : un seuil `bas` appelle les liens `carence` ou `ratio_bas`, un seuil `haut` les liens `exces` ou `ratio_eleve`) ; (3) son code de trouble est le `type_evenement` de l'événement ; (4) l'espèce de l'événement figure parmi ses espèces (toutes si non précisé), et sa catégorie parmi ses catégories quand l'une et l'autre sont renseignées ; (5) sa force atteint le minimum `interpretation.force_min_rehaussement` (2 par défaut [CP]). Les liens de force 1 ne rehaussent pas ; ils alimentent la lecture « causes communes » de l'interprétation de visite (EF-M2-05). La fenêtre de 12 mois est le paramètre `interpretation.fenetre_evenements_mois`.

### RC-2 — Contrôle de plausibilité à l'import

Un bulletin est bloqué (statut `anomalie`) si une valeur sort des plages ci-dessous. Les plages sont volontairement larges : elles détectent les erreurs d'extraction et les contaminations, pas les fourrages médiocres. Elles sont stockées dans `ref.plage_plausibilite` et **à valider par le comité technique** [CP] ; le bulletin HA25-42515 de la campagne 2025-2026 (Na 13,6 g, Cu 57,9 mg, Mn 1 765 mg, Fe 9 012 mg par kg MS) doit être bloqué par ces plages (cas de recette T-02).

| Paramètre | Plage acceptée (par kg MS sauf MS) | Statut |
|---|---|---|
| MS (%) | foin 75–95 ; paille 80–95 ; enrubannage 25–80 ; ensilage d'herbe 18–60 ; ensilage de maïs 22–45 ; vert 10–35 | [CP] |
| MM (g) | 20–200 | [CP] |
| MAT (g) | 25–300 | [CP] |
| UFL 2018 | 0,35–1,15 | [CP] |
| PDI (g) | 20–200 | [CP] |
| Ca, P, Mg, K, Na (g) | Ca 1–25 ; P 0,5–8 ; Mg 0,5–6 ; K 2–45 ; Na 0–6 | [CP] |
| Cu (mg) | 0–30 (au-delà : seuil `valeur_anormale` gravité 3, Meschy 2007 [V]) | [CP] |
| Zn, Mn (mg) | Zn 5–150 ; Mn 10–400 | [CP] |
| Fe (mg) | 20–3 000 | [CP] |

Une valeur hors plage ne détruit rien : le bulletin est conservé, le résultat porte `flag_anomalie`, le technicien demande une vérification au laboratoire ou un re-prélèvement, puis lève l'anomalie (avec motif) ou annule le bulletin. Un bulletin en anomalie est exclu des références.

### RC-3 — Positionnement par quartiles

**Entrées** : population de référence `R` (lots du même type de fourrage, de la même campagne, du même GDS ; ou de la même classe lithologique ; ou des deux GDS), paramètre `p`, valeur `v` du lot.

**Règle** : si `n(R) ≥ 5`, calculer Q1, médiane, Q3 (méthode des quantiles de PostgreSQL `percentile_cont`, interpolation linéaire) et classer : `faible` si `v < Q1`, `normal` si `Q1 ≤ v ≤ Q3`, `eleve` si `v > Q3`. Sinon, afficher « référence insuffisante (n = …) ». Le lot lui-même fait partie de la population (comme dans `valo.v_reference_locale`). Population : lots d'élevages consentants, bulletins au statut `valide`, non exclus. Source du principe : Références Normandie du LANO (quartiles, > 8 500 analyses) [V].

**Ordre d'affichage** : campagne départementale ; puis région pédo-géologique si `n ≥ 5` ; puis bi-GDS seulement si la population départementale est insuffisante. Le seuil 5 est un paramètre (`references.n_min`).

### RC-4 — Historique pluriannuel

Pour un élevage, un type de fourrage et un paramètre : série des valeurs par campagne (médiane des lots de l'élevage si plusieurs lots du même type). Variation affichée entre la campagne courante et la précédente, en valeur et en pourcentage, avec un libellé neutre (« + 12 g MAT ») ; aucune classe n'est attribuée à une variation (une hausse de K n'est pas « bonne »). Les décisions enregistrées (recommandations, actions du plan) sont affichées sur la même frise.

### RC-1.5 — Lecture des profils sanguins et des mesures terrain

Les profils sanguins (`fourrage.analyse_animale`) sont lus par comparaison à `ref.repere_analyse_animale` (élément, matrice, espèce, borne basse, borne haute, source) : `carence_severe` sous 50 % de la borne basse, `carence` sous la borne basse, `normal_bas` dans le quart inférieur de l'intervalle, `normal`, `exces` au-dessus de la borne haute. Les mesures terrain sont lues par comparaison à `ref.repere_mesure_terrain` (mesure, stade, borne basse, borne haute) ; les valeurs de repère initiales sont celles du support de la présentation Forgeneuf (grilles PRITIH / NBVC) avec statut [C], à faire valider et sourcer par le vétérinaire conseil avant mise en service. L'application n'attribue aucun diagnostic : elle produit un libellé de lecture (« déficit énergétique probable ») repris de `ref.grille_lecture_mesure`, que le technicien retient ou écarte comme une alerte.

## 4.2 Stocks

### RC-5 — Volume et densité des silos

**Volume** (document 06, § 2.1) : `V = (L1 + 0,5 × L2) × l × h` avec `L1` longueur à hauteur pleine, `L2` longueur de la pente, `l` largeur mesurée à mi-hauteur (sur un côté sans mur : jusqu'au milieu de la pente), `h` moyenne d'au moins trois hauteurs (cinq en taupinière). Source : Idele, fiche « estimation des stocks » [T].

**Correction de tassement** (RC-5.3) si le silo est fermé depuis moins de trois semaines : `V × (1 − c)` avec `c = 0,05` à 10 jours, `0,03` à 20 jours si MS > 26 % ; environ le double si MS < 22 % ; interpolation linéaire entre les points, 0 au-delà de 21 jours. Source : Idele [T].

**Densité en kg MS/m³** (Idele, classeur bilan fourrager 2020, équations lues dans le classeur [T]) :

| Fourrage et ouvrage | Équation (H hauteur m, MS en %, A amidon en % MS) |
|---|---|
| Maïs, silo couloir | `D = (929 − 17,5·MS + 7,9·A + 44·H) × MS/100` |
| Maïs, taupinière | `D = (874 − 14,5·MS + 4,2·A + 48·H) × MS/100` |
| Sorgho plante entière | densités maïs × 1,20 |
| Herbe, brins courts | `D = 30·H + 2·MS + 60` |
| Herbe, brins longs | `D = 27·H + 1,8·MS + 54` |
| Herbe, silo couloir (à défaut) | `D = 16·H + 5·MS + 34` |
| Herbe, taupinière (à défaut) | `D = 7·H + 3,7·MS + 74` |

Amidon inconnu : `A = 28` ; si le pourcentage de grain `G` est connu et > 20 : `A = 1,134·G − 22,5` [T]. Les équations herbe ne sont pas appliquées au-delà de 36 % de MS (valeurs invraisemblables) : l'application exige alors une densité mesurée ou une valeur saisie (document 05, vigilance n° 2). Priorité des méthodes : carottage (densité mesurée saisie) > tamisage (densité saisie) > équation.

**Stock utile du silo** : `S = V × D / 1000 × (1 − p_conservation)` en t MS.

### RC-6 — Pertes par défaut

| Poste | Défaut | Source |
|---|---|---|
| Conservation, silo bien conduit | 5 % | organismes techniques français [C] |
| Conservation, silo qui chauffe, reprise estivale ou lente | 12 % | milieu de 10–15 % [C] ; 10 % mesurés en ferme sur maïs [M] |
| Stockage des balles : bâtiment fermé 2 % ; sous toit 3 % ; bâché surélevé 4 % ; bâché au sol 8 % ; exposé surélevé 10 % ; exposé au sol 15 % | selon mode | Huhnke 2017, Lemus 2023 (0–9 mois) [M] |
| Distribution : auge ou râtelier à cône 4 % ; râtelier à anneau 6 % ; remorque distributrice 11 % ; déroulé au sol ou râtelier berceau 13 % ; anneau sans panneau 20 % | selon équipement, défaut 13 % | Buskirk et al. 2003 [M] ; Lemus 2023 |

Les pertes de conservation et de stockage s'appliquent au stock (RC-5, RC-7) ; les pertes de distribution s'appliquent aux besoins (RC-8.3). Une perte « 0 — déjà comptée dans la ration » est possible quand la ration a été pesée à l'auge.

### RC-7 — Stock utile des balles, bottes et vrac

`S = N × P_brut × MS/100 × (1 − p_stockage) / 1000` en t MS, avec `N` nombre de balles (ou m³ pour le vrac, `P_brut` étant alors la densité en kg/m³, 80–100 pour le foin en vrac [T]), `P_brut` poids brut unitaire (pesé : indicateur `mesure` ; estimé : indicateur `estime`, incertitude RC-14). MS par défaut : foin 85 %, paille 90 % [T] ; enrubannage : analyse obligatoire ou, à défaut, saisie avec incertitude forte (28 à 67 % observés, document 05) ; ensilage en balles : analyse. Si le lot est relié à un prélèvement analysé, la MS du bulletin remplace la valeur par défaut.

## 4.3 Besoins et apports

### RC-8 — Besoins en matière sèche

**RC-8.1 Voie UGB** : `B = Σ (effectif × coeff_UGB) × 13 × jours / 1000` en t MS. Coefficients (Idele 2020 [T]) : vache laitière 1,02 (grille 0,88–1,41 selon lait et concentrés, affichée) ; vache allaitante 0,90 ; génisse < 1 an 0,30 ; 1–2 ans 0,60 ; > 2 ans 0,80 ; mâle 1–2 ans 0,60 ; taurillon 0,80 ; taureau reproducteur 1,00 (hors référentiel Idele [C]) ; brebis 0,15 ; chèvre 0,20 ; jument suitée 1,25. Durée par défaut : 130 jours (classeur Idele) ; valeur GDS proposée pour la plaine : 160 jours (FRGDS AURA / Idele 2019 [V]) — voir RC-10.

**RC-8.2 Voie ration** : `B = Σ_lots Σ_phases effectif × (Σ_composants kg MS fourrage) × jours_phase / 1000`. L'ingestion est celle de la ration saisie ; des repères d'ingestion sont affichés pour contrôle (document 05, § 6.3 : vache laitière 16 (défaut Idele [T]) ; vache allaitante suitée 13–16, tarie 10–14 [V] ; génisse < 1 an 4, 1–2 ans 8, > 2 ans 12 [T] ; jeune bovin 9–12 [V]). La règle « 2 à 2,5 % du poids vif » peut être affichée comme repère pédagogique, jamais comme référence (document 05, précaution n° 1).

**RC-8.3 Pertes à la distribution** : besoin prélevé sur le stock `B_stock = B_ration / (1 − p_distribution)` pour les composants provenant d'un stock (RC-6). Les concentrés et minéraux ne portent pas de perte de distribution.

**Cohérence** : quand les deux voies sont renseignées, l'écart `|B_UGB − B_ration| / B_UGB` est affiché ; au-delà de 20 %, un message invite à vérifier les ingestions saisies [CP].

### RC-9 — Apports et couverture nutritionnelle par lot d'animaux

**Apports par animal et par jour**, pour chaque phase et chaque élément `e` : `A_e = Σ_composants kgMS_c × teneur_e(c)`, la teneur venant du bulletin d'analyse (composant relié à un prélèvement, indicateur `analyse`), de `ref.aliment_reference` (indicateur `table`, source Tables INRAE-CIRAD-AFZ [V]) ou de l'étiquette saisie pour un minéral (teneur par kg × dose g/j, indicateur `etiquette`). Éléments calculés : UFL_2018, PDI, MAT, NDF, UEB/UEL/UEM, Ca, P, Mg, K, Na, Cu, Zn, Mn, Se, I, Co, S, Cl, BACA. Les concentrations de la ration par kg MS sont `A_e / MSI`.

**Besoins** : `ref.besoin_animal` (existant, minéraux par kg MS de ration) et `ref.besoin_journalier` (nouvelle table : UFL et PDI par jour selon catégorie, stade, poids vif et production, chargée depuis INRA 2018 et les repères déjà saisis — VL : 0,42 UFL et 48 g PDI par kg de lait TB 40 + entretien 5,3 UFL/j à 650 kg [V, ordre de grandeur] ; brebis entretien 0,8 UFL et 60 g PDI/j à 70 kg [C] ; chèvre entretien 0,88 UFL/j à 60 kg [V]). Pour les vaches allaitantes, le référentiel « Repères alimentaires » du classeur bilan fourrager (fiches « Alimentation vaches allaitantes », concentration par kg MS selon le stade : tarissement 0,70–0,75 UFL, 100–110 g MAT ; préparation au vêlage 0,75–0,85 UFL ; lactation 0,72–0,80 UFL, 120–140 g MAT ; génisses 0,75–0,85 UFL, 125–155 g MAT ; Ca 5–7 g, P 3–3,5 g, Mg 2–2,5 g) est chargé avec le statut [C] et doit être sourcé ou remplacé par les tables INRA 2018 avant mise en service (point ouvert n° 3).

Chaque ligne de référentiel porte un **mode** : `par_jour` (comparaison `A_e` au besoin journalier) ou `par_kg_ms` (comparaison de la concentration `A_e / MSI` à l'intervalle `[min ; max]`).

**Couverture** : `C_e = A_e / besoin_e` (mode `par_jour`) ou position de la concentration dans l'intervalle (mode `par_kg_ms`). Classes [CP] : `deficit` (< 80 %, gravité 2), `limite` (80–99 %, gravité 1), `couvert` (100–150 %), `exces` (> 150 %, gravité 1 pour UFL et PDI, 0 pour les minéraux sauf maxima), `maximum_depasse` (gravité 3 : Cu > 15 mg/kg MS de ration pour les ovins, EFSA 2016 [V] ; autres maxima réglementaires selon `ref.besoin_animal` lorsque renseignés). **Besoin résiduel** : `max(0 ; besoin_e − A_e)` par animal et par jour, en g ou mg.

**Ratios et contrôles** : Ca/P (cible 1,5–2,5 ; < 1,5 chez les mâles ovins et caprins : gravité 2, NRC 2007 [V] ; > 3 en préparation au vêlage : vigilance [C]) ; K/Mg (> 15 : vigilance pour les vaches à vêler [C]) ; BACA de la ration (> 50 mEq/kg MS en fin de gestation : gravité 2, Meschy 2007 [V]) ; PDI/UFL (hors 90–110 : information [C]) ; encombrement : `Σ UE / capacité d'ingestion` si la capacité est renseignée dans `ref.besoin_journalier` (> 1 : « ration trop encombrante »).

**Affectation conseillée** : si la ration d'un lot d'animaux comprend un lot de stock dont l'interprétation publiée déconseille la catégorie (champ `utilisation_deconseillee`), l'application le signale (EF-M5-05).

### RC-10 — Période de référence

La période va de la date du bilan à la prochaine récolte de même nature. Elle est décrite en phases avec un coefficient de ration : hivernage pleine ration (coefficient 1), transition de mise à l'herbe (0,5), complémentation estivale (saisi, défaut 0,5). `jours_equivalents = Σ jours_phase × coefficient`. Repères affichés (FRGDS AURA / Idele 2019 [V]) : plaine 160 jours minimum, montagne 200 ; transition + 30 jours à demi-ration ; complémentation estivale 30 jours en année favorable, 90–100 en année sèche. Le choix est celui de l'éleveur, assisté par le technicien ; l'application ne tranche pas (document 05, § 6.4).

En voie ration, les phases des lots d'animaux portent déjà leurs dates : les coefficients de période ne s'appliquent qu'à la voie UGB.

### RC-11 — Projection et date de rupture

**Pas de temps** : le calcul est journalier, l'affichage mensuel.

**Algorithme** :

```
pour chaque jour t de [debut ; fin] :
    pour chaque lot d'animaux L, phase active Φ en t :
        pour chaque composant c de la ration de Φ provenant d'un stock :
            q = effectif(Φ) × kgMS(c) / (1 − p_distribution(élevage))      # kg MS prélevés
            si c référence un lot de stock précis : consommer(lot, q, t)
            sinon (c référence une famille) : consommer selon la règle d'allocation
    appliquer les mouvements datés t (achat +, vente −, re-comptage := valeur, ajustement ±)
    stock(lot, t) = stock(lot, t−1) + mouvements − consommations
    si stock(lot, t) < 0 et rupture(lot) non définie : rupture(lot) = t
```

**Règle d'allocation par famille** : les lots de stock de la famille sont consommés dans l'ordre de priorité défini par l'utilisateur (défaut : date de récolte croissante, puis lots non analysés avant analysés) ; quand un lot est épuisé, le suivant prend le relais le même jour. **Re-comptage** : la valeur saisie remplace le stock projeté à cette date ; l'écart (projeté − recompté) est conservé comme « écart de recalage » et affiché (il mesure la dérive entre consommation prévue et réelle).

**Indicateurs** : besoin journalier moyen `b = B_stock / jours` ; autonomie en jours `= stock utile total / b` ; couverture `= stock utile / B_stock` ; bilan `= stock utile − B_stock − sécurité` (RC-12) ; date de rupture par lot, par famille (première date où la somme des lots de la famille est négative) et globale (première date où le besoin d'un composant ne peut être servi par aucun lot de la famille) ; date d'entame du stock de sécurité (première date où le stock restant d'une famille passe sous la sécurité allouée à cette famille au prorata de ses besoins).

**Agrégation mensuelle** : pour chaque mois civil chevauchant la période et chaque famille : stock début, consommation, mouvements, stock fin.

### RC-12 — Stock de sécurité

`securite = m × B_stock` avec `m = 0,15` par défaut (≈ 25 jours sur 160) ; `m = 0,25` proposé si l'année est sèche ou si les stocks n'ont été ni pesés ni carottés (indicateurs `estime` majoritaires). Position du GDS, assumée comme un arbitrage et affichée comme tel (document 05, § 7.2 [CP]) ; les fourchettes de la littérature (45–60 jours, 30–50 % en ensilage, 5–10 % minimum) sont rappelées en aide.

### RC-13 — Proposition initiale et leviers

**RC-13.1 Diagnostic** : pour chaque famille de fourrage, `deficit_f = max(0 ; B_f + securite_f − S_f)` ; pour chaque lot d'animaux et phase, les éléments en `deficit` ou `limite` (RC-9).

**RC-13.2 Proposition initiale** (heuristique, dans cet ordre, chaque étape ne s'appliquant que si un déficit subsiste) :

1. **Réaffectation** : si une famille est excédentaire (`S_f − B_f − securite_f > 0`) et qu'une famille déficitaire lui est substituable (matrice `ref.substituabilite` : foin ↔ regain ↔ enrubannage ↔ ensilage d'herbe à 1:1 en MS ; paille → foin seulement pour les lots dont le stade est « entretien » ou « tarissement », et à hauteur de 30 % de la ration au plus [C]), proposer le transfert de la quantité `min(excédent ; déficit)`, en affichant la conséquence nutritionnelle (recalcul RC-9 sur les lots concernés).
2. **Achat de fourrage** : pour le déficit restant de chaque famille, une ligne d'achat de la même famille, quantité = déficit, prix = valeur saisie ou prix indicatif GDS daté ; valeurs nutritionnelles = table de la famille (indicateur `table`).
3. **Correction nutritionnelle** : pour chaque lot d'animaux dont l'UFL ou les PDI restent sous 100 % après les étapes 1-2, une ligne de concentré de la catégorie adaptée (énergétique si UFL manque, azoté si PDI manque, mixte si les deux), quantité par animal et par jour = besoin résiduel ÷ teneur de la catégorie (table), plafonnée à 30 % de la MSI [C] ; la MS de fourrage libérée par substitution (RC-13.3) est réinjectée dans le bilan.
4. **Minéraux** : pour les éléments minéraux en déficit, l'application exprime le besoin résiduel en mg ou g par animal et par jour et renvoie au technicien ; elle ne propose pas de quantité de minéral (EF-GEN-04).

**RC-13.3 Catalogue des leviers** (gain en t MS de fourrage, coût) :

| Levier | Quantité | Paramètre | Gain | Coût | Source |
|---|---|---|---|---|---|
| Achat de foin / enrubannage / paille / autre fourrage | q (t MS) | prix €/t MS (défauts indicatifs 170 / 190 / 75) | q | q × prix | prix : indicatifs GDS, datés [CP] |
| Concentré en substitution, base foin | q (t MS) | prix €/t MS (défaut 290) | 0,44 × q | q × prix | Faverdin et al., taux de substitution [NV — référence complète à vérifier avant diffusion] |
| Concentré, base ensilage d'herbe | q | prix | 0,53 × q | q × prix | idem |
| Concentré, base ensilage de maïs | q | prix | 0,70 × q | q × prix | idem |
| Décapitalisation (UGB retirés) | q (UGB) | perte de produit €/UGB (défaut 900) | q × 13 × jours / 1000 | q × perte | Idele 13 kg/UGB/j [T] ; perte : [CP] |
| Génisses de renouvellement supprimées | q (têtes) | t MS par génisse (défaut 6) | q × tMS | 0 | [C] |
| Pâturage allongé ou dérobée | q (jours gagnés) | coût total | q × besoin journalier | coût saisi | [C] |
| Râtelier moins gaspilleur | — | nouveau taux de pertes p' | `B_ration × (1/(1−p) − 1/(1−p'))` | 0 (ou coût saisi) | Buskirk 2003 [M] |
| Autre (saisie libre) | q (t MS) | coût total | q | coût | — |

**RC-13.4 Évaluation d'un scénario** : stock corrigé `S' = S + Σ gains` ; recalcul de RC-11 et RC-12 avec les achats comme mouvements datés (date de livraison saisie, défaut : début de période) ; recalcul de RC-9 pour les lots dont la ration est modifiée ; coût total ; comparaison tabulaire des scénarios (coût, couverture, date de rupture, nombre de lots d'animaux en déficit nutritionnel, points de vigilance).

### RC-14 — Incertitude affichée

Les erreurs relatives suivantes sont composées quadratiquement par lot de stock (`e_lot = √Σ e_i²`), puis les erreurs absolues des lots sont composées quadratiquement pour le total (document 05, § 2.3 ; méthode [C], valeurs [M] ou [C] selon la ligne) :

| Facteur | Erreur relative | Statut |
|---|---|---|
| Volume d'un silo couloir | 5 % | [C] |
| Volume d'une taupinière | 10 % | [C] |
| Densité par équation | 7 % ; 15 % si l'intervalle de confiance de Corrot est retenu | [M] |
| Densité par tamisage | 7 % | [M] |
| Densité par carottage | 4 % | [M] |
| Poids de balle estimé à vue | 15 % | [M] (11–20 %) |
| Poids de balle pesé (6 balles) | 5 % | [M] (CV 7 %) |
| MS analysée | 2 points de MS, rapportés en relatif | [C] |
| MS par défaut (foin, paille) | 5 points | [C] |
| MS non analysée d'un enrubannage | 15 points | [C] |
| Pertes de conservation ou de stockage | 50 % de la valeur retenue | [C] |

La projection est recalculée avec `S × (1 − e)` et `S × (1 + e)` pour donner la fourchette de la date de rupture. L'incertitude n'est pas appliquée aux besoins (elle y est réelle mais non quantifiée ; le message de limites le dit).

## 4.4 Conventions d'arrondi et d'affichage

Les calculs sont faits en double précision sans arrondi intermédiaire. Affichage : t MS à une décimale ; kg MS/j à une décimale ; densités en kg MS/m³ entiers ; pourcentages entiers ; teneurs selon la précision du paramètre (`ref.parametre.decimales`) ; dates au format jour/mois/année ; jours entiers (autonomie tronquée à l'entier inférieur). Les montants sont en euros hors taxes, entiers.
