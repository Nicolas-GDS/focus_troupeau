<?php

namespace App\Livewire\Admin;

use App\Livewire\Admin\Concerns\AdministrateurCourant;
use App\Models\App\Gds as ModeleGds;
use App\Services\Administration\GdsService;
use App\Services\Cloisonnement\Perimetre;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

/**
 * E23 — GDS de l'instance (EF-M0-01) : contact de ses GDS pour l'administrateur ; création, couleur locale et
 * activation pour le super-administrateur. Effectifs suivis (élevages, conventions actives, agents) en lecture.
 */
class Gds extends Component
{
    use AdministrateurCourant;

    /** @var array<int, array{nom: string, contact_email: string, site_web: string, couleur_locale: string, actif: bool}> */
    public array $fiches = [];

    /** @var array{id: string, code: string, nom: string, couleur_locale: string, contact_email: string} */
    public array $nouveau = ['id' => '', 'code' => '', 'nom' => '', 'couleur_locale' => '', 'contact_email' => ''];

    public function mount(GdsService $service): void
    {
        abort_unless($this->admin()->can('gds.ecrire'), 403);
        foreach ($service->visibles($this->admin()) as $g) {
            $this->fiches[$g->id] = ['nom' => $g->nom, 'contact_email' => (string) $g->contact_email, 'site_web' => (string) $g->site_web, 'couleur_locale' => $g->couleur_locale, 'actif' => (bool) $g->actif];
        }
    }

    public function enregistrer(int $id, GdsService $service): void
    {
        $this->validate(["fiches.{$id}.nom" => ['required', 'string', 'max:200'], "fiches.{$id}.contact_email" => ['nullable', 'email'], "fiches.{$id}.site_web" => ['nullable', 'url']],
            [], ["fiches.{$id}.nom" => 'nom', "fiches.{$id}.contact_email" => 'e-mail', "fiches.{$id}.site_web" => 'site web']);
        $this->executer(fn () => $service->modifier(ModeleGds::query()->findOrFail($id), $this->fiches[$id], $this->admin()), 'GDS enregistré.', 'gds-'.$id);
    }

    public function creer(GdsService $service): void
    {
        $this->validate(['nouveau.id' => ['required', 'integer', 'between:1,999'], 'nouveau.code' => ['required', 'string', 'size:2'], 'nouveau.nom' => ['required', 'string', 'max:200'],
            'nouveau.couleur_locale' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'], 'nouveau.contact_email' => ['nullable', 'email']],
            [], ['nouveau.id' => 'numéro', 'nouveau.code' => 'code', 'nouveau.nom' => 'nom', 'nouveau.couleur_locale' => 'couleur']);
        $n = $this->nouveau;
        if ($this->executer(fn () => $service->creer(['id' => (int) $n['id'], 'code' => $n['code'], 'nom' => $n['nom'], 'couleur_locale' => $n['couleur_locale'], 'contact_email' => $n['contact_email'] ?: null], $this->admin()), 'GDS créé.', 'nouveau')) {
            $this->fiches[(int) $n['id']] = ['nom' => $n['nom'], 'contact_email' => $n['contact_email'], 'site_web' => '', 'couleur_locale' => $n['couleur_locale'], 'actif' => true];
            $this->reset('nouveau');
        }
    }

    public function render(): View
    {
        $ids = array_keys($this->fiches);

        return view('livewire.admin.gds', [
            'global' => $this->admin()->hasRole(Perimetre::ROLE_GLOBAL),
            'elevages' => DB::table('fourrage.elevage')->whereIn('gds_id', $ids)->groupBy('gds_id')->selectRaw('gds_id, count(*) as n')->pluck('n', 'gds_id'),
            'conventions' => DB::table('app.convention')->whereIn('gds_id', $ids)->where('statut', 'active')->groupBy('gds_id')->selectRaw('gds_id, count(*) as n')->pluck('n', 'gds_id'),
            'agents' => DB::table('app.user_gds')->whereIn('gds_id', $ids)->groupBy('gds_id')->selectRaw('gds_id, count(*) as n')->pluck('n', 'gds_id'),
        ]);
    }
}
