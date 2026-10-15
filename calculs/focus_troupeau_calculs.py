"""
Focus Troupeau — implémentation de référence des règles de calcul (chapitre 4 des spécifications).

Ce module est volontairement sans dépendance (Python 3.10+). Il sert de référence pour le code PHP
(app/Domain) : les tests de recette (test_calculs.py) fixent les valeurs attendues. Les constantes
par défaut sont regroupées dans DEFAUTS et correspondent aux clés de app.parametre_defaut.

Statuts épistémiques des valeurs : voir chapitre 4. Aucune valeur de ce fichier n'est une norme ;
toutes sont des valeurs par défaut modifiables dans l'application.
"""
from __future__ import annotations

from dataclasses import dataclass, field
from datetime import date, timedelta
from math import sqrt
from typing import Optional

# ---------------------------------------------------------------------------
# Valeurs par défaut (clés de app.parametre_defaut)
# ---------------------------------------------------------------------------
DEFAUTS = {
    # RC-6 pertes
    "pertes.conservation.silo_bien_conduit": 0.05,
    "pertes.conservation.silo_chauffe": 0.12,
    "pertes.stockage.batiment_ferme": 0.02,
    "pertes.stockage.sous_toit": 0.03,
    "pertes.stockage.bache_sureleve": 0.04,
    "pertes.stockage.bache_sol": 0.08,
    "pertes.stockage.expose_sureleve": 0.10,
    "pertes.stockage.expose_sol": 0.15,
    "pertes.distribution.auge_cone": 0.04,
    "pertes.distribution.ratelier_anneau": 0.061,
    "pertes.distribution.remorque": 0.114,
    "pertes.distribution.deroule_sol": 0.13,
    "pertes.distribution.anneau_sans_panneau": 0.20,
    # RC-7 MS par défaut
    "ms.defaut.foin": 85.0,
    "ms.defaut.paille": 90.0,
    "densite.foin_vrac_kg_m3": 90.0,
    # RC-8 besoins
    "ugb.kg_ms_par_ugb_jour": 13.0,
    "periode.jours_defaut": 130,
    "periode.jours_plaine": 160,
    # RC-12 sécurité
    "securite.marge": 0.15,
    "securite.marge_annee_seche": 0.25,
    # RC-13 substitution et prix indicatifs
    "substitution.foin": 0.44,
    "substitution.ensilage_herbe": 0.53,
    "substitution.ensilage_mais": 0.70,
    "prix.foin_t_ms": 170.0,
    "prix.enrubannage_t_ms": 190.0,
    "prix.paille_t_ms": 75.0,
    "prix.concentre_t_ms": 290.0,
    "decapitalisation.perte_eur_ugb": 900.0,
    "genisse.t_ms_par_tete": 6.0,
    # RC-9 couverture
    "couverture.deficit": 0.80,
    "couverture.limite": 1.00,
    "couverture.exces": 1.50,
    # RC-3
    "references.n_min": 5,
    # RC-8.3 écart voies
    "coherence.ecart_voies_max": 0.20,
}

COEFF_UGB = {  # Idele, classeur bilan fourrager 2020 [T]
    "VL": 1.02, "VA": 0.90, "GEN_0_1": 0.30, "GEN_1_2": 0.60, "GEN_2_PLUS": 0.80,
    "MALE_1_2": 0.60, "TAURILLON": 0.80, "TAUREAU": 1.00, "BREBIS": 0.15, "CHEVRE": 0.20, "JUMENT_SUITEE": 1.25,
}

# ---------------------------------------------------------------------------
# RC-5 Volume et densité des silos
# ---------------------------------------------------------------------------

def volume_silo(l1: float, l2: float, largeur: float, hauteur: float) -> float:
    """V = (L1 + 0,5·L2) × l × h (Idele [T])."""
    return (l1 + 0.5 * l2) * largeur * hauteur


def correction_tassement(jours_depuis_fermeture: Optional[int], ms_pct: float) -> float:
    """Coefficient de réduction du volume si le silo est fermé depuis < 21 jours (Idele [T], interpolation [C]).
    Retourne c dans [0 ; ~0,1] ; volume corrigé = V × (1 − c)."""
    if jours_depuis_fermeture is None or jours_depuis_fermeture >= 21:
        return 0.0
    j = max(0, jours_depuis_fermeture)
    # points de référence : 0 j → 0,06 (extrapolation), 10 j → 0,05, 20 j → 0,03, 21 j → 0
    if j <= 10:
        c = 0.06 - 0.001 * j
    elif j <= 20:
        c = 0.05 - 0.002 * (j - 10)
    else:
        c = 0.03 * (21 - j)
    if ms_pct < 22:
        c *= 2.0
    return c


