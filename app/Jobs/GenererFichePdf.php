<?php

namespace App\Jobs;

use App\Models\Dossier\InterpretationVisite;
use App\Services\Dossier\FicheVisiteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Produit et archive le PDF de la fiche figée d'une interprétation de visite publiée (EF-M2-07, EF-GEN-08), après la
 * validation de la transaction de publication. Le téléchargement le produit à la demande s'il manque encore.
 */
class GenererFichePdf implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public readonly int $interpretationVisiteId) {}

    public function handle(FicheVisiteService $fiches): void
    {
        $interpretation = InterpretationVisite::query()->withoutGlobalScopes()->find($this->interpretationVisiteId);

        if ($interpretation !== null && $interpretation->fiche_html !== null && $interpretation->fiche_pdf === null) {
            $fiches->genererPdf($interpretation);
        }
    }
}
