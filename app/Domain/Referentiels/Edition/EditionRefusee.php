<?php

namespace App\Domain\Referentiels\Edition;

use DomainException;

/**
 * Enregistrement d'un référentiel refusé (E24) : erreurs par champ, en français.
 */
final class EditionRefusee extends DomainException
{
    /**
     * @param  array<string, string>  $erreurs
     */
    public function __construct(public readonly array $erreurs)
    {
        parent::__construct(implode(' ', $erreurs));
    }
}
