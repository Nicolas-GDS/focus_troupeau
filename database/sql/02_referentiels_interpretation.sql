-- =====================================================================================
--  GDS 24 — Service « Analyses de fourrages »
--  Fichier 02 : références bibliographiques et grille de seuils d'interprétation (version initiale)
--  Chaque seuil est rattaché à une référence et à un statut épistémique ; la grille est
--  destinée à être revue par le comité technique (technicien GDS + vétérinaire) avant mise en service.
-- =====================================================================================
\set ON_ERROR_STOP on

INSERT INTO ref.reference_biblio (cle, auteurs, annee, titre, source, url, type_source, statut_verif) VALUES
 ('INRA2018','INRA (coord. Nozière P., Sauvant D., Delaby L.)',2018,'Alimentation des ruminants','Éditions Quae, Versailles, 728 p.','https://www.quae.com/produit/1523/9782759228683/alimentation-des-ruminants','ouvrage','non_verifie'),
 ('NOZIERE2026A','Nozière P., Baumont R., Boudon A., Delagarde R., Hassoun P., Lemosquet S., Maxin G., Sepchat B., Delaby L.',2026,'Le système d''alimentation INRA 2018 pour les ruminants. 1. Bases conceptuelles','INRAE Productions Animales 39(2)','https://productions-animales.org/article/view/9526','article','verifie'),
 ('NOZIERE2026B','Nozière P. et al.',2026,'Le système d''alimentation INRA 2018 pour les ruminants. 2. Application au rationnement','INRAE Productions Animales 39(2)','https://productions-animales.org/article/view/9809','article','verifie'),
 ('FEEDTABLES','INRAE, CIRAD, AFZ',2024,'Tables INRAE-CIRAD-AFZ de composition et de valeur nutritive des matières premières','feedtables.com','https://www.feedtables.com/','table','verifie'),
 ('MESCHY2007','Meschy F.',2007,'Alimentation minérale et vitaminique des ruminants : actualisation des connaissances','INRA Productions Animales 20(2):119-128','https://productions-animales.org/article/view/3444','article','verifie'),
 ('MESCHY2010','Meschy F.',2010,'Nutrition minérale des ruminants','Éditions Quae (nouvelle édition 2023)','https://www.quae.com/extract/3715','ouvrage','non_verifie'),
 ('NASEM2021','NASEM',2021,'Nutrient Requirements of Dairy Cattle, 8th rev. ed.','National Academies Press','https://doi.org/10.17226/25806','ouvrage','secondaire'),
 ('NRC2007','NRC',2007,'Nutrient Requirements of Small Ruminants','National Academies Press','https://doi.org/10.17226/11654','ouvrage','secondaire'),
 ('KEMP1957','Kemp A., ''t Hart M.L.',1957,'Grass tetany in grazing milking cows','Neth. J. Agric. Sci. 5:4-17','https://doi.org/10.18174/njas.v5i1.17745','article','verifie'),
 ('MERCK_PARESIS','McArt J.A.A.',2026,'Parturient paresis in cows','Merck Veterinary Manual','https://www.merckvetmanual.com/metabolic-disorders/disorders-of-calcium-metabolism/parturient-paresis-in-cows','manuel','verifie'),
 ('MERCK_TETANY','Stewart A.J.',2026,'Hypomagnesemic tetany in cattle and sheep','Merck Veterinary Manual','https://www.merckvetmanual.com/metabolic-disorders/disorders-of-magnesium-metabolism/hypomagnesemic-tetany-in-cattle-and-sheep','manuel','verifie'),
 ('MERCK_CU','Fritz S.',2026,'Copper poisoning in animals','Merck Veterinary Manual','https://www.merckvetmanual.com/toxicology/copper-poisoning/copper-poisoning-in-animals','manuel','verifie'),
 ('EFSA2016CU','EFSA FEEDAP Panel',2016,'Revision of the currently authorised maximum copper content in complete feed','EFSA Journal 14(8):4563','https://doi.org/10.2903/j.efsa.2016.4563','article','verifie'),
 ('REG2018_1039','Commission européenne',2018,'Règlement d''exécution (UE) 2018/1039 — composés de cuivre (additifs)','JOUE','https://eur-lex.europa.eu/eli/reg_impl/2018/1039/oj','reglementation','secondaire'),
 ('REG2016_1095','Commission européenne',2016,'Règlement d''exécution (UE) 2016/1095 — composés de zinc','JOUE','https://eur-lex.europa.eu/eli/reg_impl/2016/1095/oj','reglementation','verifie'),
 ('REG2017_2330','Commission européenne',2017,'Règlement d''exécution (UE) 2017/2330 — composés de fer','JOUE','https://eur-lex.europa.eu/eli/reg_impl/2017/2330/oj','reglementation','verifie'),
 ('REG2015_861','Commission européenne',2015,'Règlement d''exécution (UE) 2015/861 — iode','JOUE','https://eur-lex.europa.eu/eli/reg_impl/2015/861/oj','reglementation','verifie'),
 ('REG121_2014','Commission européenne',2014,'Règlement d''exécution (UE) n° 121/2014 — sélénium (L-sélénométhionine), plafonds','JOUE','https://eur-lex.europa.eu/eli/reg_impl/2014/121/oj','reglementation','verifie'),
 ('DIR2002_32','Parlement européen et Conseil',2002,'Directive 2002/32/CE sur les substances indésirables dans les aliments pour animaux (aflatoxine B1, ergot…)','JO L 140 du 30.5.2002','https://eur-lex.europa.eu/eli/dir/2002/32/oj','reglementation','secondaire'),
 ('RECO2006_576','Commission européenne',2006,'Recommandation 2006/576/CE — valeurs indicatives DON, ZEA, OTA, fumonisines (mod. 2016/1319)','JO L 229 du 23.8.2006','https://eur-lex.europa.eu/eli/reco/2006/576/oj','reglementation','secondaire'),
 ('RECO2013_165','Commission européenne',2013,'Recommandation 2013/165/UE — toxines T-2 et HT-2','JO L 91 du 3.4.2013','https://eur-lex.europa.eu/eli/reco/2013/165/oj','reglementation','verifie'),
 ('AFSSA2004','AFSSA',2004,'Bonnes pratiques de fabrication de l''ensilage pour une meilleure maîtrise des risques sanitaires','AFSSA/ANSES','https://www.anses.fr/fr/system/files/ALAN-Ra-ensilage.pdf','note_technique','verifie'),
 ('ARVALIS2024','Chauveau H. (Arvalis)',2024,'Qualité du maïs fourrage : comment interpréter son bulletin d''analyse ?','arvalis.fr','https://www.arvalis.fr/infos-techniques/qualite-du-mais-fourrage-comment-interpreter-son-bulletin-danalyse','note_technique','verifie'),
 ('ROCHEREAU2024','Rochereau T. (CA Centre-Val de Loire)',2024,'Comprendre son analyse de fourrage — Note technique n° 11','Programme Herbe & Fourrages Centre-Val de Loire','https://www.herbe-fourrages-centre.fr/','note_technique','verifie'),
 ('CA_AVEYRON2024','Chambre d''agriculture de l''Aveyron',2024,'Interpréter une analyse de fourrage (fiche technique)','aveyron.chambres-agriculture.fr','https://aveyron.chambres-agriculture.fr/','note_technique','verifie'),
 ('BAUMONT2009','Baumont R., Aufrère J., Meschy F.',2009,'La valeur alimentaire des fourrages : rôle des pratiques de culture, de récolte et de conservation','Fourrages 198:153-173','https://afpf-asso.fr/','article','verifie'),
 ('SCHLEGEL2013','Schlegel P., Wyss U.',2013,'Teneur en oligo-éléments des fourrages','Rencontres Recherches Ruminants 20','https://journees3r.fr/textes3r/20130103-teneur-en-oligo-elements-des-fourrages/','article','verifie'),
 ('ARAD2_2023','Guittard A.-L. (ARAD2 / Cerfrance Normandie Maine)',2023,'Oligo-éléments, fourrages prairiaux et besoins des bovins (cite l''enquête Bellanger, Périgaud & Lamand)','cerfrance.fr','https://normandiemaine.cerfrance.fr/arad2/oligo-elements-fourrages-prairiaux-et-besoins-des-bovins/','note_technique','secondaire'),
 ('VIRBAC2022','Virbac',2022,'Importance des oligo-éléments — recommandations INRA 2018 ; règlement (UE) 2020/354','pro-fr.virbac.com','https://pro-fr.virbac.com/','site','secondaire'),
 ('BONSILAGE2013','Schaumann / Bonsilage',2013,'Mémento d''ensilage d''herbe','bonsilage.de','https://www.bonsilage.de/downloads/Silierfibel_Gras_fr.pdf','note_technique','verifie'),
 ('LANO_REFNORMANDIE2009','LANO / Chambres d''agriculture de Normandie',2009,'Références Normandie : positionnement faible / normal / élevé par quartiles (maïs ensilage, > 8 500 analyses 2003-2008)','L''Agriculteur Normand, 27/09/2009','https://www.agriculteur-normand.com/mieux-visualiser-le-resultat-de-votre-analyse-pour-mieux-linterpreter','article','verifie'),
 ('GDS24_CAMPAGNE2025','GDS 24',2026,'Campagne d''analyses 2025-2026 : 59 bulletins LANO, 17 élevages (24 et 47)','Base interne','','table','verifie');

