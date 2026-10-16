<?php

namespace App\Domain\Dossier;

/**
 * Règle de lecture combinée des mesures terrain (ref.grille_lecture_mesure, RC-1.5) : conjonction de conditions
 * → libellé proposé, que le technicien retient ou écarte comme une alerte.
 */
final readonly class RegleLecture
{
    /**
     * @param  list<ConditionLecture>  $conditions
     */
    public function __construct(
        public string $code,
        public int $ordre,
        public array $conditions,
        public string $libellePropose,
        public Repere $source,
        public ?string $especeCode = null,
        public ?string $stade = null,
    ) {}

    /**
     * @param  array<string, float>  $valeurs
     * @param  array<string, LectureMesure>  $lectures
     */
    public function sApplique(array $valeurs, array $lectures, ?string $especeCode, ?string $stade): bool
    {
        if (($this->especeCode !== null && $this->especeCode !== $especeCode) || ($this->stade !== null && $this->stade !== $stade)) {
            return false;
        }

        foreach ($this->conditions as $condition) {
            if (! $condition->estSatisfaite($valeurs, $lectures)) {
                return false;
            }
        }

        return true;
    }

    /** @return list<string> */
    public function cles(): array
    {
        return array_map(fn (ConditionLecture $c) => $c->cle(), $this->conditions);
    }
}
