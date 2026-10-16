<?php

namespace App\Domain\Dossier;

use InvalidArgumentException;

/**
 * Condition d'une règle de lecture combinée (ref.grille_lecture_mesure) : sur la lecture d'une mesure
 * (« UREE : bas ») ou sur sa valeur (« GLYCEMIE < 45 »).
 */
final readonly class ConditionLecture
{
    private const OPERATEURS = ['<', '<=', '>', '>='];

    public function __construct(
        public string $mesureCode,
        public ?LectureMesure $lecture = null,
        public ?string $operateur = null,
        public ?float $valeur = null,
    ) {
        if (($lecture === null) === ($operateur === null) || ($operateur !== null && (! in_array($operateur, self::OPERATEURS, true) || $valeur === null))) {
            throw new InvalidArgumentException("Condition de lecture invalide sur {$mesureCode}.");
        }
    }

    /**
     * @param  array<string, float>  $valeurs  valeur (médiane) par mesure
     * @param  array<string, LectureMesure>  $lectures  lecture par mesure
     */
    public function estSatisfaite(array $valeurs, array $lectures): bool
    {
        if ($this->lecture !== null) {
            return ($lectures[$this->mesureCode] ?? null) === $this->lecture;
        }

        if (! array_key_exists($this->mesureCode, $valeurs)) {
            return false;
        }

        $v = $valeurs[$this->mesureCode];

        return match ($this->operateur) {
            '<' => $v < $this->valeur,
            '<=' => $v <= $this->valeur,
            '>' => $v > $this->valeur,
            default => $v >= $this->valeur,
        };
    }

    /** Forme canonique, pour comparer les règles entre elles. */
    public function cle(): string
    {
        return $this->lecture !== null
            ? "{$this->mesureCode}:{$this->lecture->value}"
            : "{$this->mesureCode}{$this->operateur}{$this->valeur}";
    }
}
