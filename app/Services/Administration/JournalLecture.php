<?php

namespace App\Services\Administration;

use App\Models\App\User;
use App\Services\Cloisonnement\Perimetre;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Lecture du journal d'audit (E23, EF-M10-01, ENF-SEC-08) : connexions et actions sensibles, filtrées par action,
 * compte, élevage et période. Un administrateur voit les entrées des élevages de ses GDS et des comptes rattachés à
 * ses GDS ; le super-administrateur voit tout.
 */
class JournalLecture
{
    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly ComptesService $comptes,
    ) {}

    /**
     * @param  array{action?: string|null, compte?: string|null, elevage?: string|null, du?: string|null, au?: string|null}  $filtres
     * @return LengthAwarePaginator<int, object>
     */
    public function entrees(User $admin, array $filtres, int $parPage = 50): LengthAwarePaginator
    {
        $requete = DB::table('app.journal as j')->leftJoin('app.user as u', 'u.id', '=', 'j.user_id')->leftJoin('fourrage.elevage as e', 'e.id', '=', 'j.elevage_id')
            ->select('j.id', 'j.horodatage', 'j.action', 'j.objet', 'j.detail', 'j.ip', 'u.email', 'u.prenom', 'u.nom', 'e.code_pseudo');

        if (! $this->perimetre->sansRestriction($admin)) {
            $gds = $this->perimetre->gdsAccessibles($admin);
            $comptes = $this->comptes->visibles($admin)->select('id');
            $requete->where(fn ($q) => $q->whereIn('e.gds_id', $gds)->orWhere(fn ($q) => $q->whereNull('j.elevage_id')->whereIn('j.user_id', $comptes)));
        }

        return $requete
            ->when($filtres['action'] ?? null, fn ($q, $a) => $q->where('j.action', $a))
            ->when($filtres['compte'] ?? null, fn ($q, $c) => $q->where('u.email', 'ilike', '%'.$c.'%'))
            ->when($filtres['elevage'] ?? null, fn ($q, $e) => $q->where('e.code_pseudo', 'ilike', '%'.$e.'%'))
            ->when($filtres['du'] ?? null, fn ($q, $d) => $q->whereDate('j.horodatage', '>=', $d))
            ->when($filtres['au'] ?? null, fn ($q, $d) => $q->whereDate('j.horodatage', '<=', $d))
            ->orderByDesc('j.horodatage')->orderByDesc('j.id')->paginate($parPage);
    }

    /** @return list<string> */
    public function actions(): array
    {
        return DB::table('app.journal')->distinct()->orderBy('action')->pluck('action')->all();
    }
}
