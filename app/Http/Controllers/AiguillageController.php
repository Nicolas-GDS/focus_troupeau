<?php

namespace App\Http\Controllers;

use App\Models\App\User;
use App\Services\Cloisonnement\Perimetre;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Page d'arrivée après connexion, selon le rôle (annexe 10) : éleveur et vétérinaire d'élevage → accueil (E02) ;
 * technicien et vétérinaire conseil → travail (E18) ; administrateur → administration (E23).
 */
class AiguillageController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        return match (true) {
            $user->hasAnyRole(['technicien', 'veterinaire_gds']) => redirect()->route('travail'),
            $user->hasAnyRole(['admin', Perimetre::ROLE_GLOBAL]) => redirect()->route('admin.utilisateurs'),
            $user->hasAnyRole(['eleveur', 'veterinaire']) => redirect()->route('accueil'),
            default => redirect()->route('compte-sans-role'),
        };
    }
}
