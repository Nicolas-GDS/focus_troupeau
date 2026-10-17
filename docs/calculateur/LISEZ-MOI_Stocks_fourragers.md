# Stocks fourragers et autonomie — livrables 5 et 6

Produits le 21 septembre 2026, en complément du dossier « Service analyses de fourrages »
(documents 00 à 04 du dossier `Livrables`).

## Contenu

| Fichier | Nature | Pages |
|---|---|---|
| `05_Fiche_pratique_stocks_fourragers_GDS24.docx` / `.pdf` | Fiche pratique : analyse bibliographique, protocole de mesure, **4 croquis de cubage**, calcul de l'autonomie, leviers d'ajustement et coûts, abaques, bibliographie (≈ 60 références) | 34 |
| `06_Fiche_terrain_stocks_fourragers_GDS24.docx` / `.pdf` | Formulaire A4 recto-verso à imprimer et remplir en élevage, avec aide-mémoire au dos | 4 |
| `Calculateur_autonomie_fourragere_GDS.html` | Copie d'archive du calculateur web (publié comme artefact Claude, charte bi-entité GDS 24 / GDS 47) | — |

Scripts de génération : `..\build\doc5_stocks_methode.js` et `..\build\doc6_stocks_terrain.js`
(node 22 + `docx@9`, bibliothèque de charte `gds_docx_lib.js`), et `..\build\build_figures_stocks.py`
(matplotlib) pour les croquis, écrits dans `..\build\fig\stocks\`.

```
cd "V:\Approche globale\Livrables\build"
python3 build_figures_stocks.py fig/stocks     # régénère les 4 croquis
set NODE_PATH=%HOME%\build\node_modules
node doc5_stocks_methode.js                    # FIG_DIR=fig/stocks par défaut
node doc6_stocks_terrain.js
```

## Croquis de la fiche pratique

| Fichier | Contenu | Emplacement dans le document |
|---|---|---|
| `silo_couloir_cubage.png` | Coupe longitudinale et vue de dessus d'un silo couloir : L1, L2, largeur, les trois mesures de hauteur, la couche de surface pourrie, la formule de volume | § 4.1 Cuber |
| `silo_largeur_mi_pente.png` | Trois configurations (deux murs, un mur, sans mur) et le point où prendre la largeur dans chacune | § 4.1 Cuber |
| `carottage_front.png` | Front d'attaque vu de face : carotte de référence au ¼ de la largeur et aux ⅔ de la hauteur, répartition des 9 à 12 carottes, prise de la carotte et calcul de densité | § 4.1 Protocole de carottage |
| `silo_taupiniere.png` | Coupe transversale et vue de dessus d'une taupinière : largeur à mi-pente, cinq points de mesure de hauteur | § 4.2 Silo taupinière et tas |

## Socle de référence

Le moteur de calcul est celui du classeur **« Bilan fourrager » (version 2020) de l'Institut
de l'Élevage**, dont les équations ont été lues directement dans le fichier source :

- Maïs, silo couloir : `D = (929 − 17,5·MS + 7,9·A + 44·H) × MS/100`
- Maïs, taupinière : `D = (874 − 14,5·MS + 4,2·A + 48·H) × MS/100`
- Herbe, brins courts : `D = 30·H + 2·MS + 60` — brins longs : `D = 27·H + 1,8·MS + 54`
- Herbe, silo couloir (à défaut) : `D = 16·H + 5·MS + 34` — taupinière : `D = 7·H + 3,7·MS + 74`
- Sorgho plante entière : densités maïs majorées de 20 %
- Amidon par défaut 28 % de la MS ; si le % de grain G est connu (> 20) : `A = 1,134·G − 22,5`
- Besoins : **13 kg MS par UGB et par jour** (4,75 t MS/UGB/an), durée par défaut 130 jours
- Coefficients UGB : VL 1,02 · VA 0,90 · génisse < 1 an 0,30 · 1-2 ans 0,60 · > 2 ans 0,80 ·
  mâle 1-2 ans 0,60 · taurillon 0,80 · brebis 0,15 · chèvre 0,20
- Poids bruts de balles et densité du foin en vrac (80-100 kg/m³) : mêmes sources

## Points de vigilance signalés dans le document 5

1. Les équations « herbe par type de silo » de l'Idele divergent de 10 à 15 % des tables de la
   Chambre d'agriculture de Bretagne. Retenir les équations « par type de brins ».
2. Ne pas employer les équations herbe au-delà de 36 % de MS (valeurs invraisemblables).
3. La table de poids de balles de l'IFCE 2024 est supérieure d'environ 15 % à celle de l'Idele
   pour le foin, alors que les deux se réclament de la même origine. Peser.
4. La référence bibliographique complète de Faverdin et al. (taux de substitution) reste à
   vérifier avant toute diffusion externe.
5. La règle des « 2 à 2,5 % du poids vif » n'a pas de source technique française identifiée :
   elle est présentée comme repère pédagogique, jamais attribuée à INRAE ou à l'Idele.
6. Aucune étude publiée ne confronte l'estimation complète d'un stock de silo à la pesée réelle :
   l'incertitude globale annoncée est une composition d'incertitudes partielles.

## Lacune à combler

Aucune étude française de pesée systématique de balles avec distribution des poids n'existe
(l'équivalent de Blazek et al., 2017, Wisconsin). Un peson sur chargeur et une campagne de
pesée sur les élevages suivis produiraient une référence départementale à peu de frais.
