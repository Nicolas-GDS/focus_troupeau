# 7. Exigences non fonctionnelles

## 7.1 Protection des données personnelles

Les résultats d'analyses, les identités d'élevages et les comptes sont des données à caractère personnel (document 03, § 6.6). Les obligations suivantes sont des exigences de l'application, pas seulement de l'organisation.

| Réf. | Exigence |
|---|---|
| ENF-RGPD-01 | Séparation physique des données identifiantes (`prive.*`, `app.user`) et des données métier pseudonymisées ; les vues de référence n'accèdent jamais à `prive.*`. |
| ENF-RGPD-02 | Notice d'information affichée à la première connexion (finalités, base légale : exécution de la convention et intérêt légitime pour les références agrégées ; destinataires ; durées ; droits) ; acceptation horodatée. |
| ENF-RGPD-03 | Consentements « références » et « vétérinaire » distincts, révocables, journalisés (§ 2.5). |
| ENF-RGPD-04 | Droit d'accès et portabilité : export ZIP (EF-M9-05) ; rectification : par le technicien sur demande, journalisée ; effacement : anonymisation de `prive.*` et désactivation des comptes, les lots pseudonymisés restant dans les références si le consentement était actif à la date du calcul (sinon retirés). |
| ENF-RGPD-05 | Durées de conservation : données de convention et dossier 10 ans après la fin de la convention (documentation du conseil, prescription) [à confirmer avec le registre des traitements du GDS] ; journaux de connexion 12 mois ; notifications 24 mois. |
| ENF-RGPD-06 | Hébergement en France ou dans l'UE, contrat de sous-traitance (DPA) avec l'hébergeur et avec Brevo ; aucun transfert hors UE. |
| ENF-RGPD-07 | Registre des traitements mis à jour avec ce traitement ; analyse d'impact non requise a priori (pas de données sensibles au sens de l'article 9 : les données de santé concernent les animaux), à confirmer par le délégué à la protection des données du GDS. |
| ENF-RGPD-08 | Les références agrégées ne sont diffusées qu'à partir de cinq lots et ne permettent pas la réidentification (pas de croisement commune × type sous ce seuil). |

## 7.2 Sécurité

| Réf. | Exigence |
|---|---|
| ENF-SEC-01 | HTTPS obligatoire (HSTS), cookies `Secure`/`HttpOnly`/`SameSite=Lax`, protection CSRF Laravel, en-têtes CSP et X-Frame-Options. |
| ENF-SEC-02 | Mots de passe : 12 caractères minimum, vérification contre les listes de compromission (API Have I Been Pwned en k-anonymat ou liste locale), hachage bcrypt/argon2id ; verrouillage progressif après 5 échecs ; lien de connexion à usage unique, 15 minutes. |
| ENF-SEC-03 | Double authentification par code e-mail proposée aux éleveurs, imposée aux techniciens et administrateurs (Entra ID porte la MFA). |
| ENF-SEC-04 | Autorisations vérifiées côté serveur sur chaque action Livewire (policies), jamais seulement par masquage d'interface ; tests de cloisonnement (T-40). |
| ENF-SEC-05 | Fichiers déposés (PDF, photos) : type vérifié, taille limitée (20 Mo), stockés hors racine web, servis par route signée ; antivirus ClamAV si disponible chez l'hébergeur. |
| ENF-SEC-06 | Secrets hors dépôt ; rotation des clés Brevo et Entra documentée ; dépendances mises à jour mensuellement (Dependabot ou équivalent). |
| ENF-SEC-07 | Sauvegardes quotidiennes chiffrées, test de restauration trimestriel documenté. |
| ENF-SEC-08 | Journal d'audit (`app.journal`) pour : connexions, publications, visas, révocations de consentement, modifications de référentiels, exports de données, changements de rôle. |

## 7.3 Accessibilité et ergonomie

| Réf. | Exigence |
|---|---|
| ENF-ACC-01 | Conformité visée RGAA 4.1 niveau AA sur les écrans éleveur : contrastes (texte sur Or Agenais `#D4820A` réservé aux grands titres, `#7A5200` pour le texte courant), navigation clavier, libellés de formulaires, alternatives textuelles des graphiques (tableau de données équivalent). |
| ENF-ACC-02 | L'état n'est jamais porté par la couleur seule (EF-GEN-03) ; les pastilles portent un libellé ou un symbole. |
| ENF-ACC-03 | Saisie mobile : champs numériques avec clavier adapté (`inputmode="decimal"`), virgule et point acceptés, unités affichées, une ligne de saisie à la fois sur petit écran, enregistrement automatique des brouillons. |
| ENF-ACC-04 | Langage éleveur dans les écrans éleveur (foin, luzerne, génisses, brebis), termes techniques en infobulle ; les écrans technicien peuvent utiliser le vocabulaire INRA. |

## 7.4 Performance et volumétrie

Hypothèses : 2 GDS, 300 élevages contractants à trois ans, 300 lots analysés par campagne, 20 lots de stock et 10 lots d'animaux par élevage, 50 utilisateurs simultanés au pic (restitution des foins).

| Réf. | Exigence |
|---|---|
| ENF-PERF-01 | Affichage d'un écran de consultation en moins de 1 s (serveur) ; recalcul d'une ration ou d'un lot de stock en ligne en moins de 300 ms ; calcul complet d'un bilan (job) en moins de 5 s. |
| ENF-PERF-02 | Import d'un bulletin PDF en moins de 30 s (extraction incluse). |
| ENF-PERF-03 | Rafraîchissement des vues de référence en moins de 60 s pour 10 000 lots. |
| ENF-PERF-04 | Génération d'un PDF de fiche en moins de 10 s. |

## 7.5 Exploitabilité

| Réf. | Exigence |
|---|---|
| ENF-EXP-01 | Déploiement reproductible par image Docker et migrations ; procédure d'installation documentée (README du dépôt) ; données de démonstration pseudonymisées (élevage ELV-0001, EARL Forgeneuf rendu anonyme). |
| ENF-EXP-02 | Supervision minimale : santé de l'application (`/up`), file d'attente, espace disque, sauvegardes ; alerte e-mail à l'administrateur. |
| ENF-EXP-03 | Journal des versions (CHANGELOG) et numéro de version affiché dans le pied de page de l'application et dans les PDF. |
| ENF-EXP-04 | Les référentiels sont modifiables sans déploiement (E24) ; les paramètres par défaut sont datés et conservent leur historique. |

## 7.6 Évolutivité

Le modèle de données et le code domaine sont conçus pour accueillir, sans refonte : un troisième GDS ; de nouveaux laboratoires (table `ref.laboratoire` et adaptateurs d'import) ; de nouveaux paramètres d'analyse (format long de `fourrage.resultat`) ; de nouvelles mesures terrain (table `ref.mesure_terrain`) ; le mode hors ligne (§ 6.7) ; une API REST documentée (OpenAPI) pour un futur export vers GDS France ou un outil de rationnement, exposant les mêmes services que les composants Livewire.
