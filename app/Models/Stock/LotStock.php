<?php

namespace App\Models\Stock;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Concerns\TraceAuteur;
use App\Models\Fourrage\Prelevement;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Lot de stock d'un inventaire (stock.lot_stock, § 5.4, EF-M4-02 à EF-M4-04) : silo (cotes, MS, densité) ou balles,
 * bottes, vrac (nombre, poids, MS) ; pertes retenues et valeur par défaut affichée à côté ; lien éventuel avec un
 * prélèvement analysé. Les colonnes *_calc et stock_*_t_ms sont recalculées à chaque enregistrement (RC-5, RC-7).
 *
 * @property int $id
 * @property int $inventaire_id
 * @property string $elevage_id
 * @property string $libelle
 * @property string $famille_code
 * @property string|null $type_fourrage_code
 * @property int|null $prelevement_id
 * @property string $forme
 * @property Carbon|null $date_recolte
 * @property int|null $priorite_consommation
 * @property string|null $ouvrage
 * @property string|null $equation_code
 * @property string|null $l1_m
 * @property string|null $l2_m
 * @property string|null $largeur_m
 * @property string|null $hauteur_m
 * @property int|null $nb_mesures_hauteur
 * @property Carbon|null $date_fermeture
 * @property string|null $amidon_pct
 * @property string|null $grain_pct
 * @property string|null $methode_densite
 * @property string|null $densite_kg_ms_m3
 * @property string|null $nombre
 * @property string|null $poids_brut_kg
 * @property bool $poids_mesure
 * @property string|null $volume_m3
 * @property string|null $mode_stockage
 * @property string|null $ms_pct
 * @property string|null $ms_origine
 * @property string $pertes_pct
 * @property string|null $pertes_defaut_pct
 * @property string|null $volume_calc_m3
 * @property string|null $densite_calc
 * @property string|null $stock_brut_t_ms
 * @property string|null $stock_utile_t_ms
 * @property string|null $incertitude_rel
 * @property string|null $commentaire
 * @property int|null $scenario_ligne_id lot « achat » créé par un scénario retenu (lot L6)
 */
class LotStock extends Model
{
    use CloisonneParElevage, TraceAuteur;

    public const FORMES = ['silo', 'balle_ronde', 'balle_carree', 'botte', 'vrac'];

    public const OUVRAGES = ['couloir', 'taupiniere'];

    public const EQUATIONS = ['mais_couloir', 'mais_taupe', 'sorgho_couloir', 'sorgho_taupe', 'herbe_courts', 'herbe_longs', 'herbe_couloir', 'herbe_taupe'];

    public const METHODES_DENSITE = ['equation', 'tamisage', 'carottage', 'saisie'];

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'stock.lot_stock';

    protected $fillable = [
        'inventaire_id', 'elevage_id', 'libelle', 'famille_code', 'type_fourrage_code', 'prelevement_id', 'forme', 'date_recolte',
        'priorite_consommation', 'ouvrage', 'equation_code', 'l1_m', 'l2_m', 'largeur_m', 'hauteur_m', 'nb_mesures_hauteur',
        'date_fermeture', 'amidon_pct', 'grain_pct', 'methode_densite', 'densite_kg_ms_m3', 'nombre', 'poids_brut_kg', 'poids_mesure',
        'volume_m3', 'mode_stockage', 'ms_pct', 'ms_origine', 'pertes_pct', 'pertes_defaut_pct', 'volume_calc_m3', 'densite_calc',
        'stock_brut_t_ms', 'stock_utile_t_ms', 'incertitude_rel', 'commentaire', 'scenario_ligne_id',
    ];

    protected $attributes = ['poids_mesure' => false];

    protected function casts(): array
    {
        return ['date_recolte' => 'date', 'date_fermeture' => 'date', 'poids_mesure' => 'boolean'];
    }

    /** @return BelongsTo<Inventaire, $this> */
    public function inventaire(): BelongsTo
    {
        return $this->belongsTo(Inventaire::class);
    }

    /** @return BelongsTo<Prelevement, $this> */
    public function prelevement(): BelongsTo
    {
        return $this->belongsTo(Prelevement::class);
    }

    /** @return HasMany<Mouvement, $this> */
    public function mouvements(): HasMany
    {
        return $this->hasMany(Mouvement::class);
    }
}
