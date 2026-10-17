# Seeders « minéraux × troubles sanitaires » — Focus Troupeau

Jeu de données de test pour `database/seeders/ref/` (chapitre 5.3 et 5.7 des spécifications : référentiels versionnés en CSV, une ligne par valeur, avec source et statut). Produit le 15 octobre 2026 à partir de la synthèse bibliographique `11_synthese_biblio_mineraux_troubles.md`.

Format : CSV UTF-8, séparateur `;`, première ligne d'en-têtes, pas de guillemets (aucun `;` dans les champs texte, remplacé par `–`). Listes multiples séparées par `|` (`BOV|OVI`). Champ vide = NULL (= toutes espèces / toutes catégories).

## Fichiers

| Fichier | Lignes | Contenu | Table cible |
|---|---|---|---|
| `ref/trouble_sanitaire.csv` | 32 | Référentiel des troubles (code, libellé, famille, espèces, catégories cibles, correspondance avec `fourrage.evenement_sanitaire.type_evenement`, description, source) | nouvelle table `ref.trouble_sanitaire` (proposée) |
| `ref/compatibilite_parametre_trouble.csv` | 111 | Liens paramètre d'analyse × sens (carence / excès / ratio) × trouble, avec force 1-3, mécanisme, facteurs modulateurs, repère quantitatif, référence, statut épistémique | `ref.compatibilite_parametre_trouble` (chapitre 5.3, structure proposée ci-dessous) |
| `ref/repere_mineral.csv` | 53 | Besoins, maxima tolérables (NASEM / NRC) et maxima réglementaires UE par élément et espèce | `ref.besoin_animal` (besoins) ou nouvelle table `ref.repere_mineral` ; les maxima UE peuvent aussi alimenter `ref.seuil_interpretation` (nature `reglementaire`) |
| `ref/reference_biblio_complement.csv` | 53 | Références nouvelles (chapitres 11 et 12), à ajouter à `ref.reference_biblio` (aucun doublon avec le script 02) | `ref.reference_biblio` |
| `ref/interaction_element.csv` | 55 | Chapitre 12 : facteur (paramètre, facteur agronomique, animal ou analytique) × élément cible × compartiment (sol_plante, contamination, rumen, absorption, metabolisme) × sens × mécanisme × repère × conséquence de lecture | nouvelle table `ref.interaction_element` (proposée) |
| `ref/cause_valeur_atypique.csv` | 41 | Chapitre 12 : paramètre × sens (bas, eleve) × cause plausible × catégorie (geochimie_sol, agronomie, physiologie_plante, contamination_echantillon, analytique, conservation, melange_aliment) × indices à vérifier × exemple de la campagne | nouvelle table `ref.cause_valeur_atypique` (proposée) ; alimente la règle RC-2 |
| `valider_seeders.py` | — | Contrôle de cohérence (codes, clés étrangères, domaines de valeurs) | — |

Les 12 codes existants de `type_evenement` (`myopathie_jeunes`, `retention_placentaire`, `hypocalcemie`, `tetanie`, `avortement`, `listeriose`, `acidose`, `boiteries`, `diarrhee_veaux`, `baisse_production`, `urolithiase`, `toxicite_cuivre`) sont conservés à l'identique dans `trouble_sanitaire.code` (colonne `type_evenement_existant` renseignée) ; `listeriose` et `acidose` ne sont pas repris ici car sans lien minéral direct (ils restent dans l'énumération). 20 codes nouveaux sont proposés : à valider par le vétérinaire conseil avant de figer l'énumération (point ouvert chapitre 9).

## Échelle de force (colonne `force`)

| Force | Signification | Exemple |
|---|---|---|
| 3 | Relation causale établie : reproduite expérimentalement ou consensus des manuels de référence | Se bas → myopathie des jeunes ; K/(Ca+Mg) > 2,2 → tétanie |
| 2 | Facteur de risque documenté, contribution avec d'autres facteurs | Cu bas → infertilité ; Fe élevé → carence Cu induite |
| 1 | Association rapportée ou mécanisme plausible, preuve faible ou indirecte | Ca bas → rétention placentaire ; Mg élevé → urolithiase |

Usage envisagé (RC du chapitre 4, § niveau proposé) : un événement sanitaire enregistré dans les 12 mois dont le `trouble_code` est lié avec une force ≥ 2 au paramètre en alerte rehausse un niveau 1 en 2 ; la force 1 ne rehausse pas mais alimente la lecture « causes communes » de l'interprétation de visite. Cette règle est une proposition, à arbitrer en comité technique.

## Statuts épistémiques (identiques à `ref.seuil_interpretation`)

`publie` (source primaire ou manuel de référence consulté en ligne), `publie_source_secondaire` (repris via une source secondaire : extension universitaire, article de presse technique), `consensus_professionnel`, `ordre_de_grandeur`, `hypothese_locale`. Pour les références : `verifie` / `secondaire` / `non_verifie` comme dans `ref.reference_biblio`.

## Structure proposée pour les tables (migration SQL brute, schéma `ref`)

