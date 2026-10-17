<?php

namespace App\Services\Administration;

use App\Models\App\Gds;
use App\Models\App\User;
use App\Services\Cloisonnement\Perimetre;
use App\Services\Dossier\SaisieRefusee;
use App\Services\JournalService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * GDS de l'instance (E23, EF-M0-01) : l'administrateur modifie le contact de ses GDS ; le super-administrateur crée
 * un GDS, le désactive ou le réactive. La couleur locale suit la charte (§ 8.4).
 */
class GdsService
{
    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly JournalService $journal,
    ) {}

    /** @return Collection<int, Gds> */
    public function visibles(User $admin): Collection
    {
        return Gds::query()
            ->when(! $this->perimetre->sansRestriction($admin), fn ($q) => $q->whereIn('id', $this->perimetre->gdsAccessibles($admin)))
            ->orderBy('id')->get();
    }

    /**
     * @param  array{nom: string, contact_email?: string|null, site_web?: string|null, couleur_locale?: string|null, actif?: bool}  $d
     */
    public function modifier(Gds $gds, array $d, User $admin): Gds
    {
        if (! $this->visibles($admin)->contains('id', $gds->id)) {
            throw new SaisieRefusee('GDS hors de votre périmètre.');
        }
        $attributs = ['nom' => trim($d['nom']), 'contact_email' => trim((string) ($d['contact_email'] ?? '')) ?: null, 'site_web' => trim((string) ($d['site_web'] ?? '')) ?: null];
        if ($this->perimetre->sansRestriction($admin)) {
            $attributs['couleur_locale'] = ($d['couleur_locale'] ?? null) ?: $gds->couleur_locale;
            $attributs['actif'] = $d['actif'] ?? $gds->actif;
        }
        $gds->update($attributs);
        $this->journal->ecrire('modification_gds', $admin->id, 'app.gds:'.$gds->id);

        return $gds;
    }

    /**
     * @param  array{id: int, code: string, nom: string, couleur_locale: string, contact_email?: string|null, site_web?: string|null}  $d
     */
    public function creer(array $d, User $admin): Gds
    {
        if (! $this->perimetre->sansRestriction($admin)) {
            throw new SaisieRefusee('Seul le super-administrateur crée un GDS.');
        }
        if (DB::table('app.gds')->where('id', $d['id'])->orWhere('code', $d['code'])->exists()) {
            throw new SaisieRefusee('Ce numéro ou ce code de GDS existe déjà.');
        }
        $gds = Gds::query()->create([...$d, 'actif' => true]);
        $this->journal->ecrire('creation_gds', $admin->id, 'app.gds:'.$gds->id);

        return $gds;
    }
}
