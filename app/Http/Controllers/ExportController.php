<?php

namespace App\Http\Controllers;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Cloisonnement\RegleStatut;
use App\Services\Export\ExportsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Exports de l'élevage (EF-M9-04 rationnement, EF-M9-05 portabilité) : élevage lié à travers les scopes (404 hors
 * périmètre), permission donnees.exporter ; un lecteur externe ne reçoit que le publié (§ 6.3).
 */
class ExportController extends Controller
{
    public function rationnement(Request $request, Elevage $elevage, ExportsService $exports, RegleStatut $statuts): Response
    {
        $user = $this->utilisateur($request, $elevage);
        $campagne = is_string($request->query('campagne')) ? $request->query('campagne') : null;
        $contenu = $exports->rationnement($elevage, ! $statuts->voitTousLesStatuts($user), $user, $campagne);

        return response($contenu, 200, ['Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="rationnement-'.$elevage->code_pseudo.($campagne ? '-'.$campagne : '').'.csv"']);
    }

    public function donnees(Request $request, Elevage $elevage, ExportsService $exports, RegleStatut $statuts): BinaryFileResponse
    {
        $user = $this->utilisateur($request, $elevage);
        $chemin = $exports->donnees($elevage, ! $statuts->voitTousLesStatuts($user), $user);

        return response()->download($chemin, 'donnees-'.$elevage->code_pseudo.'-'.now()->format('Y-m-d').'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }

    private function utilisateur(Request $request, Elevage $elevage): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        Gate::authorize('view', $elevage);

        return $user;
    }
}