def amidon_depuis_grain(grain_pct: Optional[float]) -> float:
    """Amidon par défaut 28 % ; si % de grain G connu et > 20 : A = 1,134·G − 22,5 (Idele [T])."""
    if grain_pct is None or grain_pct <= 20:
        return 28.0
    return 1.134 * grain_pct - 22.5


def densite_silo(equation: str, hauteur: float, ms_pct: float, amidon_pct: float = 28.0) -> float:
    """Densité en kg MS/m³ selon l'équation Idele 2020 [T]. Lève ValueError hors domaine (herbe > 36 % MS)."""
    h, ms, a = hauteur, ms_pct, amidon_pct
    if equation in ("mais_couloir", "sorgho_couloir"):
        d = (929 - 17.5 * ms + 7.9 * a + 44 * h) * ms / 100
    elif equation in ("mais_taupe", "sorgho_taupe"):
        d = (874 - 14.5 * ms + 4.2 * a + 48 * h) * ms / 100
    elif equation == "herbe_courts":
        d = 30 * h + 2 * ms + 60
    elif equation == "herbe_longs":
        d = 27 * h + 1.8 * ms + 54
    elif equation == "herbe_couloir":
        d = 16 * h + 5 * ms + 34
    elif equation == "herbe_taupe":
        d = 7 * h + 3.7 * ms + 74
    else:
        raise ValueError(f"équation inconnue : {equation}")
    if equation.startswith("herbe") and ms > 36:
        raise ValueError("équations herbe non applicables au-delà de 36 % de MS : saisir une densité mesurée")
    if equation.startswith("sorgho"):
        d *= 1.20
    return d


def stock_utile_silo(volume_m3: float, densite_kg_ms_m3: float, pertes_conservation: float) -> float:
    """S = V × D / 1000 × (1 − p) en t MS."""
    return volume_m3 * densite_kg_ms_m3 / 1000.0 * (1.0 - pertes_conservation)


# ---------------------------------------------------------------------------
# RC-7 Balles, bottes, vrac
# ---------------------------------------------------------------------------

def stock_utile_balles(nombre: float, poids_brut_kg: float, ms_pct: float, pertes_stockage: float) -> float:
    """S = N × P × MS/100 × (1 − p) / 1000 en t MS. Pour le vrac : nombre = m³, poids = densité kg/m³."""
    return nombre * poids_brut_kg * ms_pct / 100.0 * (1.0 - pertes_stockage) / 1000.0


# ---------------------------------------------------------------------------
# RC-8 Besoins en MS
# ---------------------------------------------------------------------------

def besoin_ugb(effectifs: dict[str, float], jours: float, kg_par_ugb_jour: float = 13.0,
               coeffs: dict[str, float] = COEFF_UGB) -> tuple[float, float]:
    """Retourne (UGB total, besoin t MS) — RC-8.1."""
    ugb = sum(n * coeffs[cat] for cat, n in effectifs.items())
    return ugb, ugb * kg_par_ugb_jour * jours / 1000.0


def besoin_stock(besoin_ration_t_ms: float, pertes_distribution: float) -> float:
    """B_stock = B_ration / (1 − p_distribution) — RC-8.3."""
    return besoin_ration_t_ms / (1.0 - pertes_distribution)


# ---------------------------------------------------------------------------
# RC-9 Apports et couverture
# ---------------------------------------------------------------------------

@dataclass
class Composant:
    libelle: str
    kg_ms: float                       # par animal et par jour
    teneurs: dict[str, float]          # par kg MS (UFL, PDI g, MAT g, CA g, P g, CU mg, ZN mg…)
    origine: str = "table"             # 'analyse' | 'table' | 'etiquette'
    source: str = "aliment_reference"  # 'lot_stock' | 'famille_stock' | 'aliment_reference' | 'etiquette' | 'paturage'
    lot_stock: Optional[str] = None
    famille: Optional[str] = None


def apports(ration: list[Composant]) -> tuple[float, dict[str, float]]:
    """(MSI kg/j, apports par élément par animal et par jour)."""
    msi = sum(c.kg_ms for c in ration)
    tot: dict[str, float] = {}
    for c in ration:
        for e, t in c.teneurs.items():
            tot[e] = tot.get(e, 0.0) + c.kg_ms * t
    return msi, tot


ELEMENTS_NUTRITIONNELS = ("UFL", "UFV", "PDI", "MAT", "UEB", "UEL", "UEM", "NDF")


