<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Interprétation de visite (dossier.interpretation_visite, § 5.3, EF-M2-05 à EF-M2-07) : synthèse unique publiée
 * à l'éleveur. Circuit : brouillon → valide → (visee si niveau 3) → publie → archive ; une modification après
 * publication crée la version suivante (§ 2.3, T-06).
 *
 * @property int $id
 * @property int $visite_id
 * @property int $version
 * @property int|null $niveau_global niveau retenu par le technicien
 * @property int|null $niveau_propose
 * @property string|null $titre
 * @property string|null $synthese
 * @property string|null $limites
 * @property string $version_grille
 * @property string $statut
 * @property int|null $redige_par
 * @property int|null $vise_par
 * @property Carbon|null $vise_le
 * @property string|null $refus_visa_motif
 * @property int|null $refus_visa_par
 * @property Carbon|null $refus_visa_le
 * @property int|null $publie_par
 * @property Carbon|null $publie_le
 * @property string|null $fiche_html
 * @property string|null $fiche_pdf
 * @property int|null $maj_par
 */
class InterpretationVisite extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'visite_id';

    public const CHEMIN_ELEVAGE = [['dossier.visite', 'id', 'elevage_id']];

    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_VALIDE = 'valide';

    public const STATUT_VISEE = 'visee';

    public const STATUT_PUBLIE = 'publie';

    public const STATUT_ARCHIVE = 'archive';

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'dossier.interpretation_visite';

    protected $fillable = [
        'visite_id', 'version', 'niveau_global', 'niveau_propose', 'titre', 'synthese', 'limites', 'version_grille',
        'statut', 'redige_par', 'vise_par', 'vise_le', 'refus_visa_motif', 'refus_visa_par', 'refus_visa_le',
        'publie_par', 'publie_le', 'fiche_html', 'fiche_pdf', 'maj_par',
    ];

    protected $attributes = ['version' => 1, 'statut' => self::STATUT_BROUILLON];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'niveau_global' => 'integer',
            'niveau_propose' => 'integer',
            'vise_le' => 'datetime',
            'refus_visa_le' => 'datetime',
            'publie_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<Visite, $this> */
    public function visite(): BelongsTo
    {
        return $this->belongsTo(Visite::class);
    }

    /** @return HasMany<Message, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('ordre');
    }

    /** @return HasMany<CauseCommune, $this> */
    public function causesCommunes(): HasMany
    {
        return $this->hasMany(CauseCommune::class);
    }

    /** @return HasOne<PlanAction, $this> */
    public function planAction(): HasOne
    {
        return $this->hasOne(PlanAction::class);
    }
}
