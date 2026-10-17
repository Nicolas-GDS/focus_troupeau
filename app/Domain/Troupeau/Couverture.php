<?php

namespace App\Domain\Troupeau;

/**
 * Apports et couverture nutritionnelle par lot d'animaux (RC-9). Implémentation de référence : calculs/ (T-13).
 *
 * Apports : A_e = Σ kg MS × teneur_e ; concentration A_e / MSI. Couverture : ratio A_e / besoin (par jour) ou position
 * de la concentration dans [min ; max] (par kg MS). Classes [CP] : déficit sous `deficit`, limite sous `limite`,
 * couvert jusqu'à `exces` inclus, excès au-delà (gravité 1 pour les éléments énergétiques, azotés et d'encombrement,
 * 0 pour les minéraux), maximum dépassé (gravité 3) quand la concentration dépasse un maximum réglementaire.
 */
final class Couverture
{
    /** Éléments dont l'excès porte une gravité 1 (RC-9) ; les autres (minéraux) une gravité 0. */
    public const ELEMENTS_NUTRITIONNELS = ['UFL_2018', 'UFV_2018', 'PDI', 'MAT', 'UEB', 'UEL', 'UEM', 'NDF'];

    public function __construct(private readonly SeuilsCouverture $seuils) {}

    /**
     * @param  list<Composant>  $composants
     * @return array{msi: float, apports: array<string, float>, complet: bool}
     */
    public function apports(array $composants): array
    {
        $msi = 0.0;
        $apports = [];
        $complet = true;

        foreach ($composants as $c) {
            $msi += $c->kgMs;

            if (! $c->teneursConnues()) {
                $complet = $complet && $c->kgMs == 0.0;

                continue;
            }

            foreach ((array) $c->teneurs as $element => $teneur) {
                $apports[$element] = ($apports[$element] ?? 0.0) + $c->kgMs * $teneur;
            }
        }

        return ['msi' => $msi, 'apports' => $apports, 'complet' => $complet];
    }

    /** @return array{classe: ClasseCouverture, gravite: int} */
    public function classer(float $ratio, bool $maximumDepasse, bool $mineral): array
    {
        return match (true) {
            $maximumDepasse => ['classe' => ClasseCouverture::MaximumDepasse, 'gravite' => 3],
            $ratio < $this->seuils->deficit => ['classe' => ClasseCouverture::Deficit, 'gravite' => 2],
            $ratio < $this->seuils->limite => ['classe' => ClasseCouverture::Limite, 'gravite' => 1],
            $ratio <= $this->seuils->exces => ['classe' => ClasseCouverture::Couvert, 'gravite' => 0],
            default => ['classe' => ClasseCouverture::Exces, 'gravite' => $mineral ? 0 : 1],
        };
    }

    /**
     * @param  list<Composant>  $composants
     * @param  array<string, Besoin>  $besoins
     * @return array<string, ResultatCouverture>
     */
    public function couverture(array $composants, array $besoins): array
    {
        ['msi' => $msi, 'apports' => $apports, 'complet' => $complet] = $this->apports($composants);
        $resultats = [];

        foreach ($besoins as $element => $besoin) {
            $apport = $apports[$element] ?? 0.0;
            $concentration = $msi > 0 ? $apport / $msi : 0.0;

            if (! $complet) {
                $resultats[$element] = new ResultatCouverture($apport, $concentration, $msi, false);

                continue;
            }

            if ($besoin->mode === Besoin::PAR_JOUR) {
                $ratio = $besoin->valeur > 0 ? $apport / $besoin->valeur : 0.0;
                $residuel = max(0.0, (float) $besoin->valeur - $apport);
            } else {
                $min = (float) $besoin->min;
                $ratio = match (true) {
                    $concentration < $min => $min > 0 ? $concentration / $min : 0.0,
                    $besoin->max !== null => $concentration > $besoin->max ? $concentration / $besoin->max : 1.0,
                    default => $min > 0 ? $concentration / $min : 0.0,
                };
                $residuel = max(0.0, ($min - $concentration) * $msi);
            }

            $depasse = $besoin->maximum !== null && $concentration > $besoin->maximum;
            ['classe' => $classe, 'gravite' => $gravite] = $this->classer($ratio, $depasse, ! in_array($element, self::ELEMENTS_NUTRITIONNELS, true));
            $resultats[$element] = new ResultatCouverture($apport, $concentration, $msi, true, $ratio, $classe, $gravite, $residuel);
        }

        return $resultats;
    }

    /**
     * Rapport Ca/P de la ration, null sans phosphore.
     *
     * @param  array<string, float>  $apports
     */
    public function ratioCaP(array $apports): ?float
    {
        return empty($apports['P']) ? null : ($apports['CA'] ?? 0.0) / $apports['P'];
    }
}
