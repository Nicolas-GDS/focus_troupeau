<?php

namespace App\Services\Autonomie;

use App\Domain\Autonomie\ConsommationPhase;
use App\Domain\Referentiels\Referentiels;
use App\Domain\Referentiels\StatutEpistemique;
use App\Models\Plan\Scenario;
use App\Models\Stock\Inventaire;
use App\Models\Stock\PeriodePhase;
use App\Services\Notifications\TextesTypes;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Données de l'écran E14 et du PDF du bilan (EF-M6-01 à EF-M6-04, EF-M6-06) : trois indicateurs avec leur fourchette
 * d'incertitude et le repère d'action, projection mensuelle par famille, ruptures par famille et par lot, entame de la
 * sécurité, série du graphique (stock projeté, bande d'incertitude, besoin cumulé), phases de la période (RC-10) et
 * hypothèses employées avec leur source. Le calcul est refait à l'affichage (EF-M6-05 : immédiat) ; plan.bilan sert à
 * l'accueil et aux alertes.
 */
class EcranBilan
{
    public function __construct(
        private readonly BilanService $bilans,
        private readonly Referentiels $referentiels,
        private readonly TextesTypes $textes,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function donnees(Inventaire $inventaire, ?Scenario $scenario = null): array
    {
        $calcul = $this->bilans->calculer($inventaire, $scenario);
        $c = $calcul['centrale'];
        $basse = $calcul['basse'];
        $haute = $calcul['haute'];
        $gds = $this->gds($inventaire->elevage_id);
        $libellesFamille = DB::table('ref.famille_fourrage')->pluck('libelle', 'code')->all();
        $familles = [];
        foreach ($calcul['lots'] as $lot) {
            $familles[$lot->famille] = (string) ($libellesFamille[$lot->famille] ?? $lot->famille);
        }

        $repere = $this->referentiels->parametre('autonomie.repere_couverture', $gds);
        $date = fn (?DateTimeImmutable $d) => $d === null ? null : Carbon::instance($d);

        return [
            'calcul' => $calcul,
            'familles' => $familles,
            'indicateurs' => [
                'couverture' => ['valeur' => $c->couverture, 'basse' => $basse->couverture, 'haute' => $haute->couverture],
                'autonomie' => ['jours' => $c->autonomieJours, 'basse' => $basse->autonomieJours, 'haute' => $haute->autonomieJours],
                'bilan' => ['valeur' => $c->bilan, 'basse' => $basse->bilan, 'haute' => $haute->bilan],
                'stock' => ['valeur' => $c->stockUtile, 'basse' => $c->stockUtile * (1 - $calcul['incertitude']), 'haute' => $c->stockUtile * (1 + $calcul['incertitude'])],
            ],
            'rupture' => ['date' => $date($c->dateRupture), 'min' => $date($basse->dateRupture), 'max' => $date($haute->dateRupture)],
            'entame_securite' => $date($c->dateEntameSecurite),
            'repere' => $repere === null ? null : [
                'valeur' => $repere,
                'texte' => $this->textes->texte($c->couverture >= $repere ? 'autonomie.repere_action.au_dessus' : 'autonomie.repere_action.en_dessous'),
                ...$this->sourceParametre('autonomie.repere_couverture', $gds),
            ],
            'mensuel' => array_map(fn (array $m) => [...$m, 'libelle' => Carbon::createFromFormat('!Y-m', $m['mois'])->translatedFormat('F Y')], $c->mensuel),
            'ruptures_famille' => collect($familles)->mapWithKeys(fn (string $libelle, string $code) => [$code => [
                'libelle' => $libelle, 'rupture' => $date($c->ruptureParFamille[$code] ?? null),
                'besoin' => $c->besoinParFamille[$code] ?? 0.0, 'stock' => $calcul['stocks_famille'][$code] ?? 0.0,
                'securite' => $calcul['securites_famille'][$code] ?? 0.0,
            ]])->all(),
            'lots' => collect($calcul['libelles'])->map(fn (string $libelle, string $id) => [
                'libelle' => $libelle, 'epuisement' => $date($c->epuisementParLot[$id] ?? null), 'rupture' => $date($c->ruptureParLot[$id] ?? null),
            ])->values()->all(),
            'ecarts' => array_map(fn (array $e) => [...$e, 'libelle' => $calcul['libelles'][$e['lot']] ?? $e['lot'], 'date' => Carbon::instance($e['date'])], $c->ecartsRecalage),
            'graphique' => $this->graphique($calcul, $familles),
            'periodes' => PeriodePhase::query()->withoutGlobalScopes()->where('inventaire_id', $inventaire->id)->orderBy('date_debut')->get(),
            'reperes_periode' => $this->textes->texte('autonomie.periode.reperes'),
            'hypotheses' => $this->hypotheses($inventaire, $calcul, $gds),
        ];
    }

    /**
     * Série du graphique : un point au début de la période puis un par fin de mois (stock total projeté, bornes,
     * besoin cumulé, stock par famille), et la sécurité totale.
     *
     * @param  array<string, mixed>  $calcul
     * @param  array<string, string>  $familles
     * @return array{points: list<array{libelle: string, stock: float, basse: float, haute: float, besoin: float, familles: array<string, float>}>, securite: float, familles: array<string, string>}
     */
    private function graphique(array $calcul, array $familles): array
    {
        $c = $calcul['centrale'];
        $besoinMois = $this->besoinMensuel($calcul['phases'], $calcul['debut'], $calcul['fin']);
        $premier = $c->mensuel[0] ?? null;
        $points = [];
        if ($premier !== null) {
            $points[] = [
                'libelle' => $calcul['debut']->format('d/m'), 'stock' => $premier['stock_debut'], 'basse' => $calcul['basse']->mensuel[0]['stock_debut'],
                'haute' => $calcul['haute']->mensuel[0]['stock_debut'], 'besoin' => 0.0,
                'familles' => array_map(fn (array $f) => $f['stock_debut'], array_intersect_key($premier['familles'], $familles)),
            ];
        }

        $cumul = 0.0;
        foreach ($c->mensuel as $i => $m) {
            $cumul += $besoinMois[$m['mois']] ?? 0.0;
            $points[] = [
                'libelle' => Carbon::createFromFormat('!Y-m', $m['mois'])->endOfMonth()->min(Carbon::instance($calcul['fin']))->format('d/m'),
                'stock' => $m['stock_fin'], 'basse' => $calcul['basse']->mensuel[$i]['stock_fin'], 'haute' => $calcul['haute']->mensuel[$i]['stock_fin'],
                'besoin' => $cumul, 'familles' => array_map(fn (array $f) => $f['stock_fin'], array_intersect_key($m['familles'], $familles)),
            ];
        }

        return ['points' => $points, 'securite' => $c->securite, 'familles' => $familles];
    }

    /**
     * Besoin prélevé sur les stocks par mois (pertes à la distribution comprises), quel que soit le stock disponible.
     *
     * @param  list<ConsommationPhase>  $phases
     * @return array<string, float>
     */
    public function besoinMensuel(array $phases, DateTimeImmutable $debut, DateTimeImmutable $fin): array
    {
        $besoin = [];
        $un = new DateInterval('P1D');
        for ($jour = $debut; $jour <= $fin; $jour = $jour->add($un)) {
            $mois = $jour->format('Y-m');
            $besoin[$mois] ??= 0.0;
            foreach ($phases as $p) {
                if ($p->active($jour)) {
                    $besoin[$mois] += $p->effectif * array_sum(array_column($p->composants, 'kg_ms')) / 1000 / (1 - $p->pertesDistribution);
                }
            }
        }

        return $besoin;
    }

    /**
     * Hypothèses et valeurs par défaut employées, avec leur source (EF-M6-06, EF-GEN-02).
     *
     * @param  array<string, mixed>  $calcul
     * @return list<array{libelle: string, valeur: string, source: string|null, statut: string|null}>
     */
    private function hypotheses(Inventaire $inventaire, array $calcul, ?int $gds): array
    {
        $cle = $inventaire->annee_seche ? 'securite.marge_annee_seche' : 'securite.marge';
        $pct = fn (float $v) => number_format(100 * $v, 0, ',', ' ').' %';

        $propre = DB::table('app.parametre_elevage')->where('elevage_id', $inventaire->elevage_id)->where('cle', $cle)->exists();
        $source = $this->sourceParametre($cle, $gds);
        $reference = $this->referentiels->parametre($cle, $gds);
        if ($propre && $reference !== null) {
            $source['source'] = 'Valeur de l\'élevage (E16) ; référence '.$pct($reference).' — '.$source['source'];
        }

        return [
            ['libelle' => 'Marge de sécurité'.($inventaire->annee_seche ? ' (année sèche)' : ''), 'valeur' => $pct((float) $inventaire->marge_securite), ...$source],
            ['libelle' => 'Pertes à la distribution (moyenne pondérée des rations)', 'valeur' => $pct($calcul['pertes_moyennes']), 'source' => 'Rations saisies (RC-8.3)', 'statut' => null],
            ['libelle' => 'Incertitude relative du stock total', 'valeur' => '± '.$pct($calcul['incertitude']), 'source' => 'RC-14 ; erreurs relatives de app.parametre_defaut', 'statut' => 'Hypothèse locale [CP] pour les cas absents du tableau de RC-14'],
            ['libelle' => 'Pas de calcul', 'valeur' => 'journalier, affiché par mois', 'source' => 'RC-11', 'statut' => null],
        ];
    }

    /**
     * @return array{source: string|null, statut: string|null}
     */
    private function sourceParametre(string $cle, ?int $gds): array
    {
        $ligne = DB::table('app.parametre_defaut')->where('cle', $cle)
            ->where(fn ($q) => $q->whereNull('gds_id')->when($gds !== null, fn ($q) => $q->orWhere('gds_id', $gds)))
            ->whereNull('valide_au')->orderByRaw('gds_id IS NULL')->first(['source', 'statut_epistemique']);
        $statut = $ligne === null ? null : StatutEpistemique::tryFrom((string) $ligne->statut_epistemique);

        return ['source' => $ligne?->source, 'statut' => $statut === null ? null : $statut->libelle().' ['.$statut->codeSpecifications().']'];
    }

    /** @return Collection<int, Inventaire> inventaires de l'élevage, le plus récent d'abord */
    public function inventaires(string $elevageId): Collection
    {
        return Inventaire::query()->where('elevage_id', $elevageId)->orderByDesc('date_inventaire')->get();
    }

    private function gds(string $elevageId): ?int
    {
        $gds = DB::table('fourrage.elevage')->where('id', $elevageId)->value('gds_id');

        return $gds === null ? null : (int) $gds;
    }
}
