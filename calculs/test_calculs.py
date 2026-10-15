"""
Tests de recette des règles de calcul (chapitre 9, cas T-10 à T-29).
Exécution : python3 test_calculs.py   (depuis le dossier calculs/, aucune dépendance)
Les valeurs attendues sont calculées à la main à partir des formules du chapitre 4 ; le code PHP
doit les reproduire à 1e-6 près (valeurs continues) ou exactement (dates, entiers, classes).
"""
from datetime import date
import math
import sys

import os
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import focus_troupeau_calculs as ft

OK = 0


def check(nom, obtenu, attendu, tol=1e-6):
    global OK
    if isinstance(attendu, float):
        ok = math.isclose(obtenu, attendu, rel_tol=0, abs_tol=tol)
    else:
        ok = obtenu == attendu
    print(("OK   " if ok else "ÉCHEC") + f" {nom}: obtenu={obtenu!r} attendu={attendu!r}")
    if not ok:
        sys.exit(1)
    OK += 1


# --- T-10 RC-5 volume et densité ---------------------------------------------------------
V = ft.volume_silo(30, 6, 8, 2.0)
check("T-10a volume silo couloir (30 + 0,5×6) × 8 × 2", V, 528.0)
D = ft.densite_silo("mais_couloir", 2.0, 33, 30)
# (929 − 17,5×33 + 7,9×30 + 44×2) × 0,33 = 676,5 × 0,33
check("T-10b densité maïs couloir", D, 676.5 * 0.33)
check("T-10c densité sorgho taupinière = maïs taupinière × 1,2",
      ft.densite_silo("sorgho_taupe", 2.0, 30, 28), 1.2 * (874 - 14.5 * 30 + 4.2 * 28 + 48 * 2) * 0.30)
check("T-10d herbe brins courts H 2,5 MS 30 : 30×2,5 + 2×30 + 60", ft.densite_silo("herbe_courts", 2.5, 30), 195.0)
try:
    ft.densite_silo("herbe_longs", 2.0, 40)
    check("T-10e herbe > 36 % MS refusée", False, True)
except ValueError:
    check("T-10e herbe > 36 % MS refusée", True, True)
check("T-10f amidon par défaut", ft.amidon_depuis_grain(None), 28.0)
check("T-10g amidon depuis grain 45 % : 1,134×45 − 22,5", ft.amidon_depuis_grain(45), 1.134 * 45 - 22.5)
check("T-10h tassement 10 j, MS 33 : 5 %", ft.correction_tassement(10, 33), 0.05)
check("T-10i tassement 20 j, MS 20 : 2 × 3 %", ft.correction_tassement(20, 20), 0.06)
check("T-10j tassement ≥ 21 j : 0", ft.correction_tassement(25, 30), 0.0)
S_silo = ft.stock_utile_silo(V, D, 0.05)
check("T-10k stock utile silo 528 × 223,245 / 1000 × 0,95", S_silo, 528 * 676.5 * 0.33 / 1000 * 0.95)

# --- T-11 RC-7 balles -------------------------------------------------------------------
S_foin = ft.stock_utile_balles(400, 300, 85, 0.03)
check("T-11a 400 balles × 300 kg × 85 % × 0,97", S_foin, 98.94)
S_enrub = ft.stock_utile_balles(150, 650, 45, 0.03)
check("T-11b enrubannage 150 × 650 × 45 % × 0,97", S_enrub, 42.55875)
S_paille = ft.stock_utile_balles(100, 280, 90, 0.03)
check("T-11c paille 100 × 280 × 90 % × 0,97", S_paille, 24.444)
check("T-11d foin en vrac 50 m³ × 90 kg/m³ × 85 % × 0,98", ft.stock_utile_balles(50, 90, 85, 0.02), 3.7485)

# --- T-12 RC-8 besoins ----------------------------------------------------------------
ugb, B = ft.besoin_ugb({"VA": 100, "GEN_1_2": 30, "TAUREAU": 2}, 160)
check("T-12a UGB 100×0,90 + 30×0,60 + 2×1,00", ugb, 110.0)
check("T-12b besoin UGB 110 × 13 × 160 / 1000", B, 228.8)
check("T-12c besoin prélevé sur stock 200 / (1 − 0,13)", ft.besoin_stock(200.0, 0.13), 200 / 0.87)

