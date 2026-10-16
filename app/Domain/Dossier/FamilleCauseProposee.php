<?php

namespace App\Domain\Dossier;

/**
 * Famille de cause proposée à l'étape « croiser » (EF-M2-05) : somme des forces sur les troubles présents, et ces
 * troubles avec la force de leur lien.
 */
final readonly class FamilleCauseProposee
{
    /**
     * @param  array<string, int>  $troubles  code du trouble => force
     */
    public function __construct(
        public string $familleCause,
        public int $force,
        public array $troubles,
    ) {}
}
