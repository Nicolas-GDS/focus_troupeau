#!/usr/bin/env python3
"""Contrôle de cohérence des CSV de référentiel « minéraux × troubles ».

Usage : python3 valider_seeders.py [dossier_ref] [schema.sql] [referentiels.sql]

Vérifie :
- la structure des CSV (séparateur ;, en-têtes, nombre de colonnes) ;
- que chaque parametre_code existe dans ref.parametre (schéma 01) ;
- que chaque espece_code / categorie_code existe dans ref.espece_animale / ref.categorie_animale ;
- que chaque trouble_code de la compatibilité existe dans trouble_sanitaire.csv ;
- que chaque reference_cle existe dans ref.reference_biblio (script 02) ou dans reference_biblio_complement.csv,
  et qu'aucune clé du complément ne doublonne une clé existante ;
- les domaines de valeurs (sens, force, statut_epistemique, type_repere, statut_verif).
Sans les scripts SQL, les contrôles de clés étrangères vers ref.* sont faits sur des listes intégrées
(extraites des scripts le 15/10/2026).
"""
import csv
import re
import sys
from pathlib import Path

PARAMS_CONNUS = set("""MS MM MO MAT CB DCS SSR AMI MGT PH NDF ADF ADL P CA MG K NA S CL CU ZN MN FE AL SE I CO MO_OLIGO
DMO UFL UFV PDIN PDIE PDIA UEL UEB UEM DNDF DMONA AMIDR P_ABS CA_ABS BACA BE UFL_2018 UFV_2018 PDI PDIA_2018 BPR
DT6_N DT6_AMI AMIDR_2018 AMI_BYPASS NI EB NH3_NTOT AC_LACT AC_ACET AC_BUTYR SPORES_BUT DON ZEA FUM AFB1 OTA NITRATES
K_CAMG_EQ CA_P CU_MO PDI_UFL""".split())
ESPECES_CONNUES = {"BOV", "OVI", "CAP"}
CATEGORIES_CONNUES = set("VL VL_TARIE VA GEN JB VEAU BREBIS_LAIT BREBIS_ALL BREBIS_ENT AGNEAU CHEVRE CHEVRETTE BOUC_MALE".split())
REFS_EXISTANTES = set("""INRA2018 NOZIERE2026A NOZIERE2026B FEEDTABLES MESCHY2007 MESCHY2010 NASEM2021 NRC2007 KEMP1957
MERCK_PARESIS MERCK_TETANY MERCK_CU EFSA2016CU REG2018_1039 REG2016_1095 REG2017_2330 REG2015_861 REG121_2014 DIR2002_32
RECO2006_576 RECO2013_165 AFSSA2004 ARVALIS2024 ROCHEREAU2024 CA_AVEYRON2024 BAUMONT2009 SCHLEGEL2013 ARAD2_2023 VIRBAC2022
BONSILAGE2013 LANO_REFNORMANDIE2009 GDS24_CAMPAGNE2025""".split())
STATUTS_EPI = {"publie", "publie_source_secondaire", "consensus_professionnel", "ordre_de_grandeur", "hypothese_locale"}
STATUTS_VERIF = {"verifie", "secondaire", "non_verifie"}
TYPES_SOURCE = {"ouvrage", "article", "reglementation", "note_technique", "manuel", "table", "site", "these"}
SENS = {"carence", "exces", "ratio_bas", "ratio_eleve"}
TYPES_REPERE = {"besoin", "seuil_carence_fourrage", "max_tolerable", "max_reglementaire_ue"}


def lire(path):
    with open(path, encoding="utf-8", newline="") as f:
        rows = list(csv.DictReader(f, delimiter=";"))
    return rows


def extraire_codes_sql(schema_sql, referentiels_sql):
    """Si les scripts SQL sont fournis, en extrait les codes pour remplacer les listes intégrées."""
    params, especes, cats, refs = set(), set(), set(), set()
    if schema_sql and Path(schema_sql).exists():
        txt = Path(schema_sql).read_text(encoding="utf-8", errors="replace")
        for bloc, cible in (("ref.parametre (", params), ("ref.categorie_animale VALUES", cats)):
            i = txt.find("INSERT INTO " + bloc)
            if i >= 0:
                fin = txt.find(";", i)
                cible.update(re.findall(r"\(\s*'([A-Z0-9_]+)'", txt[i:fin]))
        i = txt.find("INSERT INTO ref.espece_animale")
        if i >= 0:
            especes.update(re.findall(r"\('([A-Z]+)'", txt[i:txt.find(";", i)]))
    if referentiels_sql and Path(referentiels_sql).exists():
        txt = Path(referentiels_sql).read_text(encoding="utf-8", errors="replace")
        i = txt.find("INSERT INTO ref.reference_biblio")
        if i >= 0:
            refs.update(re.findall(r"\(\s*'([A-Z0-9_]+)'", txt[i:txt.find(";\n", i)]))
    return params or PARAMS_CONNUS, especes or ESPECES_CONNUES, cats or CATEGORIES_CONNUES, refs or REFS_EXISTANTES