# --- T-13 RC-9 couverture ----------------------------------------------------------------
foin = ft.Composant("Foin 2025", 4.0, {"UFL": 0.67, "PDI": 60, "MAT": 78, "CA": 7.3, "P": 2.2, "CU": 5.1, "K": 19.7}, "analyse", "famille_stock", famille="FOIN")
enrub = ft.Composant("Enrubannage 2026", 7.0, {"UFL": 0.80, "PDI": 70, "MAT": 84, "CA": 9.6, "P": 2.5, "CU": 5.4, "K": 13.4}, "analyse", "famille_stock", famille="ENRUB")
correcteur = ft.Composant("Correcteur azoté", 0.88, {"UFL": 1.0, "PDI": 250, "MAT": 280, "CA": 10, "P": 8, "CU": 20, "K": 15}, "table", "aliment_reference")
mineral = ft.Composant("Minéral (100 g/j)", 0.1, {"CA": 80, "P": 120, "CU": 2000}, "etiquette", "etiquette")  # 12 P / 8 Ca ; Cu 2 000 mg/kg
ration = [foin, enrub, correcteur, mineral]
msi, app = ft.apports(ration)
check("T-13a MSI 4 + 7 + 0,88 + 0,1", msi, 11.98)
check("T-13b apport UFL 4×0,67 + 7×0,80 + 0,88×1,0 + 0", app["UFL"], 4 * 0.67 + 7 * 0.80 + 0.88)
check("T-13c apport Cu mg/j 4×5,1 + 7×5,4 + 0,88×20 + 0,1×2000", app["CU"], 4 * 5.1 + 7 * 5.4 + 0.88 * 20 + 200)
besoins = {
    "UFL": {"mode": "par_jour", "valeur": 9.5},            # exemple VA lactation [valeur d'essai]
    "PDI": {"mode": "par_jour", "valeur": 800},
    "CU":  {"mode": "par_kg_ms", "min": 10, "max": None, "maximum": None},  # 10 mg/kg MS bovins
    "CA":  {"mode": "par_kg_ms", "min": 6, "max": 6.5},
}
cv = ft.couverture(ration, besoins)
check("T-13d ratio UFL 9,16/9,5", cv["UFL"]["ratio"], (4 * 0.67 + 7 * 0.80 + 0.88) / 9.5)
check("T-13e classe UFL limite (96 %)", cv["UFL"]["classe"], "limite")
check("T-13f résiduel UFL", cv["UFL"]["residuel"], 9.5 - (4 * 0.67 + 7 * 0.80 + 0.88))
check("T-13g PDI 240+490+220 = 950 → couvert", cv["PDI"]["classe"], "couvert")
conc_cu = app["CU"] / msi
check("T-13h concentration Cu ration", cv["CU"]["concentration"], conc_cu)
check("T-13i Cu ≈ 23 mg/kg MS pour un minimum de 10, sans borne haute → exces (> 150 %)", cv["CU"]["classe"], "exces")
check("T-13i2 excès minéral : gravité 0 (information)", cv["CU"]["gravite"], 0)
ratio_ca = app["CA"] / msi / 6.5
check("T-13j Ca au-dessus de l'intervalle 6–6,5 → ratio = conc/max", cv["CA"]["ratio"], ratio_ca)
# ovins : maximum réglementaire 15 mg/kg MS
cv_ovin = ft.couverture([ft.Composant("Foin", 1.5, {"CU": 5}), ft.Composant("Seau bovin", 0.1, {"CU": 300})], {"CU": {"mode": "par_kg_ms", "min": 8, "maximum": 15}})
check("T-13k seau bovin chez des ovins : (7,5+30)/1,6 = 23,4 mg/kg MS > 15 → maximum_depasse", cv_ovin["CU"]["classe"], "maximum_depasse")
check("T-13l gravité 3", cv_ovin["CU"]["gravite"], 3)
check("T-13m Ca/P de la ration", ft.ratio_ca_p(app), app["CA"] / app["P"])

