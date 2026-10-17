<?php

namespace App\Services\Stocks;

use App\Domain\Stocks\EquationDensite;
use App\Models\Fourrage\Elevage;
use App\Models\Stock\Inventaire;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Support\Facades\DB;
use JsonException;

/**
 * Reprise d'un bilan du calculateur HTML d'autonomie fourragère (EF-M9-06, T-28 ; docs/calculateur/). Le format
 * « gds-autonomie-fourragere-v1 » est la clé sous laquelle le calculateur enregistre sa saisie dans le navigateur
 * (fonction collect() : expl, date, jours, marge, distrib, voie, silos, balles, ugb, ration, lev) ; le calculateur
 * n'a pas de bouton d'export, le contenu se copie depuis le stockage local du navigateur.
 *
 * Deux temps : analyser() propose un lot par silo et par ligne de balles (famille déduite du type de silo ou proposée
 * d'après le libellé des balles, forme, pertes), avec ses alertes ; importer() crée l'inventaire et ses lots par
 * StocksService, donc avec les mêmes calculs que la saisie. Seuls les stocks sont repris (T-28) : les lignes UGB et
 * ration du calculateur n'ont pas de composition et ne sont pas reprises (signalées). Différences de calcul signalées :
 * le calculateur n'applique ni la correction de tassement ni le refus des équations herbe au-delà de 36 % de MS.
 */
class ImportCalculateurService
{
    /** Pertes de stockage du calculateur (« st », en %) → clé de app.parametre_defaut. */
    private const STOCKAGE = [
        '2' => 'pertes.stockage.batiment_ferme', '3' => 'pertes.stockage.sous_toit', '4' => 'pertes.stockage.bache_sureleve',
        '8' => 'pertes.stockage.bache_sol', '10' => 'pertes.stockage.expose_sureleve', '15' => 'pertes.stockage.expose_sol',
    ];

    /** Famille proposée d'après un mot du libellé d'une ligne de balles (à confirmer dans l'aperçu). */
    private const MOTS_FAMILLE = ['enrub' => 'ENRUB', 'paille' => 'PAILLE', 'foin' => 'FOIN', 'regain' => 'FOIN', 'luzerne' => 'FOIN'];

    public function __construct(private readonly StocksService $stocks) {}

    /**
     * @return array{inventaire: array{libelle: string, date: string, jours: int, marge: float, distrib: float|null}, lots: list<array{donnees: array<string, mixed>, alertes: list<string>}>, non_repris: list<string>}
     */
    public function analyser(string $json): array
    {
        try {
            $d = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new SaisieRefusee('Contenu illisible : coller le contenu enregistré par le calculateur (clé gds-autonomie-fourragere-v1).');
        }

        if (! is_array($d) || (! isset($d['silos']) && ! isset($d['balles']))) {
            throw new SaisieRefusee('Ce contenu ne vient pas du calculateur d\'autonomie fourragère (silos et balles absents).');
        }

        $lots = [];
        foreach ((array) ($d['silos'] ?? []) as $n => $s) {
            if (self::nombre($s['l1'] ?? null) === null) {
                continue;
            }
            $lots[] = $this->silo((array) $s, $n + 1);
        }
        foreach ((array) ($d['balles'] ?? []) as $n => $b) {
            if (self::nombre($b['n'] ?? null) === null) {
                continue;
            }
            $lots[] = $this->balles((array) $b, $n + 1);
        }

        $nonRepris = [];
        foreach (['ugb' => 'catégorie UGB', 'ration' => 'ligne de ration', 'lev' => 'levier'] as $cle => $libelle) {
            $lignes = array_filter((array) ($d[$cle] ?? []), fn ($l) => is_array($l) && array_filter($l, fn ($v) => $v !== '' && $v !== '0'));
            if ($lignes !== []) {
                $nonRepris[] = count($lignes).' '.$libelle.($cle === 'lev' ? 's (scénarios : lot L6)' : ($cle === 'ugb' ? 's (à décrire en lots d\'animaux, écran Troupeau)' : 's (rations à décrire par composant, écran Troupeau)'));
            }
        }

        return [
            'inventaire' => [
                'libelle' => trim((string) ($d['expl'] ?? '')),
                'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['date'] ?? '')) ? (string) $d['date'] : today()->toDateString(),
                'jours' => max(1, (int) (self::nombre($d['jours'] ?? null) ?? 160)),
                'marge' => (self::nombre($d['marge'] ?? null) ?? 15) / 100,
                'distrib' => self::nombre($d['distrib'] ?? null),
            ],
            'lots' => $lots,
            'non_repris' => $nonRepris,
        ];
    }