def classe_couverture(ratio: float, maximum_depasse: bool = False, est_mineral: bool = False,
                      d: dict = DEFAUTS) -> tuple[str, int]:
    """Classes [CP] : deficit < 80 % (2) ; limite 80–99 % (1) ; couvert 100–150 % ; exces > 150 %
    (gravité 1 pour les éléments nutritionnels, 0 pour les minéraux) ; maximum_depasse (3)."""
    if maximum_depasse:
        return "maximum_depasse", 3
    if ratio < d["couverture.deficit"]:
        return "deficit", 2
    if ratio < d["couverture.limite"]:
        return "limite", 1
    if ratio <= d["couverture.exces"]:
        return "couvert", 0
    return "exces", (0 if est_mineral else 1)


def couverture(ration: list[Composant], besoins: dict[str, dict]) -> dict[str, dict]:
    """besoins : {element: {'mode': 'par_jour'|'par_kg_ms', 'valeur': x} ou {'mode':'par_kg_ms','min':a,'max':b, 'maximum': m}}.
    Retourne par élément : apport, concentration, besoin, ratio, classe, gravite, residuel."""
    msi, app = apports(ration)
    out = {}
    for e, b in besoins.items():
        a = app.get(e, 0.0)
        conc = a / msi if msi > 0 else 0.0
        maxi = b.get("maximum")
        depasse = maxi is not None and conc > maxi
        if b["mode"] == "par_jour":
            besoin = b["valeur"]
            ratio = a / besoin if besoin > 0 else 0.0
            residuel = max(0.0, besoin - a)
        else:  # par_kg_ms : comparaison de la concentration à [min ; max]
            mini, maxv = b.get("min", b.get("valeur")), b.get("max", b.get("valeur"))
            if conc < mini:
                ratio = conc / mini
            elif maxv is not None:
                ratio = conc / maxv if conc > maxv else 1.0   # > 1 : au-dessus de l'intervalle
            else:
                ratio = conc / mini                            # pas de borne haute : excès signalé au-delà de 150 % du minimum
            residuel = max(0.0, (mini - conc) * msi)
        classe, grav = classe_couverture(ratio, depasse, est_mineral=e not in ELEMENTS_NUTRITIONNELS)
        out[e] = {"apport": a, "concentration": conc, "ratio": ratio, "classe": classe, "gravite": grav,
                  "residuel": residuel, "msi": msi}
    return out


def ratio_ca_p(app: dict[str, float]) -> Optional[float]:
    return app["CA"] / app["P"] if app.get("P") else None


# ---------------------------------------------------------------------------
# RC-10 / RC-11 Période, projection, rupture
# ---------------------------------------------------------------------------

@dataclass
class LotStock:
    id: str
    famille: str
    stock_utile_t_ms: float
    date_recolte: Optional[date] = None
    priorite: int = 0
    incertitude_rel: float = 0.0


@dataclass
class Mouvement:
    lot_id: str
    date: date
    type: str          # 'achat' | 'vente' | 'recomptage' | 'ajustement'
    quantite_t_ms: float


@dataclass
class Phase:
    lot_animaux: str
    date_debut: date
    date_fin: date
    effectif: int
    ration: list[Composant]


@dataclass
class ResultatProjection:
    besoin_stock_t_ms: float
    stock_utile_t_ms: float
    securite_t_ms: float
    bilan_t_ms: float
    couverture: float
    autonomie_jours: int
    date_rupture: Optional[date]
    date_entame_securite: Optional[date]
    rupture_par_lot: dict[str, Optional[date]]
    epuisement_par_lot: dict[str, Optional[date]]
    rupture_par_famille: dict[str, Optional[date]]
    mensuel: list[dict]
    ecarts_recalage: list[dict]


