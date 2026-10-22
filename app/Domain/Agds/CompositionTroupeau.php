<?php

namespace App\Domain\Agds;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Composition du troupeau bovin à une date, à partir des bovins présents lus dans AGDS : effectifs par sexe et par
 * tranche d'âge, et par race. Simple dénombrement pour l'affichage, sans correspondance avec les catégories
 * animales de ref.categorie_animale (à valider, point ouvert) ; les bornes des tranches sont fournies par l'appelant.
 */
final readonly class CompositionTroupeau
{
    public const INCONNU = '?';

    /**
     * @param  list<int>  $bornesMois  bornes croissantes des tranches d'âge, en mois (ex. [12, 24])
     */
    public function __construct(private array $bornesMois)
    {
        $tries = $bornesMois;
        sort($tries);
        if ($bornesMois === [] || $tries !== $bornesMois || min($bornesMois) <= 0) {
            throw new InvalidArgumentException('Bornes de tranches d\'âge invalides.');
        }
    }

    /**
     * @param  list<BovinAgds>  $bovins
     * @return array{total: int, tranches: list<string>, effectifs: array<string, array<string, int>>, races: array<string, int>, sans_date_naissance: int}
     */
    public function calculer(array $bovins, DateTimeImmutable $date): array
    {
        $tranches = $this->libellesTranches();
        $effectifs = [];
        $races = [];
        $sansDate = 0;

        foreach ($bovins as $bovin) {
            $sexe = in_array($bovin->sexe, [BovinAgds::FEMELLE, BovinAgds::MALE], true) ? $bovin->sexe : self::INCONNU;
            $age = $bovin->ageMois($date);
            if ($age === null) {
                $sansDate++;
            } else {
                $tranche = $tranches[$this->indiceTranche($age)];
                $effectifs[$sexe][$tranche] = ($effectifs[$sexe][$tranche] ?? 0) + 1;
            }
            $race = $bovin->raceCode ?? self::INCONNU;
            $races[$race] = ($races[$race] ?? 0) + 1;
        }
        arsort($races);

        return ['total' => count($bovins), 'tranches' => $tranches, 'effectifs' => $effectifs, 'races' => $races, 'sans_date_naissance' => $sansDate];
    }

    /** @return list<string> */
    private function libellesTranches(): array
    {
        $libelles = ['moins de '.$this->bornesMois[0].' mois'];
        for ($i = 1, $n = count($this->bornesMois); $i < $n; $i++) {
            $libelles[] = $this->bornesMois[$i - 1].' à '.$this->bornesMois[$i].' mois';
        }
        $libelles[] = $this->bornesMois[count($this->bornesMois) - 1].' mois et plus';

        return $libelles;
    }

    private function indiceTranche(int $ageMois): int
    {
        foreach ($this->bornesMois as $i => $borne) {
            if ($ageMois < $borne) {
                return $i;
            }
        }

        return count($this->bornesMois);
    }
}
