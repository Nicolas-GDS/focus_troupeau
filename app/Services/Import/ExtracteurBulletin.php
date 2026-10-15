<?php

namespace App\Services\Import;

/**
 * Extraction structurée d'un bulletin de laboratoire (EF-M9-01). Un export structuré du laboratoire, lorsqu'il sera
 * conventionné, remplacera l'extraction PDF par une autre implémentation, sans changer la suite du circuit.
 */
interface ExtracteurBulletin
{
    /**
     * @return array{entete: array<string, mixed>, famille_rapport: string, resultats: array<string, mixed>}
     *
     * @throws ExtractionImpossible
     */
    public function extraire(string $cheminPdf): array;
}
