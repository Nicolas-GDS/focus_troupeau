<?php

namespace App\View\Components;

use App\Domain\Interpretation\PointHistorique;
use App\Domain\Interpretation\Variation;
use App\Services\Dossier\Affichage;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Courbe pluriannuelle d'un paramètre (RC-4, EF-M1-08, EF-M3-05) : valeur par campagne (médiane des lots du même
 * type), variation entre les deux dernières campagnes en valeur et en pourcentage avec un libellé neutre, sans couleur
 * d'état (une hausse n'est ni bonne ni mauvaise). SVG rendu côté serveur, légende textuelle équivalente (ENF-ACC-01).
 */
class Historique extends Component
{
    public int $largeur = 600;

    public int $hauteur = 150;

    private const GAUCHE = 60;

    private const HAUT = 28;

    private const BAS = 112;

    /**
     * @param  list<PointHistorique>  $points  série triée par campagne
     */
    public function __construct(
        public string $parametre,
        public string $unite,
        public int $decimales,
        public array $points,
        public ?Variation $variation = null,
    ) {}

    public function x(int $index): float
    {
        $n = count($this->points);

        return $n <= 1 ? $this->largeur / 2 : round(self::GAUCHE + $index * ($this->largeur - 2 * self::GAUCHE) / ($n - 1), 1);
    }

    public function y(float $valeur): float
    {
        $valeurs = array_map(fn (PointHistorique $p) => $p->valeur, $this->points);
        [$min, $max] = [min($valeurs), max($valeurs)];

        return $max == $min ? (self::HAUT + self::BAS) / 2 : round(self::BAS - ($valeur - $min) / ($max - $min) * (self::BAS - self::HAUT), 1);
    }

    public function trace(): string
    {
        return implode(' ', array_map(fn (int $i) => $this->x($i).','.$this->y($this->points[$i]->valeur), array_keys($this->points)));
    }

    public function nombre(float $valeur): string
    {
        return Affichage::nombre($valeur, $this->decimales);
    }

    /** « + 12 g/kg MS (+ 21,8 %) » : signe explicite, aucune appréciation. */
    public function libelleVariation(): ?string
    {
        if ($this->variation === null) {
            return null;
        }

        $signe = fn (float $v) => ($v > 0 ? '+ ' : ($v < 0 ? '− ' : '')).Affichage::nombre(abs($v), $this->decimales);
        $unite = $this->unite === '-' ? '' : ' '.$this->unite;
        $pourcentage = $this->variation->pourcentage === null ? '' : ' ('.($this->variation->pourcentage > 0 ? '+ ' : ($this->variation->pourcentage < 0 ? '− ' : ''))
            .Affichage::nombre(abs($this->variation->pourcentage), 1).' %)';

        return $signe($this->variation->ecart).$unite.$pourcentage;
    }

    public function legende(): string
    {
        $serie = implode(' ; ', array_map(fn (PointHistorique $p) => $p->campagne.' : '.$this->nombre($p->valeur).($p->n > 1 ? ' (médiane de '.$p->n.' lots)' : ''), $this->points));
        $variation = $this->libelleVariation();

        return $serie.'.'.($variation === null ? ' Une seule campagne : pas de variation.'
            : ' Entre '.$this->variation?->campagnePrecedente.' et '.$this->variation?->campagneCourante.' : '.$variation.'.');
    }

    public function render(): View
    {
        return view('components.historique');
    }
}
