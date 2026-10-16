<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Action d'un plan (dossier.action, § 5.3, EF-M2-06) : priorité, responsable, « comment », « quand » (date ou
 * jalon), contrôles associés. L'éleveur marque « faite » une action qui lui incombe (§ 2.3).
 *
 * @property int $id
 * @property int $plan_action_id
 * @property int $priorite
 * @property int $ordre
 * @property string $responsable
 * @property string $titre
 * @property string|null $comment
 * @property string|null $quand
 * @property Carbon|null $echeance
 * @property string|null $jalon
 * @property string $statut
 * @property Carbon|null $fait_le
 * @property int|null $fait_par
 * @property string|null $commentaire_eleveur
 * @property int|null $valide_par
 * @property Carbon|null $valide_le
 * @property int|null $maj_par
 */
class Action extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'plan_action_id';

    public const CHEMIN_ELEVAGE = [
        ['dossier.plan_action', 'id', 'interpretation_visite_id'],
        ['dossier.interpretation_visite', 'id', 'visite_id'],
        ['dossier.visite', 'id', 'elevage_id'],
    ];

    public const RESPONSABLES = ['eleveur', 'technicien', 'veterinaire'];

    public const JALONS = [
        'tarissement', '6_sem_avant_velage', '3_sem_avant_velage', 'velage', 'entree_hivernage', 'mise_a_l_herbe',
        'campagne_suivante',
    ];

    public const STATUT_A_FAIRE = 'a_faire';

    public const STATUT_FAITE = 'faite';

    public const STATUT_ABANDONNEE = 'abandonnee';

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'dossier.action';

    protected $fillable = [
        'plan_action_id', 'priorite', 'ordre', 'responsable', 'titre', 'comment', 'quand', 'echeance', 'jalon', 'statut',
        'fait_le', 'fait_par', 'commentaire_eleveur', 'valide_par', 'valide_le', 'maj_par',
    ];

    protected $attributes = ['statut' => self::STATUT_A_FAIRE];

    protected function casts(): array
    {
        return [
            'priorite' => 'integer',
            'ordre' => 'integer',
            'echeance' => 'date',
            'fait_le' => 'date',
            'valide_le' => 'datetime',
        ];
    }

    /** @return BelongsTo<PlanAction, $this> */
    public function planAction(): BelongsTo
    {
        return $this->belongsTo(PlanAction::class);
    }

    /** @return HasMany<Controle, $this> */
    public function controles(): HasMany
    {
        return $this->hasMany(Controle::class)->orderBy('echeance');
    }
}