def projeter(lots: list[LotStock], mouvements: list[Mouvement], phases: list[Phase],
             debut: date, fin: date, pertes_distribution: float, marge_securite: float) -> ResultatProjection:
    """RC-11 : projection journalière, agrégée par mois ; RC-12 : sécurité."""
    stock = {l.id: l.stock_utile_t_ms for l in lots}
    fam_de = {l.id: l.famille for l in lots}
    ordre_fam: dict[str, list[str]] = {}
    for l in sorted(lots, key=lambda l: (l.priorite, l.date_recolte or date.max, l.id)):
        ordre_fam.setdefault(l.famille, []).append(l.id)
    rupture_lot: dict[str, Optional[date]] = {l.id: None for l in lots}
    epuise_lot: dict[str, Optional[date]] = {l.id: None for l in lots}
    rupture_fam: dict[str, Optional[date]] = {f: None for f in ordre_fam}
    mvts_par_jour: dict[date, list[Mouvement]] = {}
    for m in mouvements:
        mvts_par_jour.setdefault(m.date, []).append(m)
    ecarts = []
    mensuel: dict[tuple[int, int], dict] = {}
    besoin_total = 0.0
    besoin_par_fam: dict[str, float] = {}

    def conso_jour(d: date) -> dict[str, float]:
        """kg MS prélevés sur le stock par lot de stock (famille → allocation)."""
        prelev: dict[str, float] = {}
        for ph in phases:
            if not (ph.date_debut <= d <= ph.date_fin):
                continue
            for c in ph.ration:
                if c.source not in ("lot_stock", "famille_stock"):
                    continue
                q = ph.effectif * c.kg_ms / (1.0 - pertes_distribution) / 1000.0  # t MS
                if c.source == "lot_stock":
                    prelev[c.lot_stock] = prelev.get(c.lot_stock, 0.0) + q
                else:
                    prelev[("FAM", c.famille)] = prelev.get(("FAM", c.famille), 0.0) + q
        return prelev

    # premier passage : besoins totaux (pour la sécurité)
    d = debut
    while d <= fin:
        for k, q in conso_jour(d).items():
            fam = k[1] if isinstance(k, tuple) else fam_de[k]
            besoin_total += q
            besoin_par_fam[fam] = besoin_par_fam.get(fam, 0.0) + q
        d += timedelta(days=1)
    securite = marge_securite * besoin_total
    securite_fam = {f: marge_securite * b for f, b in besoin_par_fam.items()}
    entame: Optional[date] = None

    d = debut
    while d <= fin:
        cle = (d.year, d.month)
        m = mensuel.setdefault(cle, {"mois": f"{d.year}-{d.month:02d}", "familles": {}, "stock_debut": None, "conso": 0.0, "mvts": 0.0, "stock_fin": None})
        if m["stock_debut"] is None:
            m["stock_debut"] = sum(stock.values())
            for f, ids in ordre_fam.items():
                m["familles"][f] = {"stock_debut": sum(stock[i] for i in ids), "conso": 0.0, "mvts": 0.0, "stock_fin": None}
        # mouvements du jour
        for mv in mvts_par_jour.get(d, []):
            fam = fam_de[mv.lot_id]
            if mv.type == "recomptage":
                ecart = stock[mv.lot_id] - mv.quantite_t_ms
                ecarts.append({"lot": mv.lot_id, "date": d, "projete": stock[mv.lot_id], "recompte": mv.quantite_t_ms, "ecart": ecart})
                delta = mv.quantite_t_ms - stock[mv.lot_id]
            else:
                delta = mv.quantite_t_ms  # signé
            stock[mv.lot_id] += delta
            m["mvts"] += delta
            m["familles"][fam]["mvts"] += delta
        # consommations
        for k, q in conso_jour(d).items():
            if isinstance(k, tuple):
                fam = k[1]
                reste = q
                for lid in ordre_fam.get(fam, []):
                    if reste <= 0:
                        break
                    dispo = max(0.0, stock[lid])
                    pris = min(dispo, reste)
                    stock[lid] -= pris
                    reste -= pris
                if reste > 0:  # plus rien dans la famille : on impute au dernier lot (stock négatif = rupture)
                    lid = ordre_fam.get(fam, [None])[-1]
                    if lid is not None:
                        stock[lid] -= reste
                    if rupture_fam.get(fam) is None:
                        rupture_fam[fam] = d
            else:
                fam = fam_de[k]
                stock[k] -= q
            m["conso"] += q
            m["familles"][fam]["conso"] += q
        for lid, s in stock.items():
            if s < -1e-9 and rupture_lot[lid] is None:
                rupture_lot[lid] = d
            if s <= 1e-9 and epuise_lot[lid] is None:
                epuise_lot[lid] = d
        for f, ids in ordre_fam.items():
            tot_f = sum(stock[i] for i in ids)
            if tot_f < -1e-9 and rupture_fam.get(f) is None:
                rupture_fam[f] = d
            if entame is None and tot_f < securite_fam.get(f, 0.0) - 1e-9 and besoin_par_fam.get(f, 0) > 0:
                entame = d
        m["stock_fin"] = sum(stock.values())
        for f, ids in ordre_fam.items():
            m["familles"][f]["stock_fin"] = sum(stock[i] for i in ids)
        d += timedelta(days=1)

    stock_utile = sum(l.stock_utile_t_ms for l in lots)
    jours = (fin - debut).days + 1
    b_jour = besoin_total / jours if jours else 0.0
    autonomie = int(stock_utile / b_jour) if b_jour > 0 else 0
    rupt = [r for r in rupture_fam.values() if r is not None]
    return ResultatProjection(
        besoin_stock_t_ms=besoin_total, stock_utile_t_ms=stock_utile, securite_t_ms=securite,
        bilan_t_ms=stock_utile - besoin_total - securite,
        couverture=(stock_utile / besoin_total) if besoin_total > 0 else 0.0,
        autonomie_jours=autonomie, date_rupture=min(rupt) if rupt else None, date_entame_securite=entame,
        rupture_par_lot=rupture_lot, epuisement_par_lot=epuise_lot, rupture_par_famille=rupture_fam,
        mensuel=list(mensuel.values()), ecarts_recalage=ecarts,
    )


