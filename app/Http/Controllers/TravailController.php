<?php

namespace App\Http\Controllers;

use App\Models\App\User;
use App\Services\Travail\FilesDeTravail;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * E18 — « Mon travail » : files de travail du technicien et du vétérinaire conseil (EF-M2-08).
 */
class TravailController extends Controller
{
    public function __invoke(Request $request, FilesDeTravail $files): View
    {
        /** @var User $user */
        $user = $request->user();

        return view('espace.travail', ['files' => $files->pour($user)]);
    }
}
