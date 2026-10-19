# Audit d'accessibilité des écrans éleveur (T-41, ENF-ACC-01)

Cible : RGAA 4.1, niveau AA, sur les écrans éleveur. L'audit combine un contrôle automatisé, rejouable à chaque
version, et une vérification manuelle des critères qu'un outil ne sait pas juger.

## 1. Contrôle automatisé

```sh
docker compose exec app php artisan db:seed            # jeux J1 et J2 (si absents)
docker compose exec app php artisan accessibilite:auditer
```

axe-core 4.10.2 (règles WCAG 2.1 A et AA, dont les contrastes) s'exécute dans le Chromium de l'image, en largeur
téléphone (360 px) et bureau (1280 px), sous la politique de sécurité du contenu de production. Pages auditées :
connexion et lien de connexion ; avec le compte de J2 : accueil, synthèse de visite, fourrages, matrice minérale,
eau, animaux, aliments, bilan sanitaire, historique, plan d'action, compte, consentements ; avec le compte de J1 :
stocks, troupeau, autonomie, approvisionnement, paramètres. Le rapport détaillé est écrit dans
`storage/app/audit-accessibilite.json` ; la commande échoue sur toute violation grave ou critique ou toute page en
erreur.

**Résultat du 19/10/2026** : 38 pages auditées, aucune violation après correction. Défaut corrigé : les tableaux
défilants (largeur téléphone) n'étaient pas atteignables au clavier (`scrollable-region-focusable`, RGAA 7.3) ; les
25 zones défilantes de l'application sont désormais focalisables. Des tests statiques
(`tests/Feature/Interface/AccessibiliteTest.php`) empêchent la régression (zones défilantes, alternatives des images).

## 2. Vérification manuelle (avant la mise en production, puis à chaque refonte d'écran)

À faire par une personne, au clavier seul puis avec un lecteur d'écran (NVDA sous Windows ou VoiceOver sur iPhone),
sur un téléphone et sur un poste.

| Critère RGAA | Vérification | Écrans |
|---|---|---|
| 1.2, 1.3 | Les graphiques SVG (positionnement, historique, projection) ont un titre et une légende textuelle équivalente ; les pictogrammes décoratifs sont ignorés | E03, E04, E10, E14 |
| 3.1 | L'état n'est jamais porté par la couleur seule : chaque pastille a un libellé | tous |
| 3.2, 3.3 | Contrastes : texte courant sur fond Or Agenais en `#7A5200`, `#D4820A` réservé aux grands titres | GDS 47 |
| 7.1, 7.3 | Toutes les actions (accorder, enregistrer, retenir, ajouter un lot) se font au clavier ; aucun piège au clavier | tous |
| 8.5, 8.6 | Titre de page explicite et unique | tous |
| 10.7 | Le focus est toujours visible (contour `:focus-visible`) | tous |
| 10.11 | Pas de défilement horizontal de la page à 320 px de large (seuls les tableaux défilent) ; lisible zoomé à 200 % | tous |
| 11.1, 11.2 | Chaque champ a une étiquette visible ; l'unité est affichée ; un clavier numérique s'ouvre pour les quantités | E12, E13, E15, E16 |
| 11.10 | Les erreurs de saisie sont annoncées (rôle `alert`) et associées au champ | E12, E13, E15, E16, E17 |
| 12.6, 12.7 | Navigation principale et lien d'évitement vers le contenu | tous |

Consigner pour chaque ligne : date, écran, appareil, résultat, correction éventuelle.
