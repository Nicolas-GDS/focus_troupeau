<?php

namespace App\View\Components;

use App\Domain\Interpretation\PointHistorique;
use App\Domain\Interpretation\Position;
use App\Domain\Interpretation\ReferenceQuartiles;
use App\Services\Dossier\Affichage;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Graphique de positionnement (§ 8.3 x-positionnement, EF-M3-03, document 04 figure 4) : points anonymes de la
 * population, bande Q1-Q3, médiane, lots de l'élevage mis en évidence, campagnes précédentes de l'élevage en rappel.
 * SVG rendu côté serveur, identique à l'écran, dans la fiche figée et dans le PDF ; la légende textuelle donne les
 * mêmes informations (alternative accessible, ENF-ACC-01). Ce composant ne fait que la mise à l'échelle.
 */
class Positionnement extends Component
{
    public const LARGEUR = 600;

    public const MARGE = 24;

    /** Distance minimale entre deux étiquettes (unités du viewBox). */
    private const ECART_ETIQUETTES = 30;

    /** Les étiquettes du minimum et du maximum sont ancrées vers l'intérieur : zone de recouvrement plus large. */
    private const ECART_BORNES = 45;

    public int $largeur = self::LARGEUR;

    public int $marge = self::MARGE;

    public float $minimum;

    public float $maximum;

    /**
     * @param  list<float>  $valeurs  valeurs de la population
     * @param  list<array{libelle: string, valeur: float, position: Position}>  $lots  lots de l'élevage
     * @param  list<PointHistorique>  $rappels  campagnes précédentes de l'élevage
     */
    public function __construct(
        public string $parametre,
        public string $unite,
        public int $decimales,
        public ReferenceQuartiles $reference,
        public string $population,
        public array $valeurs,
        public array $lots,
        public array $rappels = [],
    ) {
        $tout = [...$valeurs, $reference->minimum, $reference->maximum, ...array_column($lots, 'valeur'), ...array_map(fn (PointHistorique $p) => $p->valeur, $rappels)];
        $this->minimum = min($tout);
        $this->maximum = max($tout);
    }

    public function x(float $valeur): float
    {
        $etendue = $this->maximum - $this->minimum;

        return round(self::MARGE + ($etendue == 0.0 ? 0.5 : ($valeur - $this->minimum) / $etendue) * (self::LARGEUR - 2 * self::MARGE), 1);
    }

    /**
     * Étiquettes des lots sous l'axe : une seconde ligne quand deux étiquettes se touchent.
     *
     * @return list<array{x: float, y: int, texte: string}>
     */
    public function etiquettesLots(): array
    {
        $etiquettes = [];
        $lots = $this->lots;
        usort($lots, fn (array $a, array $b) => $a['valeur'] <=> $b['valeur']);

        foreach ($lots as $lot) {
            $x = $this->x($lot['valeur']);
            $precedente = $etiquettes === [] ? null : $etiquettes[array_key_last($etiquettes)];
            $y = $precedente !== null && $x - $precedente['x'] < self::ECART_ETIQUETTES && $precedente['y'] === 76 ? 90 : 76;
            $etiquettes[] = ['x' => $x, 'y' => $y, 'texte' => $this->nombre($lot['valeur'])];
        }

        return $etiquettes;
    }

    /** Le minimum (ou le maximum) n'est écrit que si aucune étiquette de lot ne le recouvre. */
    public function borneLisible(float $borne): bool
    {
        $x = $this->x($borne);

        return array_filter($this->etiquettesLots(), fn (array $e) => $e['y'] === 76 && abs($e['x'] - $x) < self::ECART_BORNES) === [];
    }

    /**
     * Étiquettes Q1 et Q3 : centrées, ou écartées de part et d'autre de la bande quand elle est étroite.
     *
     * @return array{q1: array{x: float, ancre: string}, q3: array{x: float, ancre: string}}
     */
    public function etiquettesQuartiles(): array
    {
        $q1 = $this->x($this->reference->q1);
        $q3 = $this->x($this->reference->q3);

        return $q3 - $q1 < 2 * self::ECART_ETIQUETTES
            ? ['q1' => ['x' => $q1 - 2, 'ancre' => 'end'], 'q3' => ['x' => $q3 + 2, 'ancre' => 'start']]
            : ['q1' => ['x' => $q1, 'ancre' => 'middle'], 'q3' => ['x' => $q3, 'ancre' => 'middle']];
    }

    public function nombre(float $valeur): string
    {
        return Affichage::nombre($valeur, $this->decimales);
    }

    public function uniteAffichee(): string
    {
        return $this->unite === '-' ? '' : ' '.$this->unite;
    }

    /** Légende textuelle, alternative au graphique (ENF-ACC-01). */
    public function legende(): string
    {
        $lots = implode(' ; ', array_map(fn (array $l) => $l['libelle'].' : '.$this->nombre($l['valeur']).', '.mb_strtolower($l['position']->libelle()), $this->lots));
        $texte = $lots.'. Population : '.$this->population.' ; Q1 '.$this->nombre($this->reference->q1).', médiane '.$this->nombre($this->reference->mediane)
            .', Q3 '.$this->nombre($this->reference->q3).' (de '.$this->nombre($this->reference->minimum).' à '.$this->nombre($this->reference->maximum).').';

        if ($this->rappels !== []) {
            $texte .= ' Campagnes précédentes (cercles en pointillé) : '
                .implode(', ', array_map(fn (PointHistorique $p) => $p->campagne.' '.$this->nombre($p->valeur), $this->rappels)).'.';
        }

        return $texte;
    }

    public function render(): View
    {
        return view('components.positionnement');
    }
}
