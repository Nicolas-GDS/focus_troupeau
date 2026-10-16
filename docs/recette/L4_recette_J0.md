# Recette du lot L4 — positionnement (jeu J0, campagne 2025-2026)

Procédure pour l'environnement de **recette**. Livrable de revue visé (§ 9.3) : « graphiques de positionnement sur
J0 ». Cas de recette couverts : T-07, T-08, T-09 (§ 9.2). En développement, le jeu J5 (population synthétique) et les
tests `tests/Feature/Recette/RecetteL4Test.php` et `tests/Feature/Interpretation/ReferencesTest.php` jouent les mêmes
cas ; ils doivent être verts avant la recette.

## 1. Prérequis

1. Recettes des lots L2 et L3 passées : les 59 bulletins de J0 sont chargés et contrôlés
   (`docs/recette/L2_recette_J0.md`).
2. Dernière version du code : `php artisan migrate --force` (vues `valo` du lot L4, paramètres suivis,
   `references.n_min`).
3. **Consentements « références » (point ouvert 14)** : un lot n'entre dans les références que si son élevage a un
   consentement « références » actif. À enregistrer **uniquement pour les élevages dont le consentement écrit a été
   recueilli** ; d'ici l'administration du lot L7, par `php artisan tinker` :

   ```php
   App\Models\App\Consentement::withoutGlobalScopes()->create([
       'elevage_id' => App\Models\Fourrage\Elevage::withoutGlobalScopes()->where('code_pseudo', 'ELV-…')->value('id'),
       'type' => 'references', 'accorde_le' => now(),
   ]);
   ```

4. **Couches SIG (point ouvert 15)** : la population lithologique exige les couches BRGM et RRP et la localisation des
   prélèvements ou des sièges. Sans elles, seules les populations départementale et bi-GDS s'affichent. Après un
   import SIG : `php artisan tinker` puis `(new App\Jobs\RafraichirContextePrelevements)->handle();` (sinon, le
   rafraîchissement a lieu chaque nuit et après chaque publication).

## 2. Faire entrer les bulletins dans les références

Seuls les bulletins **validés** (interprétation validée, statut `valide`) entrent dans les références (RC-3, décision
du 16/10/2026) : interpréter et valider les bulletins de J0 depuis « Mon travail » (E18) et l'écran E20, comme pour la
recette du lot L2. Puis :

```
php artisan references:bilan --campagne=2025-2026
```

| Lecture du bilan | Attendu |
|---|---|
| Tableau des statuts | Les bulletins interprétés en `valide`, avec « Consentement : oui » pour les élevages consentants |
| Avertissement « n'entrent pas dans les références faute de consentement » | Absent, ou limité aux élevages sans consentement écrit |
| Tableau des populations | n par GDS × type pour MAT, UFL 2018 et Cu ; « quartiles affichés » à partir de 5 lots |
| Population lithologique | Présente si les couches SIG sont chargées |

## 3. Graphiques de positionnement (livrable)

Sur un élevage de J0 qui a des foins de prairie permanente, avec le compte d'un technicien du GDS : « Dossier » →
« Fourrages » → un foin de prairie permanente.

- La liste montre la position UFL et MAT de chaque lot (pastille, libellé).
- Le détail « Où se situe ce lot dans la campagne ? » montre, par paramètre suivi et dosé, le graphique : points gris
  anonymes, bande Q1-Q3, médiane, lot en point plein, et la légende textuelle (n, quartiles, position).
- **Comparaison au prototype** (document 04, figure 4, 17 foins de prairie permanente de la campagne, un lot aberrant
  exclu) : MAT Q1 62 et Q3 70 g/kg MS (de 55 à 83) ; UFL 2018 Q1 0,65 et Q3 0,72 ; cuivre Q1 2,4 et Q3 3,5 mg/kg MS (de
  1,6 à 4,6). Les valeurs ne coïncident que si les 17 lots sont validés et leurs élevages consentants ; noter les
  écarts et leur cause au procès-verbal.
- Aucun nom ni code d'un autre élevage n'apparaît sur la page ni dans la fiche.

## 4. Cas de recette

| Cas | Scénario | Attendu |
|---|---|---|
| T-07 | Un type de fourrage à 4 lots dans le GDS (voir le bilan), puis un cinquième validé | « Référence insuffisante (n = 4) » au détail du lot ; avec 5 lots, le graphique et les quartiles |
| T-08 | Révoquer le consentement « références » d'un élevage contributeur (`revoque_le` renseigné), recharger le détail d'un lot d'un autre élevage | n diminue immédiatement (vues non matérialisées) ; rétablir ensuite le consentement |
| T-09 | J0 ne couvre qu'une campagne : à vérifier sur un élevage ayant des foins sur deux campagnes (en développement : `J5-24-01`, volet « Historique ») | Variation en valeur et en pourcentage (« + 12 g/kg MS (+ 21,8 %) »), sans couleur ni classe |
| Fiche | Publier une visite d'un élevage de J0 dont les foins sont positionnés | Colonne « Position campagne » et section « Où se situent vos fourrages dans la campagne ? » (MAT, UFL, Cu), figées à la publication |
| Repli | Un type trop rare dans le GDS mais présent dans les deux GDS | Population départementale « insuffisante », puis population bi-GDS |
| Cloisonnement | Compte éleveur : le rappel « campagnes précédentes » et les courbes de l'historique | Seuls ses lots publiés y figurent |

## 5. Points connus au lot L4

- Les références reposent sur les seuls bulletins validés : elles se construisent au fil des interprétations.
- La population lithologique dépend des couches SIG et de la localisation (point ouvert 15) ; un prélèvement nouveau
  n'y entre qu'après le rafraîchissement du contexte.
- Les graphiques de la fiche (MAT, UFL, cuivre) ne s'affichent que pour une population suffisante ; une population
  insuffisante est signalée au détail du lot (E04).
- Les courbes de l'historique portent MAT, UFL 2018, cuivre et zinc (EF-M1-08) ; pas de série « alertes » à ce stade.
