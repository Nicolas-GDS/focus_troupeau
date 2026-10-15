<?php

namespace App\Http\Controllers\Authentification;

use App\Http\Controllers\Controller;
use App\Services\Authentification\LienConnexionService;
use App\Services\JournalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Laravel\Fortify\Fortify;

/**
 * Activation du compte à partir de l'invitation : choix du mot de passe (§ 2.4, EF-M0-02, EF-M0-03).
 * Le mot de passe suit ENF-SEC-02 : 12 caractères minimum, absent des listes de mots de passe compromis.
 */
class ActivationController extends Controller
{
    public function __construct(private readonly LienConnexionService $liens) {}

    public function create(string $jeton): View
    {
        return view('authentification.activation', [
            'valide' => $this->liens->estValide($jeton, LienConnexionService::TYPE_ACTIVATION),
        ]);
    }

    public function store(Request $request, string $jeton, JournalService $journal): RedirectResponse
    {
        $request->validate(['password' => ['required', 'string', Password::default(), 'confirmed']]);

        $user = DB::transaction(function () use ($jeton, $request) {
            $user = $this->liens->consommer($jeton, LienConnexionService::TYPE_ACTIVATION);
            $user?->forceFill(['password' => (string) $request->input('password')])->save();

            return $user;
        });

        if ($user === null) {
            return redirect()->back()->withErrors([
                'password' => 'Ce lien d\'activation n\'est plus valable. Demandez une nouvelle invitation à votre technicien.',
            ]);
        }

        $journal->ecrire('activation_compte', $user->id, 'app.user:'.$user->id);

        $request->session()->put('connexion.methode', 'activation');
        Auth::login($user);
        $request->session()->regenerate();

        return redirect(Fortify::redirects('login'));
    }
}
