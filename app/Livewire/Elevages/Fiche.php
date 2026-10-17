<?php

namespace App\Livewire\Elevages;

use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Models\App\Consentement;
use App\Models\App\Convention;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Administration\AnonymisationService;
use App\Services\Administration\ComptesService;
use App\Services\Administration\ConsentementsService;
use App\Services\Administration\ConventionsService;
use App\Services\Administration\ElevagesService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * E23 — fiche d'un élevage (EF-M0-02, EF-M0-06, § 2.4, § 2.5) : identité et caractéristiques, conventions (création,
 * prolongation), comptes éleveurs (invitation), consentements (lecture ; accord et révocation par l'administrateur).
 * L'élevage est lié à travers les scopes (404 hors périmètre).
 */
class Fiche extends Component
{
    use AdministrateurCourant;

    #[Locked]
    public string $elevageId;

    /** @var array<string, mixed> */
    public array $identite = [];

    /** @var array{date_debut: string, date_fin: string, reference_externe: string} */
    public array $convention = ['date_debut' => '', 'date_fin' => '', 'reference_externe' => ''];

    public string $prolongation = '';

    /** @var array{email: string, nom: string, prenom: string} */
    public array $invitation = ['email' => '', 'nom' => '', 'prenom' => ''];

    public function mount(Elevage $elevage): void
    {
        abort_unless($this->admin()->can('elevage.ecrire'), 403);
        $this->authorize('view', $elevage);
        $this->elevageId = $elevage->id;
        $i = DB::table('prive.elevage_identite')->where('elevage_id', $elevage->id)->first();
        $this->identite = [
            ...array_combine(ElevagesService::IDENTITE, array_map(fn ($c) => (string) ($i->{$c} ?? ''), ElevagesService::IDENTITE)),
            'especes' => $elevage->listeEspeces(), 'systeme_bovin' => (string) $elevage->systeme_bovin, 'systeme_ovin' => (string) $elevage->systeme_ovin,
            'systeme_caprin' => (string) $elevage->systeme_caprin, 'effectif_bovins' => (string) $elevage->effectif_bovins, 'effectif_ovins' => (string) $elevage->effectif_ovins,
            'effectif_caprins' => (string) $elevage->effectif_caprins, 'adherent_gds' => (bool) $elevage->adherent_gds, 'code_insee_commune' => (string) $elevage->code_insee_commune,
            'consentement_donnees' => (bool) ($i->consentement_donnees ?? false), 'consentement_date' => (string) ($i->consentement_date ?? ''),
        ];
        $this->convention['date_debut'] = now()->toDateString();
        $this->convention['date_fin'] = now()->addYear()->subDay()->toDateString();
    }

    public function enregistrer(ElevagesService $service): void
    {
        $this->validate(['identite.raison_sociale' => ['required', 'string', 'max:200'], 'identite.courriel' => ['nullable', 'email'], 'identite.code_postal' => ['nullable', 'regex:/^\d{5}$/'],
            'identite.code_insee_commune' => ['nullable', 'regex:/^\w{5}$/'], 'identite.effectif_bovins' => ['nullable', 'integer', 'min:0'],
            'identite.effectif_ovins' => ['nullable', 'integer', 'min:0'], 'identite.effectif_caprins' => ['nullable', 'integer', 'min:0']],
            [], ['identite.raison_sociale' => 'raison sociale', 'identite.courriel' => 'e-mail', 'identite.code_postal' => 'code postal']);
        $this->executer(fn () => $service->modifier($this->elevage(), $this->identite, $this->admin()), 'Fiche enregistrée.', 'identite');
    }

    public function creerConvention(ConventionsService $service): void
    {
        $this->authorize('create', Convention::class);
        $this->validate(['convention.date_debut' => ['required', 'date'], 'convention.date_fin' => ['required', 'date']], [], ['convention.date_debut' => 'début', 'convention.date_fin' => 'fin']);
        $this->executer(fn () => $service->creer($this->elevage(), $this->convention, $this->admin()), 'Convention créée.', 'convention');
    }

    public function prolonger(int $id, ConventionsService $service): void
    {
        $convention = Convention::query()->where('elevage_id', $this->elevageId)->findOrFail($id);
        $this->authorize('update', $convention);
        $this->validate(['prolongation' => ['required', 'date']], [], ['prolongation' => 'nouvelle date de fin']);
        if ($this->executer(fn () => $service->prolonger($convention, $this->prolongation, $this->admin()), 'Convention prolongée.', 'convention')) {
            $this->reset('prolongation');
        }
    }

    public function inviter(ComptesService $comptes): void
    {
        abort_unless($this->admin()->can('compte.inviter'), 403);
        $this->validate(['invitation.email' => ['required', 'email'], 'invitation.nom' => ['required', 'string', 'max:100'], 'invitation.prenom' => ['nullable', 'string', 'max:100']],
            [], ['invitation.email' => 'e-mail', 'invitation.nom' => 'nom']);
        $i = $this->invitation;
        if ($this->executer(fn () => $comptes->inviterEleveur($this->elevage(), $i['email'], $i['nom'], $i['prenom'] ?: null, $this->admin()), 'Compte éleveur invité.', 'invitation')) {
            $this->reset('invitation');
        }
    }

    public function revoquer(int $id, ConsentementsService $service): void
    {
        $c = Consentement::query()->where('elevage_id', $this->elevageId)->findOrFail($id);
        $this->authorize('revoquer', $c);
        $this->executer(fn () => $service->revoquer($c, $this->admin()), 'Consentement révoqué.', 'consentement');
    }

    public function accorderReferences(ConsentementsService $service): void
    {
        $elevage = $this->elevage();
        $this->authorize('create', [Consentement::class, $elevage]);
        $this->executer(fn () => $service->accorder($elevage, Consentement::TYPE_REFERENCES, null, $this->admin()), 'Consentement « références » enregistré.', 'consentement');
    }

    public string $motifAnonymisation = '';

    public function anonymiser(AnonymisationService $service): void
    {
        abort_unless($this->admin()->can('elevage.anonymiser'), 403);
        $this->executer(fn () => $service->anonymiser($this->elevage(), $this->motifAnonymisation, $this->admin()), 'Élevage anonymisé.', 'anonymisation');
    }

    public function render(): View
    {
        $elevage = $this->elevage();
        $admin = $this->admin();

        return view('livewire.elevages.fiche', [
            'elevage' => $elevage,
            'conventions' => Convention::query()->where('elevage_id', $elevage->id)->orderByDesc('date_debut')->get(),
            'comptes' => User::query()->whereIn('id', DB::table('app.elevage_acces')->where('elevage_id', $elevage->id)->where('role', 'eleveur')->select('user_id'))->orderBy('nom')->get(),
            'consentements' => Consentement::query()->where('elevage_id', $elevage->id)->with('veterinaire')->orderByDesc('accorde_le')->get(),
            'peutGererConsentements' => $admin->can('consentement.ecrire'),
            'peutCreerConvention' => $admin->can('convention.ecrire'),
            'peutExporter' => $admin->can('donnees.exporter'),
            'peutAnonymiser' => $admin->can('elevage.anonymiser'),
            'especes' => ElevagesService::ESPECES,
        ])->layout('components.layouts.espace', ['titre' => 'Élevage '.$elevage->code_pseudo]);
    }

    private function elevage(): Elevage
    {
        return Elevage::query()->findOrFail($this->elevageId);
    }
}
