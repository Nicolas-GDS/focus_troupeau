<?php

namespace App\Http\Controllers\Authentification;

use App\Http\Controllers\Controller;
use App\Services\Authentification\LienConnexionService;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Laravel\Fortify\Fortify;

/**
 * Connexion par lien envoyé par e-mail (EF-M0-03, ENF-SEC-02, T-30).
 *
 * L'ouverture du lien (GET) affiche une confirmation sans consommer le lien : les antivirus de
 * messagerie qui « visitent » les liens ne doivent pas l'épuiser. La connexion se fait au POST.
 */
class LienConnexionController extends Controller
{
    public function __construct(private readonly LienConnexionService $liens) {}

    public function create(): View
    {
        return view('authentification.lien-demande');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        $this->liens->demanderConnexion(mb_strtolower((string) $request->input('email')), $request->ip());

        return back()->with('status', 'Si un compte correspond à cette adresse, un lien de connexion vient de lui être envoyé. Il est valable '
            .config('authentification.lien_connexion_minutes').' minutes.');
    }

    public function confirmer(string $jeton): View
    {
        return view('authentification.lien-confirmer', [
            'valide' => $this->liens->estValide($jeton, LienConnexionService::TYPE_CONNEXION),
        ]);
    }

    public function connecter(Request $request, string $jeton, JournalService $journal): RedirectResponse
    {
        $user = $this->liens->consommer($jeton, LienConnexionService::TYPE_CONNEXION);

        if ($user === null) {
            $journal->ecrire('connexion_echec', null, null, ['methode' => 'lien', 'motif' => 'lien_invalide']);

            return redirect()->route('connexion.lien')
                ->withErrors(['email' => 'Ce lien n\'est plus valable : il a déjà servi ou a expiré. Demandez-en un nouveau.']);
        }

        $request->session()->put('connexion.methode', 'lien');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->intended(Fortify::redirects('login'));
    }
}
