<?php

namespace App\Http\Controllers;

use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\Visite;
use App\Services\Dossier\FicheVisiteService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Téléchargement de la fiche de visite figée en PDF (EF-M2-07, EF-GEN-08) : lien signé et policy de lecture de la
 * version (ENF-SEC-05) ; une version archivée reste téléchargeable par les agents (T-06). Le PDF est produit à la
 * demande s'il n'a pas encore été généré par le job.
 */
class FicheVisiteController extends Controller
{
    public function pdf(Visite $visite, string $version, FicheVisiteService $fiches): StreamedResponse
    {
        // Numéro de version contrôlé ici, après l'authentification (un visiteur est d'abord renvoyé vers la connexion)
        abort_unless(ctype_digit($version), 404);
        $version = (int) $version;

        $interpretation = InterpretationVisite::query()->where('visite_id', $visite->id)->where('version', $version)
            ->whereIn('statut', [InterpretationVisite::STATUT_PUBLIE, InterpretationVisite::STATUT_ARCHIVE])
            ->whereNotNull('fiche_html')->firstOrFail();
        Gate::authorize('view', $interpretation);

        $chemin = $interpretation->fiche_pdf ?? $fiches->genererPdf($interpretation);
        $nom = 'fiche-visite-'.$visite->elevage()->value('code_pseudo').'-'.$visite->date_visite->format('Y-m-d').'-v'.$version.'.pdf';

        return Storage::disk((string) config('fiches.disque'))->download($chemin, $nom, ['Content-Type' => 'application/pdf']);
    }
}
