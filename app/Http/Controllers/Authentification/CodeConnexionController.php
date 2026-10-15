<?php

namespace App\Http\Controllers\Authentification;

use App\Http\Controllers\Controller;
use App\Models\App\User;
use App\Services\Authentification\CodeVerificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Laravel\Fortify\Fortify;

/**
 * Saisie du code reçu par e-mail, deuxième étape de la connexion (EF-M0-03, ENF-SEC-03).
 */
class CodeConnexionController extends Controller
{
    public function __construct(private readonly CodeVerificationService $codes) {}

    public function create(Request $request): View|RedirectResponse
    {
        if ($this->compteEnAttente($request) === null) {
            return redirect()->route('login');
        }

        return view('authentification.code');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['code' => ['required', 'string']]);
        $user = $this->compteEnAttente($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        if (! $this->codes->verifier($user, (string) $request->input('code'))) {
            throw ValidationException::withMessages(['code' => ['Ce code est incorrect ou n\'est plus valable.']]);
        }

        $remember = (bool) $request->session()->pull('connexion.code.remember', false);
        $request->session()->forget('connexion.code');

        $request->session()->put('connexion.methode', 'mot_de_passe_code');
        Auth::login($user, $remember);
        $request->session()->regenerate();

        return redirect()->intended(Fortify::redirects('login'));
    }

    public function renvoyer(Request $request): RedirectResponse
    {
        $user = $this->compteEnAttente($request);

        if ($user === null) {
            return redirect()->route('login');
        }

        $this->codes->envoyer($user);

        return back()->with('status', 'Un nouveau code vous a été envoyé.');
    }

    private function compteEnAttente(Request $request): ?User
    {
        $id = $request->session()->get('connexion.code.user_id');

        return $id === null ? null : User::query()->where('id', $id)->where('actif', true)->first();
    }
}