-- Raccourci
CREATE OR REPLACE FUNCTION ref.f_ref(p_cle text) RETURNS integer LANGUAGE sql STABLE AS $$ SELECT id FROM ref.reference_biblio WHERE cle = p_cle $$;

-- -------------------------------------------------------------------------------------
-- Seuils (colonnes : parametre, famille, type, espece, categorie, nature, min, max, classe, gravite, message, ref, statut)
-- Convention : intervalle [min ; max[ ; NULL = non borné. gravite : 0 info, 1 vigilance, 2 alerte, 3 critique.
-- -------------------------------------------------------------------------------------
INSERT INTO ref.seuil_interpretation (parametre_code, famille_fourrage_code, type_fourrage_code, espece_code, categorie_code, nature, borne_min, borne_max, classe, gravite, message, reference_id, statut_epistemique) VALUES
-- Matière sèche : conservation
 ('MS','FOIN',NULL,NULL,NULL,'sanitaire',NULL,80,'humide',2,'Foin à moins de 80 % MS : risque d''échauffement et de moisissures (bottelage ≥ 84 % MS recommandé).',ref.f_ref('ARVALIS2024'),'publie'),
 ('MS','ENRUB',NULL,NULL,NULL,'sanitaire',NULL,50,'trop_humide',2,'Enrubannage < 50 % MS : fermentation butyrique et Listeria possibles ; distribuer rapidement, écarter les zones altérées.',ref.f_ref('AFSSA2004'),'publie'),
 ('MS','ENRUB',NULL,NULL,NULL,'sanitaire',65,NULL,'trop_sec',1,'Enrubannage > 65 % MS : tassage insuffisant, moisissures aérobies possibles.',ref.f_ref('AFSSA2004'),'publie'),
 ('MS','ENSIL_HERBE',NULL,NULL,NULL,'sanitaire',NULL,30,'trop_humide',1,'Ensilage d''herbe < 30 % MS : risque butyrique accru ; vérifier pH et odeur.',ref.f_ref('AFSSA2004'),'publie'),
 ('MS','ENSIL_MAIS',NULL,NULL,NULL,'nutritionnel',NULL,30,'immature',1,'Maïs < 30 % MS : récolte précoce, amidon faible, jus ; optimum 32-35 %.',ref.f_ref('ARVALIS2024'),'publie'),
 ('MS','ENSIL_MAIS',NULL,NULL,NULL,'nutritionnel',37,NULL,'trop_sec',1,'Maïs > 37 % MS : tassage et dégradabilité de l''amidon réduits.',ref.f_ref('ARVALIS2024'),'publie'),
-- Cendres : contamination tellurique
 ('MM','FOIN',NULL,NULL,NULL,'sanitaire',100,NULL,'terre',2,'Cendres > 100 g/kg MS : contamination par la terre probable (spores butyriques, Listeria, antagonisme Cu/Zn).',ref.f_ref('BONSILAGE2013'),'consensus_professionnel'),
 ('MM','ENRUB',NULL,NULL,NULL,'sanitaire',100,NULL,'terre',2,'Cendres > 100 g/kg MS : terre dans le fourrage ; vérifier hauteur de coupe et réglage des outils.',ref.f_ref('BONSILAGE2013'),'consensus_professionnel'),
 ('MM','ENSIL_HERBE',NULL,NULL,NULL,'sanitaire',100,NULL,'terre',2,'Cendres > 100 g/kg MS : terre dans l''ensilage (butyriques).',ref.f_ref('BONSILAGE2013'),'consensus_professionnel'),
 ('MM','ENSIL_MAIS',NULL,NULL,NULL,'sanitaire',60,NULL,'terre',2,'Cendres > 6 % MS sur maïs : alerte contamination tellurique.',ref.f_ref('ARVALIS2024'),'publie'),
-- Azote
 ('MAT',NULL,NULL,NULL,NULL,'nutritionnel',NULL,80,'faible',1,'Fourrage pauvre en azote (< 80 g MAT/kg MS) : complémentation azotée à raisonner, surtout en fin de gestation et lactation.',ref.f_ref('ROCHEREAU2024'),'consensus_professionnel'),
 ('MAT','FOIN','FOIN_LUZ',NULL,NULL,'nutritionnel',NULL,150,'faible_pour_luzerne',1,'Foin de luzerne < 150 g MAT/kg MS : pertes de feuilles ou récolte tardive.',ref.f_ref('ROCHEREAU2024'),'consensus_professionnel'),
 ('BPR',NULL,NULL,NULL,NULL,'nutritionnel',NULL,-30,'deficit_azote_soluble',1,'BPR très négative : le rumen manque d''azote dégradable ; associer une source d''azote soluble (légumineuse, tourteau, urée sous contrôle).',ref.f_ref('NOZIERE2026A'),'publie'),
 ('BPR',NULL,NULL,NULL,NULL,'nutritionnel',30,NULL,'exces_azote',1,'BPR très positive : azote excédentaire rejeté (urée) ; associer une source d''énergie.',ref.f_ref('NOZIERE2026A'),'publie'),
-- Énergie
 ('UFL_2018','FOIN',NULL,NULL,NULL,'nutritionnel',NULL,0.65,'faible',1,'Foin de faible valeur énergétique (< 0,65 UFL) : récolte tardive ; à réserver aux animaux à faibles besoins.',ref.f_ref('ROCHEREAU2024'),'consensus_professionnel'),
 ('UFL_2018','ENRUB',NULL,NULL,NULL,'nutritionnel',NULL,0.75,'faible',1,'Enrubannage < 0,75 UFL : valeur faible.',ref.f_ref('ROCHEREAU2024'),'consensus_professionnel'),
 ('UFL_2018','ENSIL_MAIS',NULL,NULL,NULL,'nutritionnel',NULL,0.88,'faible',1,'Maïs < 0,88 UFL : faible (moyenne nationale ≈ 0,95 UFL 2018).',ref.f_ref('ARVALIS2024'),'publie'),
 ('DMO','FOIN',NULL,NULL,NULL,'nutritionnel',NULL,55,'faible',1,'Digestibilité faible (< 55 %) : fourrage récolté tardif.',ref.f_ref('ROCHEREAU2024'),'consensus_professionnel'),
 ('AMI','ENSIL_MAIS',NULL,NULL,NULL,'sanitaire',350,NULL,'risque_acidose',2,'Amidon > 350 g/kg MS : risque d''acidose ; limiter la part de concentré, apporter de la fibre efficace.',ref.f_ref('ARVALIS2024'),'consensus_professionnel'),
-- Macro-éléments
 ('MG',NULL,NULL,NULL,NULL,'sanitaire',NULL,2.0,'bas',1,'Mg < 2 g/kg MS : facteur de risque d''hypomagnésémie (tétanie), aggravé si K élevé ; prévoir un apport de Mg au pâturage de printemps.',ref.f_ref('MERCK_TETANY'),'publie'),
 ('K',NULL,NULL,NULL,NULL,'sanitaire',30,NULL,'eleve',1,'K > 30 g/kg MS : BACA très positive et absorption du Mg réduite ; inadapté aux vaches taries en préparation au vêlage.',ref.f_ref('MESCHY2007'),'publie'),
 ('NA',NULL,NULL,NULL,NULL,'nutritionnel',NULL,1.0,'bas',1,'Na < 1 g/kg MS : apport de sel indispensable (besoin ≈ 1,2-2,2 g/kg MS de ration).',ref.f_ref('MESCHY2007'),'publie'),
 ('K_CAMG_EQ',NULL,NULL,NULL,NULL,'sanitaire',2.2,NULL,'risque_tetanie',2,'K/(Ca+Mg) > 2,2 éq. : risque de tétanie d''herbage (Kemp & ''t Hart).',ref.f_ref('KEMP1957'),'publie'),
 ('CA_P',NULL,NULL,'OVI','BOUC_MALE','sanitaire',NULL,1.5,'ca_p_bas',2,'Ca/P < 1,5 chez des mâles ovins/caprins : risque d''urolithiase phosphatique ; viser Ca/P 2 à 2,5.',ref.f_ref('NRC2007'),'publie_source_secondaire'),
 ('CA_P',NULL,NULL,'CAP','BOUC_MALE','sanitaire',NULL,1.5,'ca_p_bas',2,'Ca/P < 1,5 chez des mâles caprins : risque d''urolithiase ; viser Ca/P 2 à 2,5.',ref.f_ref('NRC2007'),'publie_source_secondaire'),
 ('BACA',NULL,NULL,'BOV','VL_TARIE','sanitaire',50,NULL,'inadapte_tarie',2,'BACA/BE > 50 mEq/kg MS pour des vaches en fin de gestation : fourrage seul incompatible avec la prévention de l''hypocalcémie (cible BE < 50, BACA −50 à −150 avec sels anioniques).',ref.f_ref('MESCHY2007'),'publie'),
 ('BACA',NULL,NULL,'BOV','VL','nutritionnel',350,NULL,'tres_eleve',1,'BACA > 350 mEq/kg MS : au-delà du plafond recommandé en lactation.',ref.f_ref('MESCHY2007'),'publie'),
-- Oligo-éléments
 ('CU',NULL,NULL,NULL,NULL,'sanitaire',NULL,7,'carence_probable',2,'Cu < 7 mg/kg MS : le fourrage ne couvre pas le besoin (10 mg/kg MS bovins) ; carence probable si Mo, S ou Fe défavorables. Complémentation adaptée à l''espèce (attention ovins).',ref.f_ref('ARAD2_2023'),'publie_source_secondaire'),
 ('CU',NULL,NULL,NULL,NULL,'nutritionnel',7,10,'insuffisant',1,'Cu entre 7 et 10 mg/kg MS : apport limite pour les bovins.',ref.f_ref('VIRBAC2022'),'publie_source_secondaire'),
 ('CU',NULL,NULL,'OVI',NULL,'reglementaire',15,NULL,'toxicite_ovins',3,'Cu > 15 mg/kg : au-delà du maximum réglementaire de l''aliment complet ovin — risque de toxicité cuprique (Texel et races sensibles) ; ne pas distribuer seul à des ovins, vérifier l''échantillon.',ref.f_ref('REG2018_1039'),'publie'),
 ('CU',NULL,NULL,NULL,NULL,'sanitaire',30,NULL,'valeur_anormale',3,'Cu > 30 mg/kg MS sur un fourrage : valeur anormale — contamination (minéral, terre riche) ou erreur analytique ; faire vérifier par le laboratoire.',ref.f_ref('GDS24_CAMPAGNE2025'),'hypothese_locale'),
 ('ZN',NULL,NULL,NULL,NULL,'nutritionnel',NULL,30,'tres_bas',2,'Zn < 30 mg/kg MS : apport très insuffisant (besoin 50) ; complémentation indispensable (immunité, onglons, fertilité).',ref.f_ref('VIRBAC2022'),'publie_source_secondaire'),
 ('ZN',NULL,NULL,NULL,NULL,'nutritionnel',30,50,'insuffisant',1,'Zn entre 30 et 50 mg/kg MS : en-dessous de l''apport recommandé.',ref.f_ref('VIRBAC2022'),'publie_source_secondaire'),
 ('FE',NULL,NULL,NULL,NULL,'sanitaire',300,500,'contamination_possible',1,'Fe 300-500 mg/kg MS : suspicion de terre ; antagonisme possible sur Cu et Zn.',ref.f_ref('SCHLEGEL2013'),'consensus_professionnel'),
 ('FE',NULL,NULL,NULL,NULL,'sanitaire',500,NULL,'contamination',2,'Fe > 500 mg/kg MS : contamination tellurique ou anomalie ; vérifier MM, hauteur de coupe, propreté de l''échantillon ; biodisponibilité Cu/Zn réduite.',ref.f_ref('SCHLEGEL2013'),'consensus_professionnel'),
 ('MN',NULL,NULL,NULL,NULL,'nutritionnel',NULL,40,'bas',1,'Mn < 40 mg/kg MS : en-dessous du besoin (50) ; fréquent sur légumineuses et maïs.',ref.f_ref('VIRBAC2022'),'publie_source_secondaire'),
 ('SE',NULL,NULL,NULL,NULL,'sanitaire',NULL,0.1,'carence_probable',2,'Se < 0,1 mg/kg MS : carence probable (myopathie des jeunes, rétentions placentaires, immunité) ; profil métabolique conseillé.',ref.f_ref('MESCHY2007'),'publie'),
 ('MO_OLIGO',NULL,NULL,NULL,NULL,'sanitaire',3,NULL,'antagoniste_cu',2,'Mo > 3 mg/kg MS : risque de carence cuprique induite (thiomolybdates) ; interpréter avec Cu et S.',ref.f_ref('EFSA2016CU'),'consensus_professionnel'),
-- Mycotoxines (valeurs indicatives UE, aliments à 12 % d''humidité ≈ 88 % MS ; unités µg/kg)
 ('DON',NULL,NULL,NULL,'VEAU','reglementaire',2000,NULL,'depasse_indicatif_jeunes',2,'DON > 2 mg/kg : au-dessus de la valeur indicative UE pour veaux, agneaux et chevreaux.',ref.f_ref('RECO2006_576'),'publie_source_secondaire'),
 ('DON',NULL,NULL,NULL,'AGNEAU','reglementaire',2000,NULL,'depasse_indicatif_jeunes',2,'DON > 2 mg/kg : au-dessus de la valeur indicative UE pour les agneaux.',ref.f_ref('RECO2006_576'),'publie_source_secondaire'),
 ('DON',NULL,NULL,NULL,'CHEVRETTE','reglementaire',2000,NULL,'depasse_indicatif_jeunes',2,'DON > 2 mg/kg : au-dessus de la valeur indicative UE pour les chevreaux.',ref.f_ref('RECO2006_576'),'publie_source_secondaire'),
 ('DON',NULL,NULL,NULL,NULL,'reglementaire',5000,NULL,'depasse_indicatif',2,'DON > 5 mg/kg : au-dessus de la valeur indicative UE (aliments composés) ; diluer le lot, surveiller ingestion et diarrhées.',ref.f_ref('RECO2006_576'),'publie_source_secondaire'),
 ('DON',NULL,NULL,NULL,NULL,'sanitaire',1000,5000,'present',1,'DON détecté à un niveau notable : vigilance pour les jeunes et les animaux en acidose (détoxification ruminale réduite).',ref.f_ref('RECO2006_576'),'consensus_professionnel'),
 ('ZEA',NULL,NULL,NULL,NULL,'reglementaire',500,NULL,'depasse_indicatif',2,'Zéaralénone > 0,5 mg/kg : au-dessus de la valeur indicative UE pour veaux, vaches laitières, ovins et caprins ; effet œstrogénique (fertilité, avortements).',ref.f_ref('RECO2006_576'),'publie_source_secondaire'),
 ('ZEA',NULL,NULL,NULL,NULL,'sanitaire',250,500,'proche_indicatif',1,'Zéaralénone entre 0,25 et 0,5 mg/kg : proche de la valeur indicative ; vigilance reproduction, ne pas concentrer le lot chez les femelles en reproduction.',ref.f_ref('RECO2006_576'),'consensus_professionnel'),
 ('FUM',NULL,NULL,NULL,'VEAU','reglementaire',20000,NULL,'depasse_indicatif_jeunes',2,'Fumonisines > 20 mg/kg : au-dessus de la valeur indicative UE pour les veaux, agneaux et chevreaux.',ref.f_ref('RECO2006_576'),'publie_source_secondaire'),
 ('FUM',NULL,NULL,NULL,NULL,'reglementaire',50000,NULL,'depasse_indicatif',2,'Fumonisines > 50 mg/kg : au-dessus de la valeur indicative UE pour ruminants adultes.',ref.f_ref('RECO2006_576'),'publie_source_secondaire'),
 ('AFB1',NULL,NULL,NULL,NULL,'reglementaire',5,NULL,'depasse_max_laitieres',3,'Aflatoxine B1 > 5 µg/kg : dépasse la teneur maximale réglementaire pour animaux laitiers et jeunes (Dir. 2002/32/CE) ; transfert AFM1 dans le lait.',ref.f_ref('DIR2002_32'),'publie_source_secondaire'),
-- Conservation
 ('NH3_NTOT',NULL,NULL,NULL,NULL,'sanitaire',15,NULL,'proteolyse_forte',2,'N-NH3 > 15 % de N total : protéolyse importante, ingestion et PDI dégradées.',ref.f_ref('AFSSA2004'),'publie'),
 ('AC_BUTYR',NULL,NULL,NULL,NULL,'sanitaire',5,NULL,'butyrique',2,'Acide butyrique > 5 g/kg MS : ensilage butyrique (clostridies) ; limiter à < 50 g d''acide butyrique ingéré/j, écarter pour les laitières.',ref.f_ref('AFSSA2004'),'publie'),
 ('NITRATES',NULL,NULL,NULL,NULL,'sanitaire',5000,10000,'prudence',1,'Nitrates 5 000-10 000 mg/kg MS : prudence (gestantes), dilution, adaptation progressive.',ref.f_ref('AFSSA2004'),'publie_source_secondaire'),
 ('NITRATES',NULL,NULL,NULL,NULL,'sanitaire',10000,NULL,'toxique',3,'Nitrates > 10 000 mg/kg MS : potentiellement toxique (méthémoglobinémie) sans adaptation.',ref.f_ref('AFSSA2004'),'publie_source_secondaire');

-- Besoins / apports recommandés (extraits utilisés dans les restitutions ; à compléter avec le livre rouge INRA 2018)
INSERT INTO ref.besoin_animal (categorie_code, stade, poids_vif_kg, production, parametre_code, valeur, unite, reference_id, statut_epistemique, commentaire) VALUES
 ('VL','lactation',650,'par kg de lait (TB 40)','UFL_2018',0.42,'UFL par kg lait',ref.f_ref('NOZIERE2026A'),'publie','0,44 en INRA 2007'),
 ('VL','lactation',650,'par kg de lait','PDI',48,'g par kg lait',ref.f_ref('NOZIERE2026A'),'publie','42 à 55 g selon concentration PDI de la ration'),
 ('VL','entretien',650,NULL,'UFL_2018',5.3,'UFL par jour',ref.f_ref('INRA2018'),'ordre_de_grandeur','0,041 × PV^0,75 × Iact'),
 ('VL','lactation',NULL,'ration','CU',10,'mg/kg MS ration',ref.f_ref('VIRBAC2022'),'secondaire','INRA 2018'),
 ('VL','lactation',NULL,'ration','ZN',50,'mg/kg MS ration',ref.f_ref('VIRBAC2022'),'secondaire','INRA 2018'),
 ('VL','lactation',NULL,'ration','MN',50,'mg/kg MS ration',ref.f_ref('VIRBAC2022'),'secondaire','INRA 2018'),
 ('VL','lactation',NULL,'ration','SE',0.2,'mg/kg MS ration',ref.f_ref('VIRBAC2022'),'secondaire','0,1-0,2 INRA ; 0,3 NASEM 2021'),
 ('VL','lactation',NULL,'ration','I',0.6,'mg/kg MS ration',ref.f_ref('VIRBAC2022'),'secondaire','0,5-0,8'),
 ('VL','lactation',NULL,'ration','CO',0.3,'mg/kg MS ration',ref.f_ref('MESCHY2007'),'publie','relevé de 0,1 à 0,3 pour la cellulolyse'),
 ('VL','debut_lactation',NULL,'ration','CA',6.4,'g/kg MS ration',ref.f_ref('NASEM2021'),'secondaire',NULL),
 ('VL','debut_lactation',NULL,'ration','P',3.9,'g/kg MS ration',ref.f_ref('NASEM2021'),'secondaire',NULL),
 ('VL','debut_lactation',NULL,'ration','MG',1.8,'g/kg MS ration',ref.f_ref('NASEM2021'),'secondaire',NULL),
 ('VL','debut_lactation',NULL,'ration','NA',2.2,'g/kg MS ration',ref.f_ref('NASEM2021'),'secondaire',NULL),
 ('VL_TARIE','fin_gestation',NULL,'ration','BACA',50,'mEq/kg MS ration (maximum)',ref.f_ref('MESCHY2007'),'publie','BE < 50 ; BACA −50 à −150 avec sels anioniques'),
 ('BREBIS_ENT','entretien',70,NULL,'UFL_2018',0.8,'UFL par jour',ref.f_ref('INRA2018'),'ordre_de_grandeur','Sagot 2017 (Idele-Ciirpo)'),
 ('BREBIS_ENT','entretien',70,NULL,'PDI',60,'g par jour',ref.f_ref('INRA2018'),'ordre_de_grandeur',NULL),
 ('BREBIS_ALL','fin_gestation',55,'portée simple','UFL_2018',1.24,'UFL par jour',ref.f_ref('INRA2018'),'ordre_de_grandeur','CDEO 2007'),
 ('BREBIS_ALL','debut_lactation',55,'2 L/j','UFL_2018',2.41,'UFL par jour',ref.f_ref('INRA2018'),'ordre_de_grandeur','CDEO 2007'),
 ('BREBIS_ALL',NULL,NULL,'ration','CU',8,'mg/kg MS ration (max 15)',ref.f_ref('EFSA2016CU'),'publie','4-8 mg/kg MS ; toxicité au-delà de 15'),
 ('CHEVRE','lactation',60,'par kg de lait standard','UFL_2018',0.389,'UFL par kg lait',ref.f_ref('NOZIERE2026A'),'publie','Sauvant et al. 2022'),
 ('CHEVRE','entretien',60,NULL,'UFL_2018',0.88,'UFL par jour',ref.f_ref('NOZIERE2026A'),'publie','0,0406 × PV^0,75'),
 ('CHEVRE','lactation',NULL,'ration','CU',20,'mg/kg MS ration',ref.f_ref('NRC2007'),'secondaire','15-25 (Merck) ; max UE 35');

-- Fin du fichier 02
