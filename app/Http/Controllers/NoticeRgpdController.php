<?php

namespace App\Http\Controllers;

use App\Models\App\User;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Notice d'information RGPD à la première connexion, acceptation horodatée et journalisée (ENF-RGPD-02).
 */
class NoticeRgpdController extends Controller
{
    public function show(): View
    {
        return view('rgpd.notice');
    }

    public function store(Request $request, JournalService $journal): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->notice_rgpd_acceptee_le === null) {
            $user->forceFill(['notice_rgpd_acceptee_le' => now()])->save();
            $journal->ecrire('notice_rgpd_acceptee', $user->id, 'app.user:'.$user->id);
        }

        return redirect()->intended(route('tableau-de-bord'));
    }
}
