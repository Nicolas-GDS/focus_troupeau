<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Administration\ComptesService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * E23 — comptes (EF-M10-01) : recherche, création avec invitation, rôles, rattachements aux GDS et aux élevages,
 * activation, renvoi d'invitation. Réservé à l'administrateur (compte.ecrire), dans le périmètre de ses GDS.
 */
class Utilisateurs extends Component
{
    use AdministrateurCourant, WithPagination;

    #[Url]
    public string $recherche = '';

    #[Url]
    public string $role = '';

    #[Url]
    public ?int $compte = null;

    /** @var array{email: string, nom: string, prenom: string, telephone: string, roles: list<string>, gds: list<int|string>} */
    public array $nouveau = ['email' => '', 'nom' => '', 'prenom' => '', 'telephone' => '', 'roles' => [], 'gds' => []];

    /** @var array{nom: string, prenom: string, telephone: string, roles: list<string>, gds: list<int|string>} */
    public array $edition = ['nom' => '', 'prenom' => '', 'telephone' => '', 'roles' => [], 'gds' => []];

    public string $elevageARattacher = '';

    public function mount(): void
    {
        abort_unless($this->admin()->can('compte.ecrire'), 403);
        if ($this->compte !== null) {
            $this->choisir($this->compte);
        }
    }

    public function updatedRecherche(): void
    {
        $this->resetPage();
    }

    public function creer(ComptesService $comptes): void
    {
        $this->validate([
            'nouveau.email' => ['required', 'email', 'max:200'], 'nouveau.nom' => ['required', 'string', 'max:100'],
            'nouveau.prenom' => ['nullable', 'string', 'max:100'], 'nouveau.telephone' => ['nullable', 'string', 'max:30'],
            'nouveau.roles' => ['array', 'min:1'], 'nouveau.roles.*' => [Rule::in(ComptesService::ROLES)], 'nouveau.gds' => ['array'],
        ], [], ['nouveau.email' => 'e-mail', 'nouveau.nom' => 'nom', 'nouveau.roles' => 'rôles']);
        $n = $this->nouveau;
        $cree = null;
        if ($this->executer(function () use ($comptes, $n, &$cree) {
            $cree = $comptes->creer([...$n, 'gds' => array_map('intval', $n['gds'])], $this->admin());
        }, 'Compte créé'.(array_intersect($n['roles'], ComptesService::ROLES_INVITES) !== [] ? ' ; invitation envoyée.' : '.'), 'nouveau')) {
            $this->reset('nouveau');
            $this->choisir($cree->id);
        }
    }

    public function choisir(int $id): void
    {
        $user = app(ComptesService::class)->visibles($this->admin())->findOrFail($id);
        $this->compte = $user->id;
        $this->edition = [
            'nom' => $user->nom, 'prenom' => (string) $user->prenom, 'telephone' => (string) $user->telephone,
            'roles' => $user->getRoleNames()->values()->all(),
            'gds' => DB::table('app.user_gds')->where('user_id', $user->id)->pluck('gds_id')->map(fn ($g) => (int) $g)->all(),
        ];
        $this->resetErrorBag();
    }

    public function enregistrer(ComptesService $comptes): void
    {
        $user = $this->compteChoisi();
        $this->validate([
            'edition.nom' => ['required', 'string', 'max:100'], 'edition.prenom' => ['nullable', 'string', 'max:100'],
            'edition.telephone' => ['nullable', 'string', 'max:30'], 'edition.roles' => ['array'], 'edition.roles.*' => [Rule::in(ComptesService::ROLES)],
        ], [], ['edition.nom' => 'nom']);
        $e = $this->edition;
        $admin = $this->admin();
        $this->executer(fn () => $comptes->modifier($user, [...$e,
            'roles' => array_values(array_intersect($e['roles'], $comptes->rolesAttribuables($admin))),
            'gds' => array_values(array_intersect(array_map('intval', $e['gds']), $comptes->gdsAttribuables($admin)))], $admin), 'Compte enregistré.', 'edition');
    }

    public function activer(bool $actif, ComptesService $comptes): void
    {
        $this->executer(fn () => $comptes->changerActivation($this->compteChoisi(), $actif, $this->admin()), $actif ? 'Compte réactivé.' : 'Compte désactivé.', 'edition');
    }

    public function inviter(ComptesService $comptes): void
    {
        $this->executer(fn () => $comptes->renvoyerInvitation($this->compteChoisi(), $this->admin()), 'Invitation envoyée.', 'edition');
    }

    public function rattacher(ComptesService $comptes): void
    {
        $elevage = Elevage::query()->where('code_pseudo', trim($this->elevageARattacher))->first();
        if ($elevage === null) {
            $this->addError('rattachement', 'Élevage introuvable dans votre périmètre (code pseudonyme).');

            return;
        }
        if ($this->executer(fn () => $comptes->rattacher($this->compteChoisi(), $elevage, $this->admin()), 'Compte rattaché à '.$elevage->code_pseudo.'.', 'rattachement')) {
            $this->reset('elevageARattacher');
            $this->choisir((int) $this->compte);
        }
    }

    public function detacher(string $elevageId, ComptesService $comptes): void
    {
        $elevage = Elevage::query()->findOrFail($elevageId);
        $this->executer(fn () => $comptes->detacher($this->compteChoisi(), $elevage, $this->admin()), 'Rattachement retiré.', 'rattachement');
    }

    public function render(ComptesService $comptes): View
    {
        $admin = $this->admin();
        $choisi = $this->compte === null ? null : $comptes->visibles($admin)->find($this->compte);

        return view('livewire.admin.utilisateurs', [
            'comptes' => $comptes->visibles($admin)
                ->when(trim($this->recherche) !== '', fn ($q) => $q->where(fn ($q) => $q->where('email', 'ilike', '%'.trim($this->recherche).'%')->orWhere('nom', 'ilike', '%'.trim($this->recherche).'%')))
                ->when($this->role !== '', fn ($q) => $q->role($this->role))
                ->with('roles')->orderBy('nom')->orderBy('id')->paginate(25),
            'choisi' => $choisi,
            'rattachements' => $choisi === null ? collect() : DB::table('app.elevage_acces as a')->join('fourrage.elevage as e', 'e.id', '=', 'a.elevage_id')
                ->where('a.user_id', $choisi->id)->orderBy('e.code_pseudo')->get(['e.id', 'e.code_pseudo', 'a.role']),
            'rolesAttribuables' => $comptes->rolesAttribuables($admin),
            'gdsAttribuables' => DB::table('app.gds')->whereIn('id', $comptes->gdsAttribuables($admin))->orderBy('id')->pluck('nom', 'id'),
            'libellesRoles' => self::libellesRoles(),
        ]);
    }

    /** @return array<string, string> */
    public static function libellesRoles(): array
    {
        return ['eleveur' => 'Éleveur', 'veterinaire' => 'Vétérinaire d\'élevage', 'technicien' => 'Technicien', 'veterinaire_gds' => 'Vétérinaire conseil',
            'admin' => 'Administrateur', 'admin_global' => 'Super-administrateur'];
    }

    private function compteChoisi(): User
    {
        return app(ComptesService::class)->visibles($this->admin())->findOrFail((int) $this->compte);
    }
}
