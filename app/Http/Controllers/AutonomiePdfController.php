<?php

namespace App\Http\Controllers;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\Scenario;
use App\Models\Stock\Inventaire;
use App\Services\Autonomie\PdfAutonomie;
use App\Services\Cloisonnement\RegleStatut;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Export PDF du bilan d'autonomie (EF-M6-06) et d'un scénario (E15) : élevage lié à travers les scopes (404 hors
 * périmètre), policies de lecture des stocks et des scénarios.
 */
class AutonomiePdfController extends Controller
{
    public function bilan(Elevage $elevage, PdfAutonomie $pdf): Response
    {
        Gate::authorize('view', $elevage);
        Gate::authorize('viewAny', Inventaire::class);
        $inventaire = Inventaire::query()->where('elevage_id', $elevage->id)->where('statut', Inventaire::STATUT_COURANT)->firstOrFail();

        return $this->telecharger($pdf->bilan($inventaire), 'bilan-autonomie-'.$elevage->code_pseudo.'-'.now()->format('Y-m-d').'.pdf');
    }

    public function scenario(Request $request, Elevage $elevage, string $scenario, PdfAutonomie $pdf, RegleStatut $statuts): Response
    {
        Gate::authorize('view', $elevage);
        abort_unless(ctype_digit($scenario), 404);
        $s = Scenario::query()->whereIn('inventaire_id', Inventaire::query()->where('elevage_id', $elevage->id)->select('id'))->findOrFail((int) $scenario);
        Gate::authorize('view', $s);
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $this->telecharger($pdf->scenario($s, ! $statuts->voitTousLesStatuts($user)), 'scenario-'.$s->id.'-'.$elevage->code_pseudo.'.pdf');
    }

    private function telecharger(string $contenu, string $nom): Response
    {
        return response($contenu, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'attachment; filename="'.$nom.'"']);
    }
}
