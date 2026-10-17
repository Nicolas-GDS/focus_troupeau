# Recette du lot L6 — autonomie et approvisionnement (jeu J1)

Procédure pour l'environnement de **recette** ou de développement. Livrable de revue visé (§ 9.3) : « bilan J1
conforme aux valeurs attendues ». Cas couverts : T-14 à T-16 (domaine), T-20, T-21, T-23, T-24, T-26 (bilan et
scénario), T-32, T-40. Les tests `tests/Unit/Domain/Autonomie/*`, `tests/Feature/Autonomie/*` et
`tests/Feature/Recette/RecetteL6Test.php` jouent ces cas ; ils doivent être verts avant la recette.

## 1. Préparer

1. `php artisan migrate --force` (schéma `plan` du lot L6, paramètres et textes types).
2. `php artisan db:seed --class=JeuJ1Seeder --force` : élevage `J1-ALLAITANT` (GDS 24), compte `eleveur.j1@j1.test`
   (mot de passe `J4_MOT_DE_PASSE`), agents de J4. Interdit en production. Le jeu est aligné sur T-14 : silo de maïs
   dans un inventaire clos du 01/10/2026, fourrages pris par famille, foin 2026 analysé.
3. Le service `queue-worker` doit tourner (`php artisan queue:restart` après une mise à jour du code) : le bilan est
   recalculé par le job `CalculerBilan` ; les alertes sont évaluées chaque nuit à 3 h (service `scheduler`).

## 2. Contrôler le bilan J1 (§ 9.1) — écran Autonomie (E14)

| Élément | Attendu |
|---|---|
| Stock disponible | **205,9 t MS** (foin 2025 40,0 ; foin 2026 98,9 ; enrubannage 42,6 ; paille 24,4) |
| Besoin prélevé sur les stocks | **222,2 t MS** (pertes à la distribution 13 %) |
| Stock de sécurité (15 %) | **33,3 t MS** |
| Déficit (sécurité déduite) | **− 49,5 t MS** |
| Autonomie | **139 jours** ; couverture 93 %, au-dessus du repère de 80 % (« un ajustement de la ration suffit généralement ») |
| Rupture | **23/12/2026** (enrubannage), avec sa fourchette d'incertitude |
| Lots | Foin 2025 épuisé le **03/01/2027** (consommé avant le foin 2026) ; foin 2026 non épuisé |
| Graphique | Stock total projeté, bande d'incertitude, besoin cumulé, sécurité, une courbe par famille ; légende textuelle |
| Projection mensuelle | Cinq mois (novembre 2026 à mars 2027), par famille et au total |
| Hypothèses | Marge de sécurité et sa source, pertes moyennes, incertitude, pas journalier |

Puis :

1. **T-20** : Stocks → enrubannage 2026 → mouvement « achat » de 30 t MS au 15/12/2026. Le bilan est recalculé
   (moins de 5 s) : rupture repoussée au **30/01/2027**, stock disponible 235,9 t MS.
2. **Période de référence** (RC-10, voie UGB) : ajouter une phase d'hivernage puis une transition (coefficient 0,5
   par défaut) ; une phase qui chevauche ou qui sort de la période est refusée.
3. **Accueil** (E02) : autonomie, rupture et couverture du dernier bilan, avec la date du calcul.
4. **PDF** : « bilan en PDF » — charte du GDS 24, tableaux, graphique, hypothèses et sources, mention de limites,
   version du référentiel (T-26, moins de 10 s).

## 3. Approvisionnement (E15)

| Élément | Attendu |
|---|---|
| Diagnostic | Enrubannage en déficit de 97,2 t MS (sécurité comprise) ; foin en excédent de 29,2 t MS ; paille en excédent |
| Proposition | 1. Réaffectation foin → enrubannage, 29,2 t MS (substitution à 1:1) ; 2. Achat d'enrubannage, 68,0 t MS à 190 €/t MS, soit **12 920 €**, « prix indicatif à valider » |
| Besoins résiduels | Étapes 3 et 4 exprimées par animal et par jour, sans quantité de concentré ni de minéral |

1. « Créer un scénario depuis la proposition » : plus de rupture ; points de vigilance (prix indicatif, fourrage
   acheté à faire analyser).
2. Ajouter des leviers : concentré base foin 10 t MS (gain 4,4 t MS, 2 900 €), décapitalisation 5 UGB (9,8 t MS,
   4 500 €), râtelier à 4 % de pertes ; un concentré « base enrubannage » est refusé.
3. **T-23** : créer quatre scénarios, les cocher : comparaison côte à côte (coût, couverture, bilan, autonomie,
   rupture, lots en déficit nutritionnel, vigilance) ; un cinquième est refusé.
4. **T-24** : retenir un scénario d'achat → Stocks : lot « Achat — Enrubannage » (stock initial nul) et mouvement
   d'achat daté de la livraison ; le bilan de l'inventaire est à jour ; le technicien reçoit un e-mail (Mailpit).
   Retenir un autre scénario retire ce lot et ce mouvement ; un seul scénario est retenu à la fois.
5. « scénario en PDF » : leviers, effet sur le bilan, valeurs par défaut et leurs sources.

## 4. Paramètres de l'élevage (E16)

1. Modifier les pertes « sous toit » à 5 % avec un motif : la référence (3 %) reste affichée ; un nouveau lot sous
   toit prend 5 %.
2. Modifier le prix de l'enrubannage à 210 €/t MS : la proposition d'achat le reprend.
3. Se connecter en technicien : « Écarts à la référence » liste les valeurs modifiées, leur auteur et leur motif ;
   « rétablir la référence ».
4. Les paramètres du référentiel (UGB, incertitudes, substitution) ne figurent pas dans l'écran.

## 5. Alerte d'autonomie (EF-M8-03, T-21)

Quand la rupture prévue passe à moins de 60 jours de la date du jour (paramètre `alerte.autonomie_jours`), ou que le
stock de sécurité est entamé, un e-mail part aux éleveurs et au technicien de la dernière visite (à défaut, aux
techniciens du GDS). Une seule alerte par franchissement ; une nouvelle alerte n'est possible qu'après un retour
au-dessus du seuil. Avec J1, l'alerte part à partir du 25/10/2026 (rupture au 23/12/2026).

## 6. Droits

- Vétérinaire d'élevage et vétérinaire conseil : pas d'accès aux écrans Autonomie, Approvisionnement, Paramètres
  (annexe 10 : éleveur, technicien).
- Convention échue (T-32) : écrans lisibles, aucune saisie (phases, leviers, retenue, paramètres).
- Élevage hors périmètre, scénario d'un autre élevage : page introuvable (T-40).

## 7. Points connus au lot L6

- Le stock disponible du bilan ajoute aux stocks de l'inventaire les achats, ventes et ajustements datés de la
  période (RC-13.4 : S' = S + Σ gains ; EF-M7-05) ; un re-comptage reste un écart de recalage. L'implémentation de
  référence (`calculs/`) ne calcule le bilan que sur les stocks de l'inventaire : les valeurs de T-14 sont inchangées
  (aucun mouvement).
- Étapes 3 et 4 de la proposition : besoin résiduel seulement, tant que les tables d'aliments sont vides (point 5) ;
  une ligne d'achat n'a pas de valeurs nutritionnelles.
- Prix indicatifs (point 8) et taux de substitution (point 7) : valeurs de `calculs/`, « à valider ».
- Encombrement de la ration non contrôlé (capacité d'ingestion absente des repères).
