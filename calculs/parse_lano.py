# -*- coding: utf-8 -*-
"""Parseur des bulletins LANO (pdftotext -layout) -> table structurée."""
import re, os, glob, json, csv, sys

SRC = os.path.expanduser("~/fourrages_txt")

# Codes de paramètres reconnus en début de ligne (ordre : les plus longs d'abord)
CODES = [
    ("UFL 2018","UFL_2018"),("UFV 2018","UFV_2018"),("PDIA 2018","PDIA_2018"),
    ("DT6_N","DT6_N"),("DT6 amidon","DT6_AMI"),("dMOna","dMOna"),("dNDF","dNDF"),("dMO","dMO"),
    ("P abs","P_abs"),("Ca abs","Ca_abs"),("BACA","BACA"),("BPR","BPR"),("PDIN","PDIN"),("PDIE","PDIE"),
    ("PDIA","PDIA"),("PDI","PDI"),("UFL","UFL"),("UFV","UFV"),("UEL","UEL"),("UEB","UEB"),("UEM","UEM"),
    ("MAT","MAT"),("MGT","MGT"),("MM","MM"),("MO","MO"),("MS","MS"),("CB","CB"),("DCS","DCS"),("SSR","SSR"),
    ("AMI","AMI"),("LIG","LIG"),("NDF","NDF"),("ADF","ADF"),("ADL","ADL"),("MG","MG"),("pH","pH"),
    ("Mg","Mg"),("Mn","Mn"),("Ca","Ca"),("Cu","Cu"),("Zn","Zn"),("Fe","Fe"),("Na","Na"),("Al","Al"),
    ("NI","NI"),("EB","EB"),("BE","BE"),("P","P"),("K","K"),("S","S"),
]
# Libellés sans code (lignes de valeur orphelines)
LABELS = [
    (r"^\s*Amidon d[ée]gradable dans le rumen", "AMIDR"),      # section 2007 (INRA 2016)
    (r"^\s*Amidon D[ée]gradable dans le Rumen", "AMIDR_2018"),
    (r"^\s*Amidon by-pass", "AMI_BYPASS"),
    (r"^\s*Amidon\b", "AMI"),
    (r"^\s*Lignine\b", "ADL"),
]
UNITS = r"(% brut|g/kg MS|mg/kg MS|UFL/kg MS|UFV/kg MS|UEL/kg MS|UEB/kg MS|UEM/kg MS|mEq/kg MS|Kcal/kg MS|µg/kg sec|%|-)"
unit_re = re.compile(UNITS + r"(?=\s{2,}\S|\s*$)")
num_re = re.compile(r"(<?\s*-?\d{1,3}(?: \d{3})*(?:,\d+)?|<?\s*-?\d+(?:,\d+)?)\s*$")

def tofloat(s):
    s = s.replace(" ", "").replace(",", ".")
    flag = ""
    if s.startswith("<"):
        flag = "<"; s = s[1:]
    try:
        return float(s), flag
    except ValueError:
        return None, ""

