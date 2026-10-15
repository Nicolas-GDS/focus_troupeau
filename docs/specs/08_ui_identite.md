# 8. Interface et identité

## 8.1 Identité

L'application porte le nom **Focus Troupeau** (D20) selon les règles du cahier d'identité : deux mots, deux capitales ; première mention dans l'application sous la forme « Focus Troupeau, l'approche globale de la santé du troupeau » (page de connexion, pied de page) ; signature « Voir large, agir juste. » en Source Serif 4 Italic Violet Santé ; signe de mise au point (quatre équerres aux couleurs du logogramme, point central) en version horizontale dans l'en-tête, compacte comme favicon et icône d'application. Nom de fichier et d'URL : `focustroupeau`.

Co-branding : le logo du GDS de l'élevage (déterminé par `app.gds` de l'élevage courant) est en haut à gauche, Focus Troupeau à droite, GDS France réduit en pied. Un technicien rattaché aux deux GDS voit l'en-tête changer avec l'élevage sélectionné. Les deux couleurs locales ne sont jamais mélangées dans un même bloc ; le signe quadrichrome n'est posé que sur blanc, fond clair local ou quasi-noir.

Si le conseil d'administration retient un autre nom (« Mise au point », « Focus Élevage »), seuls les jetons `brand.name`, `brand.signature` et les fichiers du signe changent : aucun écran n'écrit le nom en dur.

## 8.2 Système de design

Les jetons de Coélab sont repris (espacements, rayons, ombres, composants de formulaire, tableaux, onglets, modales, toasts) et la palette est remplacée par celle de la charte GDS v2.0 : couleurs nationales (Bleu GDS `#0083CA`, Violet Santé `#9D3293`, Vert Prairie `#54B948`, Gris Territoire `#939598`, quasi-noir `#231F20`), couleurs locales par entité (Bleu Périgord `#006BA0` / Ciel Dordogne `#E6F4FB` ; Or Agenais `#D4820A` / Crème Agenais `#FDF3E3`, texte courant sur fond clair en `#7A5200`). Typographies Outfit (titres) et Source Serif 4 (corps), chargées localement (pas de police externe en production). Proportion 60 % neutre, 30 % couleur primaire d'entité, 10 % accent.

Couleurs d'état, communes aux deux entités et toujours doublées d'un libellé : critique `#B91C1C` sur fond `#FEF2F2` ; alerte `#B45309` sur fond `#FFFBEB` ; vigilance `#0369A1` sur fond `#E0F2FE` ; couvert / RAS `#2E7D32` sur fond `#F0FDF4` ; information `#4A5568`. Positions dans la campagne : faible, normal, élevé rendus par trois pastilles distinctes de forme (triangle bas, rond, triangle haut) et de couleur.

Un thème sombre est fourni pour les écrans de saisie en bâtiment (le calculateur existant en a un) ; les PDF sont toujours en thème clair.

## 8.3 Composants de restitution

Les composants ci-dessous sont des composants Blade réutilisés dans les écrans et dans les PDF.

| Composant | Contenu | Règle |
|---|---|---|
| `x-message-hierarchise` | Niveau, texte, pilier | Cinq au plus ; ordre par gravité décroissante ; phrase complète que l'éleveur peut répéter |
| `x-valeur-repere` | Valeur, unité, repère, classe, source | Jamais la couleur seule ; source en infobulle avec statut épistémique |
| `x-tuile-chiffre` | Un chiffre, son libellé, sa tendance | Quatre tuiles au plus en tête d'écran |
| `x-tableau-lots` | Lots × paramètres avec classes et position | Colonnes configurables ; tri ; export |
| `x-matrice-minerale` | Lots × éléments | Variante ration |
| `x-positionnement` | Points de la campagne, bande Q1-Q3, médiane, lot de l'élevage, campagnes passées | Tableau équivalent accessible |
| `x-barre-couverture` | Apport / besoin, classe, besoin résiduel | Repère 100 % et maximum s'il existe |
| `x-projection-stock` | Stock projeté par famille et besoin cumulé, date de rupture, bande d'incertitude | Tableau mensuel équivalent |
| `x-plan-action` | Actions par priorité, responsable, échéance, statut | Cases à cocher pour l'éleveur sur ses actions |
| `x-limites` | Ce qui n'est pas dosé, ce que la fiche ne remplace pas, version de la grille | Obligatoire sur toute page de résultat et tout PDF |

## 8.4 Gabarits PDF

Trois gabarits à la charte, rendus par Browsershot depuis les mêmes composants : **fiche de visite** (reprend la structure du prototype du document 04 : l'essentiel, les lots, la matrice, le positionnement, le plan d'action, les limites), **bilan d'autonomie** (trois indicateurs, hypothèses et valeurs par défaut employées, projection, incertitude, sources), **scénario d'approvisionnement** (lignes de levier, coûts, effet, points de vigilance). En-tête : logo de l'entité émettrice, Focus Troupeau, titre orienté décision ; pied : réseau GDS France, version de l'application, version de la grille, date de génération, pagination.

## 8.5 Règles de rédaction des libellés générés

Les textes générés par l'application (messages de seuils, lectures, propositions) suivent le chapitre 5 du document 03 : hiérarchiser (risque sanitaire, affectation, complémentation) ; langage de l'éleveur dans les écrans éleveur ; comparer (toujours un repère) ; distinguer ce qui est mesuré de ce qui est suggéré (« probable », « à confirmer ») ; pas de produit ni de marque ; un responsable par action. Les textes sont stockés dans les référentiels (E24), pas dans le code, et relus par le comité technique.
