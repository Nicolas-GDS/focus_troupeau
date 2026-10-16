<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Contrôle associé à une action, étape « suivre » (dossier.controle, § 5.3, EF-M2-06) : mesure, cible, échéance ;
 * résultat saisi par le technicien.
 *
 * @property int $id
 * @property int $action_id
 * @property string|null $mesure_code
 * @property string $cible
 * @property Carbon $echeance
 * @property string|null $resultat
 * @property int|null $mesure_terrain_id
 * @property int|null $analyse_animale_id
 * @property string $statut
 * @property int|null $maj_par
 */
class Controle extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'action_id';

    public const CHEMIN_ELEVAGE = [
        ['dossier.action', 'id', 'plan_action_id'],
        ['dossier.plan_action', 'id', 'interpretation_visite_id'],
        ['dossier.interpretation_visite', 'id', 'visite_id'],
        ['dossier.visite', 'id', 'elevage_id'],
    ];

    public const STATUT_PLANIFIE = 'planifie';

    public const STATUT_REALISE = 'realise';

    public const STATUT_ECHU = 'echu';

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'dossier.controle';

    protected $fillable = [
        'action_id', 'mesure_code', 'cible', 'echeance', 'resultat', 'mesure_terrain_id', 'analyse_animale_id', 'statut', 'maj_par',
    ];

    protected $attributes = ['statut' => self::STATUT_PLANIFIE];

    protected function casts(): array
    {
        return ['echeance' => 'date'];
    }

    /** @return BelongsTo<Action, $this> */
    public function action(): BelongsTo
    {
        return $this->belongsTo(Action::class);
    }
}
