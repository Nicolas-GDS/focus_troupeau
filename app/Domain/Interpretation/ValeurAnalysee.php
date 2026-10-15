<?php

namespace App\Domain\Interpretation;

/**
 * Un résultat de bulletin : paramètre, valeur et censure éventuelle (« < » sous la limite de quantification,
 * la valeur portée étant alors la limite).
 */
final readonly class ValeurAnalysee
{
    public function __construct(
        public string $parametreCode,
        public float $valeur,
        public ?string $censure = null,
    ) {}
}
