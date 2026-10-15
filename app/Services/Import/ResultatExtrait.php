<?php

namespace App\Services\Import;

/** Un résultat de bulletin traduit vers le dictionnaire ref.parametre. */
final readonly class ResultatExtrait
{
    public function __construct(
        public string $parametreCode,
        public float $valeur,
        public string $unite,
        public ?string $censure = null,
    ) {}
}
