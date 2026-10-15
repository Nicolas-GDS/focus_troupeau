<?php

namespace App\Services\Import;

use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

/**
 * Extraction des bulletins LANO par le script existant scripts/parse_lano.py (pdftotext -layout), appelé par
 * Symfony\Process (chapitre 6.1). Délai borné par l'exigence d'import en moins de 30 s (ENF-PERF-02).
 */
class ExtracteurLano implements ExtracteurBulletin
{
    private const DELAI_SECONDES = 30;

    public function extraire(string $cheminPdf): array
    {
        $process = new Process(['python3', base_path('scripts/parse_lano.py'), '--json', $cheminPdf]);
        $process->setTimeout(self::DELAI_SECONDES);

        try {
            $process->run();
        } catch (ProcessTimedOutException) {
            throw new ExtractionImpossible('Extraction interrompue : délai de '.self::DELAI_SECONDES.' secondes dépassé.');
        }

        if (! $process->isSuccessful()) {
            throw new ExtractionImpossible('Le bulletin n\'a pas pu être lu ('.trim($process->getErrorOutput()).').');
        }

        $donnees = json_decode($process->getOutput(), true);

        if (! is_array($donnees) || ! isset($donnees['entete'], $donnees['famille_rapport'], $donnees['resultats'])) {
            throw new ExtractionImpossible('Réponse inattendue de l\'extraction du bulletin.');
        }

        /** @var array{entete: array<string, mixed>, famille_rapport: string, resultats: array<string, mixed>} $donnees */
        return $donnees;
    }
}