def main():
    dossier = Path(sys.argv[1]) if len(sys.argv) > 1 else Path(__file__).parent / "ref"
    schema_sql = sys.argv[2] if len(sys.argv) > 2 else None
    ref_sql = sys.argv[3] if len(sys.argv) > 3 else None
    params, especes, cats, refs_exist = extraire_codes_sql(schema_sql, ref_sql)
    erreurs, avert = [], []

    troubles = lire(dossier / "trouble_sanitaire.csv")
    compat = lire(dossier / "compatibilite_parametre_trouble.csv")
    reperes = lire(dossier / "repere_mineral.csv")
    biblio = lire(dossier / "reference_biblio_complement.csv")

    cles_compl = [r["cle"] for r in biblio]
    doublons = set(cles_compl) & refs_exist
    if doublons:
        erreurs.append(f"Clés du complément déjà présentes dans ref.reference_biblio : {sorted(doublons)}")
    if len(cles_compl) != len(set(cles_compl)):
        erreurs.append("Clés dupliquées dans reference_biblio_complement.csv")
    for r in biblio:
        if r["statut_verif"] not in STATUTS_VERIF:
            erreurs.append(f"biblio {r['cle']} : statut_verif invalide {r['statut_verif']!r}")
        if r["type_source"] not in TYPES_SOURCE:
            erreurs.append(f"biblio {r['cle']} : type_source invalide {r['type_source']!r}")
        if r["annee"] and not re.fullmatch(r"\d{4}", r["annee"]):
            erreurs.append(f"biblio {r['cle']} : annee non numérique {r['annee']!r}")
    refs_ok = refs_exist | set(cles_compl)

    codes_trouble = [r["code"] for r in troubles]
    if len(codes_trouble) != len(set(codes_trouble)):
        erreurs.append("Codes dupliqués dans trouble_sanitaire.csv")
    for r in troubles:
        if not re.fullmatch(r"[a-z0-9_]+", r["code"]):
            erreurs.append(f"trouble {r['code']} : code non conforme (minuscules, chiffres, _)")
        for e in filter(None, r["especes"].split("|")):
            if e not in especes:
                erreurs.append(f"trouble {r['code']} : espèce inconnue {e}")
        for c in filter(None, r["categories_cibles"].split("|")):
            if c not in cats:
                erreurs.append(f"trouble {r['code']} : catégorie inconnue {c}")
        if r["reference_cle"] not in refs_ok:
            erreurs.append(f"trouble {r['code']} : référence inconnue {r['reference_cle']}")
        if r["statut_epistemique"] not in STATUTS_EPI:
            erreurs.append(f"trouble {r['code']} : statut invalide {r['statut_epistemique']!r}")

    vus = set()
    for i, r in enumerate(compat, start=2):
        cle = (r["parametre_code"], r["sens"], r["trouble_code"], r["espece_code"], r["categorie_code"])
        if cle in vus:
            erreurs.append(f"compat ligne {i} : doublon {cle}")
        vus.add(cle)
        if r["parametre_code"] not in params:
            erreurs.append(f"compat ligne {i} : paramètre inconnu {r['parametre_code']}")
        if r["sens"] not in SENS:
            erreurs.append(f"compat ligne {i} : sens invalide {r['sens']!r}")
        if r["trouble_code"] not in codes_trouble:
            erreurs.append(f"compat ligne {i} : trouble inconnu {r['trouble_code']}")
        for e in filter(None, r["espece_code"].split("|")):
            if e not in especes:
                erreurs.append(f"compat ligne {i} : espèce inconnue {e}")
        for c in filter(None, r["categorie_code"].split("|")):
            if c not in cats:
                erreurs.append(f"compat ligne {i} : catégorie inconnue {c}")
        if r["force"] not in {"1", "2", "3"}:
            erreurs.append(f"compat ligne {i} : force invalide {r['force']!r}")
        if r["reference_cle"] not in refs_ok:
            erreurs.append(f"compat ligne {i} : référence inconnue {r['reference_cle']}")
        if r["statut_epistemique"] not in STATUTS_EPI:
            erreurs.append(f"compat ligne {i} : statut invalide {r['statut_epistemique']!r}")
        if r["force"] == "3" and r["statut_epistemique"] in {"ordre_de_grandeur", "hypothese_locale"}:
            avert.append(f"compat ligne {i} : force 3 avec statut faible")

    for i, r in enumerate(reperes, start=2):
        if r["parametre_code"] not in params:
            erreurs.append(f"repere ligne {i} : paramètre inconnu {r['parametre_code']}")
        if r["espece_code"] and r["espece_code"] not in especes:
            erreurs.append(f"repere ligne {i} : espèce inconnue {r['espece_code']}")
        if r["categorie_code"] and r["categorie_code"] not in cats:
            erreurs.append(f"repere ligne {i} : catégorie inconnue {r['categorie_code']}")
        if r["type_repere"] not in TYPES_REPERE:
            erreurs.append(f"repere ligne {i} : type_repere invalide {r['type_repere']!r}")
        for col in ("valeur_min", "valeur_max"):
            if r[col] and not re.fullmatch(r"\d+(\.\d+)?", r[col]):
                erreurs.append(f"repere ligne {i} : {col} non numérique {r[col]!r}")
        if r["reference_cle"] not in refs_ok:
            erreurs.append(f"repere ligne {i} : référence inconnue {r['reference_cle']}")
        if r["statut_epistemique"] not in STATUTS_EPI:
            erreurs.append(f"repere ligne {i} : statut invalide {r['statut_epistemique']!r}")

    # Chapitre 12 : interactions et causes de valeurs atypiques
    COMPARTIMENTS = {"sol_plante", "contamination", "rumen", "absorption", "metabolisme"}
    FACTEUR_TYPES = {"parametre", "agronomique", "animal", "analytique"}
    SENS_EFFET = {"diminue", "augmente"}
    CATEGORIES_CAUSE = {"geochimie_sol", "agronomie", "physiologie_plante", "contamination_echantillon",
                        "analytique", "conservation", "melange_aliment"}
    interactions = lire(dossier / "interaction_element.csv") if (dossier / "interaction_element.csv").exists() else []
    causes = lire(dossier / "cause_valeur_atypique.csv") if (dossier / "cause_valeur_atypique.csv").exists() else []
    for i, r in enumerate(interactions, start=2):
        if r["compartiment"] not in COMPARTIMENTS:
            erreurs.append(f"interaction ligne {i} : compartiment invalide {r['compartiment']!r}")
        if r["facteur_type"] not in FACTEUR_TYPES:
            erreurs.append(f"interaction ligne {i} : facteur_type invalide {r['facteur_type']!r}")
        if r["facteur_type"] == "parametre" and r["facteur_code"] not in params:
            erreurs.append(f"interaction ligne {i} : facteur paramètre inconnu {r['facteur_code']}")
        if r["facteur_type"] != "parametre" and not re.fullmatch(r"[A-Z0-9_]+", r["facteur_code"]):
            erreurs.append(f"interaction ligne {i} : facteur_code non conforme {r['facteur_code']!r}")
        if r["element_cible"] not in params:
            erreurs.append(f"interaction ligne {i} : élément cible inconnu {r['element_cible']}")
        if r["sens_effet"] not in SENS_EFFET:
            erreurs.append(f"interaction ligne {i} : sens_effet invalide {r['sens_effet']!r}")
        if r["reference_cle"] not in refs_ok:
            erreurs.append(f"interaction ligne {i} : référence inconnue {r['reference_cle']}")
        if r["statut_epistemique"] not in STATUTS_EPI:
            erreurs.append(f"interaction ligne {i} : statut invalide {r['statut_epistemique']!r}")
    for i, r in enumerate(causes, start=2):
        if r["parametre_code"] not in params:
            erreurs.append(f"cause ligne {i} : paramètre inconnu {r['parametre_code']}")
        if r["sens"] not in {"bas", "eleve"}:
            erreurs.append(f"cause ligne {i} : sens invalide {r['sens']!r}")
        if not re.fullmatch(r"[A-Z0-9_]+", r["cause_code"]):
            erreurs.append(f"cause ligne {i} : cause_code non conforme {r['cause_code']!r}")
        if r["categorie"] not in CATEGORIES_CAUSE:
            erreurs.append(f"cause ligne {i} : catégorie invalide {r['categorie']!r}")
        if r["reference_cle"] not in refs_ok:
            erreurs.append(f"cause ligne {i} : référence inconnue {r['reference_cle']}")
        if r["statut_epistemique"] not in STATUTS_EPI:
            erreurs.append(f"cause ligne {i} : statut invalide {r['statut_epistemique']!r}")
    if interactions or causes:
        print(f"interaction_element : {len(interactions)} lignes ; cause_valeur_atypique : {len(causes)}")

    refs_utilisees = ({r["reference_cle"] for r in troubles} | {r["reference_cle"] for r in compat}
                      | {r["reference_cle"] for r in reperes} | {r["reference_cle"] for r in interactions}
                      | {r["reference_cle"] for r in causes})
    inutilisees = set(cles_compl) - refs_utilisees
    if inutilisees:
        avert.append(f"Références du complément non citées dans les CSV (citées dans la synthèse seulement) : {sorted(inutilisees)}")
    troubles_sans_lien = set(codes_trouble) - {r["trouble_code"] for r in compat}
    if troubles_sans_lien:
        avert.append(f"Troubles sans ligne de compatibilité : {sorted(troubles_sans_lien)}")

    print(f"trouble_sanitaire : {len(troubles)} lignes ; compatibilite : {len(compat)} ; repere_mineral : {len(reperes)} ; biblio complément : {len(biblio)}")
    for a in avert:
        print("AVERTISSEMENT :", a)
    for e in erreurs:
        print("ERREUR :", e)
    print("Résultat :", "OK" if not erreurs else f"{len(erreurs)} erreur(s)")
    return 1 if erreurs else 0


if __name__ == "__main__":
    sys.exit(main())
