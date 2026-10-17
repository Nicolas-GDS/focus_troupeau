<?php

namespace App\View\Components;

use App\Services\Dossier\Affichage;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

/**
 * Graphique de la projection (EF-M6-02, EF-M6-04) : stock total projeté et sa bande d'incertitude (RC-14), stock par
 * famille, besoin cumulé et niveau de la sécurité, en fin de mois. Les séries se distinguent par le trait (plein,
 * tirets, pointillés) et non par une couleur d'état ; SVG rendu côté serveur, pour l'écran et le PDF, avec une légende
 * textuelle équivalente (ENF-ACC-01).
 */
class Projection extends Component
{
    public int $largeur = 640;

    public int $hauteur = 240;

    public int $gauche = 48;

    public int $droite = 16;

    public int $haut = 16;

    public int $bas = 200;

    /** Traits des familles (pointillés différents), dans l'ordre d'affichage. */
    public const TRAITS = ['6 3', '2 3', '8 3 2 3', '1 2', '10 4'];

    /**
     * @param  list<array{libelle: string, stock: float, basse: float, haute: float, besoin: float, familles: array<string, float>}>  $points
     * @param  array<string, string>  $familles
     */
    public function __construct(
        public array $points,
        public array $familles,
        public float $securite,
    ) {}

    public function maximum(): float
    {
        $valeurs = [1.0, $this->securite];
        foreach ($this->points as $p) {
            array_push($valeurs, $p['haute'], $p['stock'], $p['besoin'], ...array_values($p['familles']));
        }

        return max($valeurs) * 1.05;
    }

    public function x(int $i): float
    {
        $n = count($this->points);

        return $n <= 1 ? $this->gauche : round($this->gauche + $i * ($this->largeur - $this->gauche - $this->droite) / ($n - 1), 1);
    }

    public function y(float $v): float
    {
        return round($this->bas - max(0.0, $v) / $this->maximum() * ($this->bas - $this->haut), 1);
    }

    /** @param  callable(array{libelle: string, stock: float, basse: float, haute: float, besoin: float, familles: array<string, float>}): float  $valeur */
    public function trace(callable $valeur): string
    {
        return implode(' ', array_map(fn (int $i) => $this->x($i).','.$this->y($valeur($this->points[$i])), array_keys($this->points)));
    }

    public function bande(): string
    {
        $haut = array_map(fn (int $i) => $this->x($i).','.$this->y($this->points[$i]['haute']), array_keys($this->points));
        $bas = array_map(fn (int $i) => $this->x($i).','.$this->y($this->points[$i]['basse']), array_reverse(array_keys($this->points)));

        return implode(' ', [...$haut, ...$bas]);
    }

    /** @return list<float> graduations de l'axe des t MS */
    public function graduations(): array
    {
        $max = $this->maximum();
        $pas = max(1, (int) (10 ** floor(log10($max / 4))));
        foreach ([1, 2, 5, 10] as $m) {
            if ($max / ($pas * $m) <= 5) {
                $pas *= $m;
                break;
            }
        }

        return array_map(fn (int $k) => (float) ($k * $pas), range(0, (int) floor($max / $pas)));
    }

    public function trait(int $index): string
    {
        return self::TRAITS[$index % count(self::TRAITS)];
    }

    public function nombre(float $v): string
    {
        return Affichage::nombre($v, 1);
    }

    public function legende(): string
    {
        $dernier = $this->points[array_key_last($this->points)] ?? null;
        if ($dernier === null) {
            return 'Aucune donnée.';
        }

        $familles = implode(' ; ', array_map(fn (string $code, string $libelle) => $libelle.' '.$this->nombre($dernier['familles'][$code] ?? 0.0).' t MS',
            array_keys($this->familles), $this->familles));

        return 'Stock projeté de '.$this->nombre($this->points[0]['stock']).' t MS au '.$this->points[0]['libelle'].' à '.$this->nombre($dernier['stock'])
            .' t MS au '.$dernier['libelle'].' (fourchette '.$this->nombre($dernier['basse']).' à '.$this->nombre($dernier['haute']).') ; besoin cumulé '
            .$this->nombre($dernier['besoin']).' t MS ; sécurité '.$this->nombre($this->securite).' t MS. Fin de période par famille : '.$familles.'.';
    }

    public function render(): View
    {
        return view('components.projection');
    }
}