# --- T-14 RC-11 projection ------------------------------------------------------------
lots = [
    ft.LotStock("FOIN25", "FOIN", 40.0, date(2025, 6, 10)),
    ft.LotStock("FOIN26", "FOIN", S_foin, date(2026, 6, 15)),
    ft.LotStock("ENRUB26", "ENRUB", S_enrub, date(2026, 5, 15)),
    ft.LotStock("PAILLE26", "PAILLE", S_paille, date(2026, 7, 20)),
]
va_lact = ft.Phase("VA lactation", date(2026, 11, 1), date(2027, 3, 31), 100, [foin, enrub, correcteur, mineral])
gen = ft.Phase("Génisses 1-2 ans", date(2026, 11, 1), date(2027, 3, 31), 30,
               [ft.Composant("Foin", 5.0, {"UFL": 0.67}, "analyse", "famille_stock", famille="FOIN"),
                ft.Composant("Paille", 1.0, {"UFL": 0.52}, "table", "famille_stock", famille="PAILLE")])
res = ft.projeter(lots, [], [va_lact, gen], date(2026, 11, 1), date(2027, 3, 31), 0.13, 0.15)
jours = 151
b_foin = (100 * 4.0 + 30 * 5.0) / 0.87 / 1000 * jours
b_enrub = 100 * 7.0 / 0.87 / 1000 * jours
b_paille = 30 * 1.0 / 0.87 / 1000 * jours
check("T-14a jours de la période", (date(2027, 3, 31) - date(2026, 11, 1)).days + 1, jours)
check("T-14b besoin prélevé total (foin + enrubannage + paille) / 0,87", res.besoin_stock_t_ms, b_foin + b_enrub + b_paille)
check("T-14c stock utile total", res.stock_utile_t_ms, 40 + S_foin + S_enrub + S_paille)
check("T-14d sécurité 15 % du besoin", res.securite_t_ms, 0.15 * (b_foin + b_enrub + b_paille))
check("T-14e bilan = stock − besoin − sécurité", res.bilan_t_ms, res.stock_utile_t_ms - res.besoin_stock_t_ms - res.securite_t_ms)
# enrubannage : 42,55875 t pour 0,8046 t/j → épuisé le 53e jour (1 nov + 52 j = 23 déc.)
j_enrub = math.floor(S_enrub / (100 * 7.0 / 0.87 / 1000)) + 1
check("T-14f rupture enrubannage = jour d'épuisement", res.rupture_par_famille["ENRUB"], date(2026, 11, 1) + __import__("datetime").timedelta(days=j_enrub - 1))
check("T-14g rupture famille foin : aucune (138,9 t pour 95,5 t de besoin)", res.rupture_par_famille["FOIN"], None)
j_foin25 = math.ceil(40.0 / ((100 * 4.0 + 30 * 5.0) / 0.87 / 1000))
check("T-14h FOIN25 (plus ancien) consommé en premier : épuisé au 64e jour", res.epuisement_par_lot["FOIN25"], date(2026, 11, 1) + __import__("datetime").timedelta(days=j_foin25 - 1))
check("T-14h2 FOIN25 épuisé n'est pas « en rupture » (allocation par famille)", res.rupture_par_lot["FOIN25"], None)
check("T-14i FOIN26 jamais épuisé", res.epuisement_par_lot["FOIN26"], None)
check("T-14j date de rupture globale = enrubannage", res.date_rupture, res.rupture_par_famille["ENRUB"])
check("T-14k autonomie en jours = stock / besoin journalier moyen", res.autonomie_jours, int(res.stock_utile_t_ms / (res.besoin_stock_t_ms / jours)))
check("T-14l cinq mois civils dans la projection", len(res.mensuel), 5)
check("T-14m stock fin nov = stock début − conso nov", res.mensuel[0]["stock_fin"], res.mensuel[0]["stock_debut"] - res.mensuel[0]["conso"] + res.mensuel[0]["mvts"])

