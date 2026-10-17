<?php

namespace App\Models\Stock;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * Mouvement d'un lot de stock (stock.mouvement, EF-M4-05) : achat, vente, re-comptage, ajustement ; il modifie le
 * stock sans effacer l'inventaire initial. Le détail (cotes, nombre de balles, fournisseur) est libre et n'est jamais
 * repris dans un libellé généré (EF-GEN-04).
 *
 * @property int $id
 * @property int $lot_stock_id
 * @property Carbon $date_mouvement
 * @property string $type
 * @property string|null $quantite_t_ms
 * @property array<string, mixed>|null $detail
 * @property string|null $prix_eur
 * @property int|null $scenario_ligne_id
 * @property string|null $motif
 * @property int|null $cree_par
 */
class Mouvement extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'lot_stock_id';

    public const CHEMIN_ELEVAGE = [['stock.lot_stock', 'id', 'elevage_id']];

    public const TYPES = ['achat', 'vente', 'recomptage', 'ajustement'];

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = null;

    protected $table = 'stock.mouvement';

    protected $fillable = ['lot_stock_id', 'date_mouvement', 'type', 'quantite_t_ms', 'detail', 'prix_eur', 'scenario_ligne_id', 'motif'];

    protected function casts(): array
    {
        return ['date_mouvement' => 'date', 'detail' => 'array'];
    }

    protected static function booted(): void
    {
        static::creating(fn (Mouvement $m) => $m->setAttribute('cree_par', $m->getAttribute('cree_par') ?? Auth::id()));
    }

    /** @return BelongsTo<LotStock, $this> */
    public function lot(): BelongsTo
    {
        return $this->belongsTo(LotStock::class, 'lot_stock_id');
    }
}