def parse_header(lines):
    txt = "\n".join(lines)
    h = {}
    def grab(pat, flags=0):
        m = re.search(pat, txt, flags)
        return m.group(1).strip() if m else None
    h["num_echantillon"] = grab(r"N° ÉCHANTILLON LANO\s+(HA\d{2}-\d+)")
    h["date_prelevement"] = grab(r"Date de prélèvement\s*:\s*(\d{2}/\d{2}/\d{4})")
    h["date_reception"] = grab(r"Date de réception\s*:\s*(\d{2}/\d{2}/\d{4})")
    h["date_edition"] = grab(r"Date d'édition(?: Rapport)?\s*:\s*(\d{2}/\d{2}/\d{4})")
    h["code_dossier"] = grab(r"Code Dossier\s*:\s*(\d+)")
    # type de rapport : texte des 6 premières lignes hors colonne de droite
    head = []
    for l in lines[:8]:
        left = re.split(r"\s{2,}(?:N° ÉCHANTILLON|Date d|Code Dossier)", l)[0]
        left = re.sub(r"\s{2,}", " ", left).strip()
        if left and not re.match(r"^(N° ÉCHANTILLON|Date|Code Dossier|Fo|En|Ve)$", left):
            head.append(left)
    h["type_rapport"] = " ".join(head)
    h["tiers"] = grab(r"TIERS\s*:\s*(.+?)\s{2,}")
    # nom éleveur = colonne droite de la ligne TIERS
    m = re.search(r"TIERS\s*:\s*.+?\s{2,}(.+)$", txt, re.M)
    h["eleveur"] = m.group(1).strip() if m else None
    # colonne de droite (bloc AGRICULTEUR/ÉLEVEUR) : lignes entre TIERS et le CP
    right = []
    started = False
    for l in lines[:30]:
        if "TIERS" in l and ":" in l: started = True; continue
        if not started: continue
        if "CARACTÉRISTIQUES" in l: break
        seg = re.split(r"\s{2,}", l.strip())
        cand = seg[-1] if seg else ""
        if not cand or cand.startswith(("Région/dépôt", "Nom technicien", "NUMERO DE CLIENT", "OU NUMERO")): continue
        if re.fullmatch(r"\d{5,8}", cand): continue          # n° client
        right.append(cand)
    addr = [x for x in right if not re.match(r"^\d{5} ", x)]
    h["adresse"] = " / ".join(addr)
    h["technicien"] = None   # jamais renseigné sur les bulletins LANO de la campagne
    h["client_lano"] = grab(r"NUMERO DE CLIENT LI?LANO[\s\S]*?\n\s*(\d{5,8})\s*\n")
    cp = re.search(r"(?<![\d\-])(\d{5})[ \t]+([A-Z][A-Z' \-]+?)[ \t]*$", txt, re.M)
    if cp:
        h["code_postal"], h["commune"] = cp.group(1), cp.group(2).strip()
    else:
        h["code_postal"] = h["commune"] = None
    # adresse : ligne(s) entre technicien et CP dans colonne droite -> on prend la ligne au-dessus du CP
    h["nom_echantillon"] = grab(r"NOM DE L(?:A PARCELLE/DU|'ECHANTILLON)\s*(?:SILO)?\s+([A-Z0-9].+?)\s*$", re.M)
    if h["nom_echantillon"]:
        h["nom_echantillon"] = re.sub(r"\s{2,}", " ", h["nom_echantillon"])
    # caractéristiques
    h["espece_fourrage"] = grab(r"Type d[e ']?\s?(?:foin|ensilage)/espèce\s*:\s*(.+?)(?:\s{2,}|\n)")
    h["cycle_coupe"] = grab(r"N° de cycle/coupe\s*:\s*(.+?)(?:\s{2,}|\n)")
    h["date_recolte"] = grab(r"Date de récolte\s*:\s*(\d{2}/\d{2}/\d{4})")
    h["date_semis"] = grab(r"Date de semis\s*:\s*(\d{2}/\d{2}/\d{4})")
    h["date_ouverture_silo"] = grab(r"Date d'ouverture du silo\s*:\s*(\d{2}/\d{2}/\d{4})")
    h["type_prelevement"] = grab(r"Type de prélèvement\s*:\s*(.+?)(?:\s{2,}|\n)")
    h["mode_sechage"] = grab(r"Mode de séchage\s*:\s*(.+?)(?:\s{2,}|\n)")
    h["mode_ensilage"] = grab(r"Mode d'ensilage\s*:\s*(.+?)(?:\s{2,}|\n)")
    h["etat_actuel"] = grab(r"[EÉ]tat actuel (?:de l'herbe|du maïs)\s*:\s*(.+?)(?:\s{2,}|\n)")
    h["conservateur"] = grab(r"Conservateur\s*:\s*([A-Za-z].+?)(?:\s{2,}|\n)")
    h["variete"] = grab(r"Variété dominante 1\s*:\s*([A-Za-z0-9].+?)(?:\s{2,}|\n)")
    h["aliment"] = grab(r"aliment\s*:\s*(.+?)(?:\s{2,}|\n)")
    return h

def parse_results(lines):
    res = {}
    cur = None
    section = "2007"
    for raw in lines:
        line = raw.rstrip("\n")
        s = line.strip()
        if "INRA 2018" in s and "SYSTALI" in s.upper():
            section = "2018"
        if s.startswith("COMPLÉMENTS") or s.startswith("COMPLEMENTS"):
            section = "END"
        # code en début de ligne ?
        matched_code = None
        for c, key in CODES:
            if re.match(r"^\s*" + re.escape(c) + r"(?=\s|$)", line):
                matched_code = key; break
        if matched_code is None:
            for pat, key in LABELS:
                if re.match(pat, line):
                    matched_code = key; break
        if matched_code:
            # désambiguïsation section 2018 pour les codes partagés
            if section == "2018" and matched_code in ("UFL","UFV","PDIA"):
                matched_code += "_2018"
            if section == "2018" and matched_code == "AMIDR":
                matched_code = "AMIDR_2018"
            if section == "2007" and matched_code == "NI":
                matched_code = "NI"   # NI existe en 2018 normalement ; garde tel quel
            cur = matched_code
        m = unit_re.search(line)
        if not m or cur is None:
            continue
        unit = m.group(1)
        if unit == "-" and cur not in ("NI", "pH"):
            continue
        pre = line[:m.start()].rstrip()
        n = num_re.search(pre)
        if not n:
            continue
        gap = m.start() - n.end()
        # le résultat est immédiatement à gauche de l'unité ; les n° de méthode sont loin
        if gap > 22:
            continue
        val, flag = tofloat(n.group(1))
        if val is None:
            continue
        # garde-fou : les n° de normes (ex. 6493, 8506, 2007) ne sont jamais des valeurs plausibles avec ces unités
        if val in (6493.0, 6492.0, 8506.0, 2007.0, 2004.0, 2016.0, 2018.0, 15510.0, 11885.0) and cur not in ("EB",):
            continue
        if cur in res:   # première valeur seulement
            continue
        res[cur] = val if not flag else f"<{val:g}"
        res[cur + "__unit"] = unit
    return res

