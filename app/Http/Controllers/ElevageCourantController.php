<?php

namespace App\Http\Controllers;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\ElevageCourant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Choix de l'élevage courant dans le sélecteur d'en-tête (EF-M0-08).
 * L'élevage est résolu à travers les scopes : un élevage hors périmètre donne 404.
 */
class ElevageCourantController extends Controller
{
    public function __invoke(Request $request, ElevageCourant $elevageCourant): RedirectResponse
    {
        $request->validate(['elevage' => ['required', 'uuid']]);

        $elevage = Elevage::query()->findOrFail($request->input('elevage'));

        /** @var User $user */
        $user = $request->user();
        $elevageCourant->choisir($user, $elevage);

        return back();
    }
}
