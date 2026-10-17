# Recette du lot L5 — stocks et troupeau (jeu J1)

Procédure pour l'environnement de **recette** ou de développement. Livrable de revue visé (§ 9.3) : « saisie mobile
d'un inventaire et d'une ration ». Cas couverts : T-10 à T-13 (règles, tests du domaine), T-28 (reprise du
calculateur), T-32 (convention échue), T-40 (cloisonnement), et les attendus du § 9.1 vérifiables au lot L5. Les tests
`tests/Feature/Recette/RecetteL5Test.php`, `tests/Feature/Stock/*` et `tests/Feature/Troupeau/*` jouent ces cas ; ils
doivent être verts avant la recette.

## 1. Préparer

1. `php artisan migrate --force` (schémas `stock` et `plan`, référentiels version 5).
2. `php artisan db:seed --class=JeuJ1Seeder --force` : élevage synthétique `J1-ALLAITANT` (GDS 24), compte
   `eleveur.j1@j1.test` (mot de passe `J4_MOT_DE_PASSE`), agents de J4. Interdit en production.
3. Sur un **téléphone** (largeur 360 px), se connecter avec le compte de l'éleveur : liens « Stocks » et « Troupeau ».

## 2. Contrôler J1 (§ 9.1)

| Écran | Attendu |
|---|---|
| Stocks → inventaire du 01/11/2026 | Période de 151 jours, marge de sécurité 15 % ; stock utile : silo de maïs 111,98 t MS (vérification de RC-5, non consommé), foin 2025 40,0, foin 2026 98,94, enrubannage 42,56, paille 24,44 — soit **205,94 t MS hors silo** |
| Troupeau | Voie ration 193,3 t MS ; **prélevé sur les stocks 222,2 t MS** (pertes à la distribution 13 %) ; voie UGB 212,0 t MS et écart des deux voies |
| Troupeau → vaches allaitantes | 11,98 kg MS ingérés ; apports de T-13 ; couverture lue sur les repères des vaches allaitantes en lactation (« repère à valider ») ; contrôles Ca/P et PDI/UFL |
| Troupeau → génisses | « Apports inconnus pour : Foin 2026, Paille » : ces lots ne sont pas analysés, la couverture n'est pas calculée |

Écart connu avec le texte du § 9.1 : la couverture « UFL 96 % (limite), PDI couvert, cuivre 23 mg/kg MS » y est lue
sur les besoins d'essai de `calculs/` (9,5 UFL et 800 g PDI par jour, cuivre 10 mg/kg MS) ; l'application la lit sur
les repères chargés (vaches allaitantes en lactation : UFL 0,72–0,80 et MAT 120–140 par kg MS, Ca, P, Mg), qui
n'ont ni PDI ni cuivre pour cette catégorie (point ouvert 6). Le calcul lui-même (T-13) est vérifié par les tests du
domaine.

## 3. Saisir sur téléphone (livrable)

1. **Inventaire** : ajouter un lot de balles (nombre, poids, pesé ou estimé), puis un silo (cotes, MS, densité par
   équation ou mesurée, date de fermeture). Vérifier l'aperçu du stock utile pendant la saisie, les indicateurs « MS
   analysée / saisie / par défaut » et « poids pesé / estimé », la perte retenue et sa valeur de référence. Relier un
   lot à un prélèvement analysé : la MS du bulletin remplace la valeur saisie. Saisir un achat, une vente, un
   re-comptage et un ajustement (motif demandé).
2. **Refus attendus** : enrubannage sans MS ; équation herbe au-delà de 36 % de MS (densité mesurée demandée).
3. **Troupeau** : créer un lot (coefficient UGB proposé selon la catégorie), une phase (une phase qui chevauche est
   refusée), une ration en kg brut (converti en kg MS avec la MS de la source) ou en kg MS ; changer le mode de
   distribution. Vérifier le tableau des apports et de la couverture, les contrôles et « Qui mange quoi ».
4. **Copie** : clore l'inventaire, en créer un nouveau, « Repartir des lots et rations » de l'ancien.

## 4. Reprendre un bilan du calculateur (T-28)

Le calculateur `docs/calculateur/Calculateur_autonomie_fourragere_GDS.html` garde sa saisie dans le navigateur, sans
bouton d'export (point ouvert 16) :

1. ouvrir le calculateur dans le navigateur où le bilan a été saisi (ou cliquer « Exemple ») ;
2. ouvrir les outils de développement (F12) → onglet Application (Chrome, Edge) ou Stockage (Firefox) → Stockage
   local → clé `gds-autonomie-fourragere-v1` → copier la valeur ;
3. dans l'application, Stocks → « Reprendre un bilan du calculateur » → coller → « Voir ce qui sera repris » ;
4. vérifier les familles proposées pour les balles et la forme, lire les alertes, « Créer l'inventaire ».

Attendu : les stocks utiles de chaque silo et de chaque lot de balles sont ceux du calculateur (exemple intégré :
fichier `tests/Fixtures/calculateur/exemple_v1.json`). Différences signalées dans l'aperçu : ouvrage d'une équation
« par type de brins » à confirmer ; équation herbe au-delà de 36 % de MS refusée (le calculateur l'applique). Les
lignes UGB, ration et leviers ne sont pas reprises.

## 5. Droits

- Vétérinaire d'élevage et vétérinaire conseil : pas d'accès aux écrans Stocks et Troupeau (annexe 10 : éleveur,
  technicien), lecture des données par les autres écrans des lots suivants.
- Convention échue (T-32) : les écrans restent lisibles, aucune saisie possible.
- Technicien d'un autre GDS : page introuvable (T-40).

## 6. Points connus au lot L5

- Croquis de cubage du document 05 : à intégrer dès que les quatre images (`silo_couloir_cubage.png`,
  `silo_largeur_mi_pente.png`, `carottage_front.png`, `silo_taupiniere.png`, produites par `build_figures_stocks.py`)
  sont versées au dépôt ; elles ne sont pas extractibles proprement du PDF.
- Brouillon local chiffré en cas de perte de réseau (§ 6.1) : non fait, proposé au lot L8.
- Phases de la période de référence (RC-10), projection, sécurité, autonomie, ruptures : lot L6. Le silo de J1 n'entre
  dans aucune ration, comme au § 9.1.
- Encombrement de la ration : non contrôlé (capacité d'ingestion absente des repères).
- Besoins : repères de RC-9 et du socle (`ref.besoin_animal`), tous « à valider » ; tables d'aliments vides
  (points ouverts 5 et 6).