    /**
     * Crée l'inventaire (date et période du calculateur, marge de sécurité du calculateur) et ses lots.
     *
     * @param  array{inventaire: array{libelle: string, date: string, jours: int, marge: float}, lots: list<array{donnees: array<string, mixed>}>}  $apercu
     * @return array{inventaire: Inventaire, lots: int, refus: list<string>}
     */
    public function importer(Elevage $elevage, array $apercu): array
    {
        return DB::transaction(function () use ($elevage, $apercu) {
            $i = $apercu['inventaire'];
            $inventaire = $this->stocks->creerInventaire($elevage, [
                'date_inventaire' => $i['date'], 'type' => 'autre', 'date_debut' => $i['date'],
                'date_fin' => date('Y-m-d', (int) strtotime($i['date'].' +'.($i['jours'] - 1).' days')), 'annee_seche' => false,
                'commentaire' => trim('Repris du calculateur d\'autonomie fourragère. '.$i['libelle']),
            ]);
            $inventaire->update(['marge_securite' => $i['marge']]);
            $refus = [];
            $crees = 0;

            foreach ($apercu['lots'] as $lot) {
                try {
                    $this->stocks->enregistrerLot($inventaire, $lot['donnees']);
                    $crees++;
                } catch (SaisieRefusee $e) {
                    $refus[] = $lot['donnees']['libelle'].' : '.$e->getMessage();
                }
            }

            return ['inventaire' => $inventaire->refresh(), 'lots' => $crees, 'refus' => $refus];
        });
    }

    /**
     * @param  array<string, mixed>  $s
     * @return array{donnees: array<string, mixed>, alertes: list<string>}
     */
    private function silo(array $s, int $n): array
    {
        $equation = EquationDensite::tryFrom((string) ($s['type'] ?? '')) ?? EquationDensite::MaisCouloir;
        $densite = self::nombre($s['dens'] ?? null);
        $ms = self::nombre($s['ms'] ?? null);
        $alertes = [];
        $ouvrage = match ($equation) {
            EquationDensite::MaisTaupiniere, EquationDensite::SorghoTaupiniere, EquationDensite::HerbeTaupiniere => 'taupiniere',
            EquationDensite::HerbeBrinsCourts, EquationDensite::HerbeBrinsLongs => null,
            default => 'couloir',
        };
        if ($ouvrage === null) {
            $ouvrage = 'couloir';
            $alertes[] = 'Ouvrage non précisé par le calculateur (équation par type de brins) : silo couloir retenu, à corriger s\'il s\'agit d\'une taupinière.';
        }
        if ($equation->herbe() && $densite === null && $ms !== null && $ms > 36) {
            $alertes[] = 'MS au-delà de 36 % : l\'équation herbe ne s\'applique pas, une densité mesurée est nécessaire (le calculateur l\'appliquait).';
        }

        return ['donnees' => [
            'libelle' => trim((string) ($s['nom'] ?? '')) ?: 'Silo '.$n, 'forme' => 'silo',
            'famille_code' => match (true) {
                $equation->herbe() => 'ENSIL_HERBE', $equation->sorgho() => 'ENSIL_AUTRE', default => 'ENSIL_MAIS'
            },
            'ouvrage' => $ouvrage, 'l1_m' => self::nombre($s['l1'] ?? null), 'l2_m' => self::nombre($s['l2'] ?? null) ?? 0.0,
            'largeur_m' => self::nombre($s['larg'] ?? null), 'hauteur_m' => self::nombre($s['haut'] ?? null), 'ms_pct' => $ms,
            'amidon_pct' => $equation->herbe() ? null : self::nombre($s['am'] ?? null),
            'methode_densite' => $densite === null ? 'equation' : 'saisie', 'equation_code' => $densite === null ? $equation->value : null,
            'densite_kg_ms_m3' => $densite, 'mode_stockage' => 'pertes.conservation.silo_bien_conduit',
            'pertes_pct' => (self::nombre($s['pertes'] ?? null) ?? 5) / 100,
        ], 'alertes' => $alertes];
    }

    /**
     * @param  array<string, mixed>  $b
     * @return array{donnees: array<string, mixed>, alertes: list<string>}
     */
    private function balles(array $b, int $n): array
    {
        $libelle = trim((string) ($b['nom'] ?? '')) ?: 'Balles '.$n;
        $minuscule = mb_strtolower($libelle);
        $famille = null;
        foreach (self::MOTS_FAMILLE as $mot => $code) {
            if (str_contains($minuscule, $mot)) {
                $famille = $code;
                break;
            }
        }
        $alertes = $famille === null ? ['Famille de fourrage non reconnue dans le libellé : la choisir.'] : [];

        return ['donnees' => [
            'libelle' => $libelle, 'forme' => 'balle_ronde', 'famille_code' => $famille ?? 'FOIN',
            'nombre' => self::nombre($b['n'] ?? null), 'poids_brut_kg' => self::nombre($b['p'] ?? null), 'poids_mesure' => false,
            'ms_pct' => self::nombre($b['ms'] ?? null),
            'mode_stockage' => self::STOCKAGE[(string) ($b['st'] ?? '3')] ?? 'pertes.stockage.sous_toit',
            'pertes_pct' => (self::nombre($b['pertes'] ?? null) ?? 3) / 100,
        ], 'alertes' => $alertes];
    }

    private static function nombre(mixed $valeur): ?float
    {
        if ($valeur === null || $valeur === '') {
            return null;
        }
        $v = str_replace(',', '.', (string) $valeur);

        return is_numeric($v) ? (float) $v : null;
    }
}