# achat d'enrubannage livré le 15 décembre et re-comptage du foin
mvts = [ft.Mouvement("ENRUB26", date(2026, 12, 15), "achat", 30.0), ft.Mouvement("FOIN26", date(2027, 1, 15), "recomptage", 50.0)]
res2 = ft.projeter(lots, mvts, [va_lact, gen], date(2026, 11, 1), date(2027, 3, 31), 0.13, 0.15)
j_enrub2 = math.floor((S_enrub + 30.0) / (100 * 7.0 / 0.87 / 1000)) + 1
check("T-14n rupture enrubannage repoussée par l'achat", res2.rupture_par_famille["ENRUB"], date(2026, 11, 1) + __import__("datetime").timedelta(days=j_enrub2 - 1))
check("T-14o un écart de recalage enregistré", len(res2.ecarts_recalage), 1)
check("T-14p écart = projeté − recompté", res2.ecarts_recalage[0]["ecart"], res2.ecarts_recalage[0]["projete"] - 50.0)

# --- T-15 RC-12 / RC-13 leviers ----------------------------------------------------------
ctx = {"jours": 160, "besoin_jour_t_ms": 1.4, "besoin_ration_t_ms": 200.0, "pertes_distribution": 0.13}
check("T-15a achat foin 20 t à 170 €", ft.levier("achat_foin", 20, 170, ctx), (20.0, 3400.0))
check("T-15b concentré base foin 10 t : gain 4,4 t", ft.levier("concentre_base_foin", 10, 290, ctx), (4.4, 2900.0))
check("T-15c décapitalisation 5 UGB × 13 × 160 / 1000", ft.levier("decapitalisation", 5, 900, ctx), (5 * 13 * 160 / 1000, 4500.0))
check("T-15d pâturage allongé 10 j × 1,4 t/j", ft.levier("paturage_allonge", 10, 0, ctx), (14.0, 0.0))
g, c = ft.levier("ratelier", 0, 0.04, ctx)
check("T-15e râtelier 13 % → 4 % : 200 × (1/0,87 − 1/0,96)", g, 200 * (1 / 0.87 - 1 / 0.96))
lignes = ft.proposition_initiale({"ENRUB": 25.0, "PAILLE": 0.0}, {"FOIN": 40.0}, {("FOIN", "ENRUB"): 1.0}, {"ENRUB": 190.0})
check("T-15f réaffectation foin → enrubannage 25 t", (lignes[0]["levier"], lignes[0]["quantite_t_ms"]), ("reaffectation", 25.0))
check("T-15g aucun achat si le déficit est couvert par réaffectation", len(lignes), 1)
lignes2 = ft.proposition_initiale({"ENRUB": 25.0}, {"FOIN": 10.0}, {("FOIN", "ENRUB"): 1.0}, {"ENRUB": 190.0})
check("T-15h réaffectation 10 t puis achat 15 t à 190 €", (lignes2[1]["quantite_t_ms"], lignes2[1]["cout"]), (15.0, 2850.0))

# --- T-16 RC-14 incertitude ---------------------------------------------------------------
e_silo = ft.incertitude_lot([ft.ERREURS["volume.couloir"], ft.ERREURS["densite.equation"], ft.incertitude_ms(2.0, 33), ft.ERREURS["pertes.relatif"] * 0.05])
check("T-16a incertitude silo √(0,05² + 0,07² + (2/33)² + 0,025²)", e_silo, math.sqrt(0.05 ** 2 + 0.07 ** 2 + (2 / 33) ** 2 + 0.025 ** 2))
e_balles = ft.incertitude_lot([ft.ERREURS["poids.estime"], ft.incertitude_ms(5.0, 85), 0.5 * 0.03])
check("T-16b incertitude balles estimées", e_balles, math.sqrt(0.15 ** 2 + (5 / 85) ** 2 + 0.015 ** 2))
lots_i = [ft.LotStock("A", "FOIN", 100.0, incertitude_rel=0.10), ft.LotStock("B", "FOIN", 50.0, incertitude_rel=0.20)]
check("T-16c total : √(10² + 10²) / 150", ft.incertitude_totale(lots_i), math.sqrt(10 ** 2 + 10 ** 2) / 150)

print(f"\n{OK} vérifications réussies.")
