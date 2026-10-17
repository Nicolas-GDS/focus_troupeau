<?php

namespace App\Livewire\Compte;

use App\Models\App\Consentement;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Administration\ConsentementsService;
use App\Services\Dossier\SaisieRefusee;
use App\Services\ElevageCourant;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use RuntimeException;

/**
 * E17 — consentements de l'éleveur (EF-M0-07, § 2.5) : pour chacun de ses élevages, accord ou révocation de
 * l'agrégation dans les références et du partage avec un vétérinaire désigné ; effet immédiat, journalisé.
 */
class Consentements extends Component
{
    /** @var array<string, string> vétérinaire choisi par élevage */
    public array $veterinaires = [];

    public function accorderReferences(string $elevageId, ConsentementsService $service): void
    {
        $elevage = $this->elevage($elevageId);
        $this->authorize('create', [Consentement::class, $elevage]);
        $this->executer(fn () => $service->accorder($elevage, Consentement::TYPE_REFERENCES, null, $this->lecteur()), 'Vos lots entreront dans les références au prochain rafraîchissement.', $elevageId);
    }

    public function accorderVeterinaire(string $elevageId, ConsentementsService $service): void
    {
        $elevage = $this->elevage($elevageId);
        $this->authorize('create', [Consentement::class, $elevage]);
        $veterinaire = User::role('veterinaire')->where('actif', true)->find((int) ($this->veterinaires[$elevageId] ?? 0));
        $this->executer(fn () => $service->accorder($elevage, Consentement::TYPE_VETERINAIRE, $veterinaire, $this->lecteur()), 'Votre vétérinaire peut désormais lire votre dossier.', $elevageId);
    }

    public function revoquer(int $id, ConsentementsService $service): void
    {
        $c = Consentement::query()->findOrFail($id);
        $this->authorize('revoquer', $c);
        $this->executer(fn () => $service->revoquer($c, $this->lecteur()), 'Consentement révoqué : il prend effet immédiatement.', $c->elevage_id);
    }

    public function render(): View
    {
        $user = $this->lecteur();
        $elevages = Elevage::query()->whereIn('id', DB::table('app.elevage_acces')->where('user_id', $user->id)->where('role', 'eleveur')->select('elevage_id'))->orderBy('code_pseudo')->get();

        return view('livewire.compte.consentements', [
            'elevages' => $elevages,
            'libelles' => $elevages->mapWithKeys(fn (Elevage $e) => [$e->id => ElevageCourant::libelle($e->setAttribute('raison_sociale', DB::table('prive.elevage_identite')->where('elevage_id', $e->id)->value('raison_sociale')))]),
            'consentements' => Consentement::query()->whereIn('elevage_id', $elevages->pluck('id'))->with('veterinaire')->orderByDesc('accorde_le')->get()->groupBy('elevage_id'),
            'choixVeterinaires' => User::role('veterinaire')->where('actif', true)->orderBy('nom')->get(['id', 'nom', 'prenom']),
        ]);
    }

    private function executer(callable $operation, string $message, string $champ): void
    {
        try {
            $operation();
            session()->flash('status', $message);
        } catch (SaisieRefusee $e) {
            $this->addError('elevage-'.$champ, $e->getMessage());
        }
    }

    private function elevage(string $id): Elevage
    {
        abort_unless(DB::table('app.elevage_acces')->where('user_id', $this->lecteur()->id)->where('elevage_id', $id)->where('role', 'eleveur')->exists(), 404);

        return Elevage::query()->findOrFail($id);
    }

    private function lecteur(): User
    {
        $user = Auth::user();
        if (! $user instanceof User) {
            throw new RuntimeException('Utilisateur non connecté.');
        }

        return $user;
    }
}
