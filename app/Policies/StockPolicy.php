<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\Mouvement;
use App\Models\Stock\PeriodePhase;
use App\Services\Cloisonnement\Perimetre;
use Illuminate\Support\Facades\DB;

/**
 * Inventaires de stocks (§ 2.3 « Inventaires de stocks » : éleveur L/E propre, technicien L/E, autres rôles L ;
 * EF-M4) : lecture dans le périmètre, sans règle de publication ; écriture avec la permission stock.ecrire et une
 * convention active (D12, T-32). Un inventaire clos ou archivé n'accepte plus de saisie. Rien n'est supprimé
 * physiquement, sauf un lot ou un mouvement saisi par erreur dans un inventaire courant.
 */
class StockPolicy
{
    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly ElevagePolicy $elevages,
    ) {}

    public function viewAny(User $user): bool
    {
        return $user->can('stock.lire');
    }

    public function view(User $user, Inventaire|LotStock|Mouvement|PeriodePhase $objet): bool
    {
        return $user->can('stock.lire') && $this->perimetre->couvreElevage($user, $this->elevageDe($objet));
    }

    public function create(User $user, Elevage $elevage): bool
    {
        return $this->ecrire($user, $elevage->id);
    }

    public function update(User $user, Inventaire|LotStock|Mouvement|PeriodePhase $objet): bool
    {
        return $this->ecrire($user, $this->elevageDe($objet)) && $this->inventaireCourant($objet);
    }

    public function delete(User $user, Inventaire|LotStock|Mouvement|PeriodePhase $objet): bool
    {
        return ! $objet instanceof Inventaire && $this->update($user, $objet);
    }

    /** Règle d'écriture de l'élevage (ElevagePolicy::ecrireStocks : permission, périmètre, convention active, T-32). */
    private function ecrire(User $user, string $elevageId): bool
    {
        return $this->elevages->ecrireStocks($user, Elevage::query()->withoutGlobalScopes()->findOrFail($elevageId));
    }

    private function inventaireCourant(Inventaire|LotStock|Mouvement|PeriodePhase $objet): bool
    {
        $inventaireId = match (true) {
            $objet instanceof Inventaire => $objet->id,
            $objet instanceof Mouvement => DB::table('stock.lot_stock')->where('id', $objet->lot_stock_id)->value('inventaire_id'),
            default => $objet->inventaire_id,
        };

        return DB::table('stock.inventaire')->where('id', $inventaireId)->value('statut') === Inventaire::STATUT_COURANT;
    }

    private function elevageDe(Inventaire|LotStock|Mouvement|PeriodePhase $objet): string
    {
        return (string) match (true) {
            $objet instanceof Inventaire, $objet instanceof LotStock => $objet->elevage_id,
            $objet instanceof Mouvement => DB::table('stock.lot_stock')->where('id', $objet->lot_stock_id)->value('elevage_id'),
            default => DB::table('stock.inventaire')->where('id', $objet->inventaire_id)->value('elevage_id'),
        };
    }
}