def parse_myco(lines):
    txt = "\n".join(lines)
    res = {}
    for key, pat in [("DON", r"Veratox DON[^\n]*?\s(<?\s*\d[\d ,]*)\s+µg/kg sec"),
                     ("ZEA", r"Veratox Zearalenone[^\n]*?\s(<?\s*\d[\d ,]*)\s+µg/kg sec"),
                     ("FUM", r"Veratox HS Fumonisine[^\n]*?\s(<?\s*\d[\d ,]*)\s+µg/kg sec")]:
        m = re.search(pat, txt)
        if m:
            v, flag = tofloat(m.group(1))
            res[key] = f"<{v:g}" if flag else v
            res[key + "__unit"] = "µg/kg sec"
    return res

rows = []
for f in sorted(glob.glob(os.path.join(SRC, "*.txt"))):
    lines = open(f, encoding="utf-8").read().split("\n")
    h = parse_header(lines)
    h["fichier"] = os.path.basename(f).replace(".txt", ".pdf")
    if "MYCOTOXINES" in h["type_rapport"].upper():
        h["famille_rapport"] = "MYCOTOXINES"
        h.update(parse_myco(lines))
    else:
        tr = h["type_rapport"].upper()
        if "MAÏS" in tr or "MAIS" in tr: fam = "ENSILAGE_MAIS"
        elif "FOINS" in tr: fam = "FOIN"
        elif "ENSILAGES/ENRUBANNAGES" in tr: fam = "ENSILAGE_ENRUBANNAGE_HERBE"
        elif "DIVERS" in tr: fam = "FOURRAGE_DIVERS"
        elif "CONCENTRE" in tr: fam = "CONCENTRE"
        elif "VERTS" in tr: fam = "FOURRAGE_VERT"
        else: fam = "AUTRE"
        h["famille_rapport"] = fam
        h.update(parse_results(lines))
    rows.append(h)

# colonnes ordonnées
meta = ["num_echantillon","fichier","famille_rapport","type_rapport","date_prelevement","date_reception","date_edition",
        "code_dossier","tiers","eleveur","adresse","technicien","client_lano","code_postal","commune","nom_echantillon",
        "espece_fourrage","cycle_coupe","date_recolte","date_semis","date_ouverture_silo","type_prelevement",
        "mode_sechage","mode_ensilage","etat_actuel","conservateur","variete","aliment"]
params = ["MS","MM","MO","MAT","CB","DCS","SSR","AMI","MGT","MG","LIG","pH","NDF","ADF","ADL",
          "P","Ca","Mg","K","Na","Cu","Zn","Mn","Fe","S","Al",
          "dMO","UFL","UFV","PDIN","PDIE","PDIA","UEL","UEB","UEM","AMIDR","dMOna","dNDF","P_abs","Ca_abs","BACA","BE",
          "UFL_2018","UFV_2018","PDI","PDIA_2018","BPR","DT6_N","DT6_AMI","AMIDR_2018","AMI_BYPASS","NI","EB",
          "DON","ZEA","FUM"]
units = {}
for r in rows:
    for p in params:
        u = r.get(p + "__unit")
        if u: units.setdefault(p, u)
cols = meta + params
out = os.path.expanduser("~/mnt/Approche globale/Livrables/analyses_fourrages_LANO_extraction.csv")
with open(out, "w", newline="", encoding="utf-8-sig") as fh:
    w = csv.writer(fh, delimiter=";")
    w.writerow(cols)
    w.writerow([""]*len(meta) + [units.get(p, "") for p in params])
    for r in rows:
        w.writerow([r.get(c, "") if r.get(c) is not None else "" for c in cols])
print("rows:", len(rows), "->", out)
# résumé de contrôle
from collections import Counter
print(Counter(r["famille_rapport"] for r in rows))
for r in rows:
    nvals = sum(1 for p in params if r.get(p) not in (None, ""))
    print(f'{r["num_echantillon"]} | {r["famille_rapport"]:28s} | {str(r.get("nom_echantillon"))[:38]:38s} | {r.get("code_postal")} {str(r.get("commune"))[:22]:22s} | n={nvals:2d} | MS={r.get("MS")} MAT={r.get("MAT")} UFL={r.get("UFL")} PDI={r.get("PDI")} BPR={r.get("BPR")} Cu={r.get("Cu")}')
