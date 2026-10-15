# Recette du lot L2 — import et interprétation (jeu J0, campagne 2025-2026)

Procédure pour l'environnement de **recette** (jamais en développement : J0 contient des données nominatives).
Livrable de revue visé (§ 9.3) : « les 59 bulletins interprétables ». Cas de recette couverts : T-01 à T-05 (§ 9.2).

## 1. Préparer la base de recette

1. Base vierge à la dernière version du code : `php artisan migrate --force` (**sans** `--seed` : le jeu J4 est réservé
   au développement).
2. Créer les comptes de recette (administrateur, technicien de chaque GDS, vétérinaire conseil) depuis l'administration
   (lot L7) ou, d'ici là, par `php artisan tinker` ; rattacher chaque agent à son GDS.
3. Vider les files après chaque déploiement : `php artisan queue:restart`.

## 2. Charger J0 — deux cas selon le contenu du script 03

Le script `03_chargement_campagne_2025-2026.sql` n'est pas versionné ; il est passé à la main par l'administrateur.

**Cas A — le script 03 charge les élevages et leurs identités (dont `num_client_labo`), sans les analyses.**
Importer les 59 PDF ; le rapprochement se fait par le numéro client LANO :

```
php artisan bulletins:importer /chemin/vers/pdf_gds24 --gds=24 --technicien=technicien.24@… --sync
php artisan bulletins:importer /chemin/vers/pdf_gds47 --gds=47 --technicien=technicien.47@… --sync
```

Les bulletins non rapprochés apparaissent « à rapprocher » dans l'écran E19 (`/import/bulletins`) et dans
« Mon travail » (E18) : désigner l'élevage à la main.

**Cas B — le script 03 charge aussi les analyses et leurs résultats** (statut `recu`).
Ne pas réimporter les PDF (ils seraient refusés comme doublons). Appliquer les ratios et le contrôle de plausibilité :

```
php artisan bulletins:controler-charges
```

## 3. Contrôler (T-01, T-02)

```
php artisan bulletins:bilan --campagne=2025-2026
```

| Cas | Attendu (§ 9.2) | Où le lire |
|---|---|---|
| T-01 | 59 analyses ; 58 au statut `controle` | Tableau « Statut / Analyses » du bilan |
| T-01 | Ratios calculés | Ligne « Ratios calculés par le GDS » (Ca/P, K/(Ca+Mg), PDI/UFL, BACA si S et Cl dosés) |
| T-01 | Alertes conformes à la campagne : 93 % des lots sous 7 mg Cu | Dernière ligne « Cuivre : … sous 7 mg/kg MS (… %) » et tableau des alertes |
| T-02 | HA25-42515 en `anomalie`, quatre résultats signalés (Na, Cu, Mn, Fe), exclu des références, technicien notifié | Tableau « Bulletins en anomalie » ; écran E19 ; e-mail reçu par les techniciens du GDS |

Écarts à noter dans le procès-verbal de recette : statut différent de l'attendu, paramètre non importé (avertissements de
l'écran E19), type de fourrage déduit à corriger (prélèvements « à compléter »).

## 4. Interpréter (T-03 à T-05) depuis l'écran E20

Depuis « Mon travail » → « Bulletins contrôlés à interpréter » :

- **T-03** : sur un foin à MAT ≈ 61 g et Cu ≈ 4,2 mg, vérifier les alertes MAT faible (1) et Cu carence probable (2),
  sans alerte Fe ni K, niveau proposé 2.
- **T-04** : enregistrer pour l'élevage un événement « tétanie » de moins de 12 mois, interpréter un lot à Mg bas
  (niveau 1) : rehaussement proposé au niveau 2 avec son motif, niveau retenu laissé au technicien.
- **T-05** : valider une interprétation au niveau retenu 3 ; vérifier que le technicien ne peut pas publier ; viser avec
  le compte du vétérinaire conseil (ou refuser le visa avec motif : retour en brouillon) ; publier ensuite.

## 5. Points connus au lot L2

- La publication ne fait que changer le statut et l'inscrire au journal : fiche, PDF et notification au lot L3.
- Les éleveurs n'accèdent pas encore à leurs bulletins ni à leurs interprétations (écrans du lot L3).
- Les plages de plausibilité (RC-2) et la correspondance des statuts épistémiques sont à valider par le comité technique.
