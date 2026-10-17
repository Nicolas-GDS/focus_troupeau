<?php

namespace App\Http\Controllers;

use App\Models\App\User;
use App\Services\Pilotage\PilotageService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Exports CSV du pilotage (E25, EF-M10-04, parcours P4) : indicateurs des GDS visibles et références agrégées d'un GDS.
 */
class PilotageController extends Controller
{
    public function indicateurs(Request $request, PilotageService $pilotage): Response
    {
        $campagne = (string) $request->query('campagne', $pilotage->campagnes()[0] ?? '');

        return $this->csv($pilotage->exportIndicateurs($campagne, $this->utilisateur($request)), 'indicateurs-pilotage-'.$campagne.'.csv');
    }

    public function references(Request $request, PilotageService $pilotage): Response
    {
        $campagne = (string) $request->query('campagne', $pilotage->campagnes()[0] ?? '');
        $gds = (int) $request->query('gds');

        return $this->csv($pilotage->exportReferences($gds, $campagne, $this->utilisateur($request)), 'references-gds'.$gds.'-'.$campagne.'.csv');
    }

    private function utilisateur(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function csv(string $contenu, string $nom): Response
    {
        return response($contenu, 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="'.$nom.'"']);
    }
}
