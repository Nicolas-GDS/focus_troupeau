<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\LotAnimaux;
use App\Models\Plan\Phase;
use App\Models\Plan\Ration;
use App\Models\Plan\RationComposant;
use App\Services\Cloisonnement\Perimetre;
use Illuminate\Support\Facades\DB;

/**
 * Lots d'animaux et rations (§ 2.3 : éleveur L/E propre, technicien L/E, autres rôles L ; EF-M5) : lecture dans le
 * périmètre ; écriture avec la permission plan.ecrire et une convention active (D12, T-32). Un lot archivé n'accepte
 * plus de saisie ; un lot d'animaux s'archive, il ne se supprime pas (EF-GEN-06).
 */
class PlanPolicy
{
    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly ElevagePolicy $elevages,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('plan.lire');
    }

    public function view(User $user, LotAnimaux|Phase|Ration|RationComposant $objet): bool
    {
        return $user->can('plan.lire') && $this->perimetre->couvreElevage($user, $this->lotDe($objet)->elevage_id);
    }

    public function create(User $user, Elevage $elevage): bool
    {
        return $this->ecrire($user, $elevage->id);
    }

    public function update(User $user, LotAnimaux|Phase|Ration|RationComposant $objet): bool
    {
        $lot = $this->lotDe($objet);

        return $this->ecrire($user, $lot->elevage_id) && $lot->archive_le === null;
    }

    public function delete(User $user, LotAnimaux|Phase|Ration|RationComposant $objet): bool
    {
        return ! $objet instanceof LotAnimaux && $this->update($user, $objet);
    }

    /** Règle d'écriture de l'élevage (ElevagePolicy::ecrirePlan : permission, périmètre, convention active, T-32). */
    private function ecrire(User $user, string $elevageId): bool
    {
        return $this->elevages->ecrirePlan($user, Elevage::query()->withoutGlobalScopes()->findOrFail($elevageId));
    }

    /** @return object{elevage_id: string, archive_le: string|null} */
    private function lotDe(LotAnimaux|Phase|Ration|RationComposant $objet): object
    {
        $lotId = match (true) {
            $objet instanceof LotAnimaux => $objet->id,
            $objet instanceof Phase => $objet->lot_animaux_id,
            $objet instanceof Ration => DB::table('plan.phase')->where('id', $objet->phase_id)->value('lot_animaux_id'),
            default => DB::table('plan.phase')->where('id', DB::table('plan.ration')->where('id', $objet->ration_id)->value('phase_id'))->value('lot_animaux_id'),
        };

        return DB::table('plan.lot_animaux')->where('id', $lotId)->select('elevage_id', 'archive_le')->firstOrFail();
    }
}