```sql
CREATE TABLE ref.trouble_sanitaire (
  code                    text PRIMARY KEY,
  libelle                 text NOT NULL,
  famille                 text NOT NULL,          -- metabolique, nerveux, locomoteur, reproduction, cutane, digestif, croissance, immunitaire, intoxication, urinaire, endocrinien, hematologique, comportemental, respiratoire, production, general
  especes                 text[] NOT NULL,        -- {BOV,OVI,CAP}
  categories_cibles       text[],
  type_evenement_existant text,                   -- valeur de fourrage.evenement_sanitaire.type_evenement, si déjà dans l'énumération
  description             text,
  reference_id            integer REFERENCES ref.reference_biblio(id),
  statut_epistemique      text NOT NULL DEFAULT 'publie'
);

CREATE TABLE ref.compatibilite_parametre_trouble (
  id                   serial PRIMARY KEY,
  parametre_code       text NOT NULL REFERENCES ref.parametre(code),
  sens                 text NOT NULL CHECK (sens IN ('carence','exces','ratio_bas','ratio_eleve')),
  trouble_code         text NOT NULL REFERENCES ref.trouble_sanitaire(code),
  especes              text[],                    -- NULL = toutes
  categories           text[],                    -- NULL = toutes
  force                smallint NOT NULL CHECK (force BETWEEN 1 AND 3),
  mecanisme            text,
  facteurs_modulateurs text,
  repere_quantitatif   text,
  reference_id         integer REFERENCES ref.reference_biblio(id),
  statut_epistemique   text NOT NULL DEFAULT 'publie',
  commentaire          text,
  version_creation     integer REFERENCES ref.version_referentiel(id),
  version_fin          integer REFERENCES ref.version_referentiel(id),
  UNIQUE (parametre_code, sens, trouble_code, especes, categories)
);

CREATE TABLE ref.interaction_element (
  id                   serial PRIMARY KEY,
  compartiment         text NOT NULL CHECK (compartiment IN ('sol_plante','contamination','rumen','absorption','metabolisme')),
  facteur_type         text NOT NULL CHECK (facteur_type IN ('parametre','agronomique','animal','analytique')),
  facteur_code         text NOT NULL,          -- ref.parametre.code si facteur_type = 'parametre', sinon code libre en majuscules (PH_SOL_BAS, CHAULAGE, FERTI_K, TERRE…)
  element_cible        text NOT NULL REFERENCES ref.parametre(code),
  sens_effet           text NOT NULL CHECK (sens_effet IN ('diminue','augmente')),
  mecanisme            text,
  repere_quantitatif   text,
  consequence_lecture  text,
  reference_id         integer REFERENCES ref.reference_biblio(id),
  statut_epistemique   text NOT NULL DEFAULT 'publie'
);

CREATE TABLE ref.cause_valeur_atypique (
  id                 serial PRIMARY KEY,
  parametre_code     text NOT NULL REFERENCES ref.parametre(code),
  sens               text NOT NULL CHECK (sens IN ('bas','eleve')),
  cause_code         text NOT NULL,
  categorie          text NOT NULL CHECK (categorie IN ('geochimie_sol','agronomie','physiologie_plante','contamination_echantillon','analytique','conservation','melange_aliment')),
  description        text NOT NULL,
  indices_a_verifier text,
  exemple_campagne   text,
  reference_id       integer REFERENCES ref.reference_biblio(id),
  statut_epistemique text NOT NULL DEFAULT 'publie',
  UNIQUE (parametre_code, sens, cause_code)
);

CREATE TABLE ref.repere_mineral (
  id                 serial PRIMARY KEY,
  parametre_code     text NOT NULL REFERENCES ref.parametre(code),
  espece_code        text REFERENCES ref.espece_animale(code),
  categorie_code     text REFERENCES ref.categorie_animale(code),
  type_repere        text NOT NULL CHECK (type_repere IN ('besoin','seuil_carence_fourrage','max_tolerable','max_reglementaire_ue')),
  valeur_min         numeric,
  valeur_max         numeric,
  unite              text NOT NULL,
  reference_id       integer REFERENCES ref.reference_biblio(id),
  statut_epistemique text NOT NULL DEFAULT 'publie',
  commentaire        text
);
```

Dans les CSV, `reference_cle` porte la clé textuelle (`ref.f_ref('MERCK_NMD')` dans le script 02) ; le seeder la résout en `reference_id` après chargement du complément bibliographique. Les colonnes `especes` / `categories` sont fournies en chaîne `A|B` à convertir en tableau.

## Attention aux unités

Les maxima réglementaires UE sont exprimés en mg/kg d'**aliment complet à 12 % d'humidité** (≈ 88 % MS) : pour comparer à un bulletin LANO en mg/kg MS, diviser la valeur réglementaire par 0,88 (15 mg/kg aliment ≈ 17 mg/kg MS). Les besoins et maxima tolérables NASEM/NRC sont en MS de ration **totale** (fourrage + concentré + CMV + eau), pas du fourrage seul : un fourrage sous le besoin n'est pas une carence du troupeau, un fourrage au-dessus du maximum réglementaire n'est pas une infraction (le règlement vise l'aliment commercialisé), mais une alerte à lire avec la complémentation.

## Contrôle

```
python3 valider_seeders.py ref                                   # listes de codes intégrées
python3 valider_seeders.py ref ../sql/01_schema_fourrages_gds24.sql ../sql/02_referentiels_interpretation.sql   # codes lus dans les scripts
```

Résultat du 17/10/2026 (six CSV, chapitres 11 et 12) : OK (0 erreur). Deux troubles n'ont volontairement aucune ligne de compatibilité (`avortement`, `diarrhee_veaux` : liens minéraux trop indirects pour une règle automatique ; conservés comme codes d'événement).