# ---------------------------------------------------------------------------
# RC-14 Incertitude
# ---------------------------------------------------------------------------
ERREURS = {  # erreurs relatives (document 05, § 2.3)
    "volume.couloir": 0.05, "volume.taupiniere": 0.10,
    "densite.equation": 0.07, "densite.tamisage": 0.07, "densite.carottage": 0.04,
    "poids.estime": 0.15, "poids.mesure": 0.05,
    "ms.analyse_points": 2.0, "ms.defaut_points": 5.0, "ms.enrubannage_non_analyse_points": 15.0,
    "pertes.relatif": 0.50,
}


def incertitude_lot(facteurs: list[float]) -> float:
    """Composition quadratique d'erreurs relatives indépendantes."""
    return sqrt(sum(e * e for e in facteurs))


def incertitude_ms(points: float, ms_pct: float) -> float:
    return points / ms_pct


def incertitude_totale(lots: list[LotStock]) -> float:
    """Erreur relative du stock total : composition quadratique des erreurs absolues."""
    tot = sum(l.stock_utile_t_ms for l in lots)
    if tot <= 0:
        return 0.0
    return sqrt(sum((l.stock_utile_t_ms * l.incertitude_rel) ** 2 for l in lots)) / tot


# ---------------------------------------------------------------------------
# RC-13 Leviers
# ---------------------------------------------------------------------------

def levier(code: str, quantite: float, parametre: float, ctx: dict, d: dict = DEFAUTS) -> tuple[float, float]:
    """Retourne (gain t MS, coût €). ctx : {'jours', 'besoin_jour_t_ms', 'besoin_ration_t_ms', 'pertes_distribution'}."""
    if code in ("achat_foin", "achat_enrubannage", "achat_paille", "achat_autre"):
        return quantite, quantite * parametre
    if code == "concentre_base_foin":
        return quantite * d["substitution.foin"], quantite * parametre
    if code == "concentre_base_ensilage_herbe":
        return quantite * d["substitution.ensilage_herbe"], quantite * parametre
    if code == "concentre_base_ensilage_mais":
        return quantite * d["substitution.ensilage_mais"], quantite * parametre
    if code == "decapitalisation":
        return quantite * d["ugb.kg_ms_par_ugb_jour"] * ctx["jours"] / 1000.0, quantite * parametre
    if code == "genisses_supprimees":
        return quantite * parametre, 0.0
    if code == "paturage_allonge":
        return quantite * ctx["besoin_jour_t_ms"], parametre
    if code == "ratelier":
        p, p2 = ctx["pertes_distribution"], parametre
        return max(0.0, ctx["besoin_ration_t_ms"] * (1 / (1 - p) - 1 / (1 - p2))), 0.0
    if code == "autre":
        return quantite, parametre
    raise ValueError(code)


def proposition_initiale(deficit_par_famille: dict[str, float], excedent_par_famille: dict[str, float],
                         substituabilite: dict[tuple[str, str], float], prix: dict[str, float]) -> list[dict]:
    """RC-13.2 étapes 1 et 2 : réaffectation puis achats. (L'étape 3, nutritionnelle, dépend des rations : voir couverture().)"""
    lignes = []
    deficit = dict(deficit_par_famille)
    exced = dict(excedent_par_famille)
    # 1. réaffectation
    for (src, cible), ratio in substituabilite.items():
        if deficit.get(cible, 0) > 0 and exced.get(src, 0) > 0:
            q = min(exced[src], deficit[cible] / ratio)
            lignes.append({"levier": "reaffectation", "de": src, "vers": cible, "quantite_t_ms": q, "gain_t_ms": q * ratio, "cout": 0.0})
            exced[src] -= q
            deficit[cible] -= q * ratio
    # 2. achats
    for fam, dq in deficit.items():
        if dq > 1e-9:
            lignes.append({"levier": "achat_fourrage", "famille": fam, "quantite_t_ms": dq, "gain_t_ms": dq, "cout": dq * prix.get(fam, 0.0)})
    return lignes
