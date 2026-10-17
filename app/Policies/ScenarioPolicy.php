<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\LevierLigne;
use App\Models\Plan\Scenario;
use App\Models\Stock\Inventaire;
use App\Services\Cloisonnement\Perimetre;
use Illuminate\Support\Facades\DB;

/**
 * Scénarios d'approvisionnement (§ 2.3 : éleveur L/E propre, technicien L/E, autres rôles L ; EF-M7) : lecture dans le
 * périmètre ; écriture déléguée à ElevagePolicy::ecrirePlan (convention active, T-32), sur un inventaire courant et un
 * scénario non archivé.
 */
class ScenarioPolicy
{
    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly ElevagePolicy $elevages,
    ) {}

    public function view(User $user, Scenario|LevierLigne $objet): bool
    {
        return $user->can('plan.lire') && $this->perimetre->couvreElevage($user, $this->contexte($objet)['elevage']);
    }

    public function update(User $user, Scenario|LevierLigne $objet): bool
    {
        $c = $this->contexte($objet);

        return $c['statut'] !== Scenario::STATUT_ARCHIVE && $c['inventaire'] === Inventaire::STATUT_COURANT
            && $this->elevages->ecrirePlan($user, Elevage::query()->withoutGlobalScopes()->findOrFail($c['elevage']));
    }

    public function delete(User $user, Scenario|LevierLigne $objet): bool
    {
        return $this->update($user, $objet) && ! ($objet instanceof Scenario && $objet->statut === Scenario::STATUT_RETENU);
    }

    /** @return array{elevage: string, statut: string, inventaire: string} */
    private function contexte(Scenario|LevierLigne $objet): array
    {
        $scenarioId = $objet instanceof Scenario ? $objet->id : $objet->scenario_id;
        $ligne = DB::table('plan.scenario as s')->join('stock.inventaire as i', 'i.id', '=', 's.inventaire_id')
            ->where('s.id', $scenarioId)->select('i.elevage_id', 's.statut', 'i.statut as inventaire')->firstOrFail();

        return ['elevage' => (string) $ligne->elevage_id, 'statut' => (string) $ligne->statut, 'inventaire' => (string) $ligne->inventaire];
    }
}
