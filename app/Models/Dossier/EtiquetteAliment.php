<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Concerns\TraceAuteur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Étiquette d'un minéral, bolus, seau ou aliment distribué (dossier.etiquette_aliment, § 5.3, EF-M1-06).
 * Le libellé saisi par l'éleveur est affiché tel quel, jamais repris dans une préconisation (EF-GEN-04).
 * Circuit : saisie (éleveur ou technicien) → validee (technicien) → publiee.
 *
 * @property int $id
 * @property string $elevage_id
 * @property int|null $visite_id
 * @property string $nature
 * @property string $libelle_eleveur
 * @property string|null $photo
 * @property Carbon|null $date_limite
 * @property string|null $dose_g_par_animal_jour
 * @property string|null $mode_distribution
 * @property string $statut
 * @property string|null $ms_pct matière sèche, quand l'étiquette décrit un concentré de ration (lot L5)
 * @property int|null $cree_par
 */
class EtiquetteAliment extends Model
{
    use CloisonneParElevage, TraceAuteur;

    public const NATURES = ['mineral', 'bolus', 'seau', 'sel', 'concentre', 'autre'];

    /** Libellés des natures (affichage). */
    public const LIBELLES_NATURES = ['mineral' => 'Minéral', 'bolus' => 'Bolus', 'seau' => 'Seau à lécher', 'sel' => 'Sel', 'concentre' => 'Aliment concentré', 'autre' => 'Autre'];

    public const STATUT_SAISIE = 'saisie';

    public const STATUT_VALIDEE = 'validee';

    public const STATUT_PUBLIEE = 'publiee';

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'dossier.etiquette_aliment';

    protected $fillable = [
        'elevage_id', 'visite_id', 'nature', 'libelle_eleveur', 'photo', 'date_limite', 'dose_g_par_animal_jour',
        'mode_distribution', 'statut', 'ms_pct',
    ];

    protected $attributes = ['statut' => self::STATUT_SAISIE];

    protected function casts(): array
    {
        return ['date_limite' => 'date'];
    }

    /** @return HasMany<EtiquetteComposition, $this> */
    public function composition(): HasMany
    {
        return $this->hasMany(EtiquetteComposition::class, 'etiquette_id');
    }
}
