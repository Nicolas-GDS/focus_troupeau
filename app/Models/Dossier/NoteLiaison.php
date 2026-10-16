<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Concerns\TraceAuteur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Note de liaison vétérinaire (dossier.note_liaison, § 5.3, EF-M2-09) : générée depuis une interprétation de
 * niveau 2 ou 3, envoyée au vétérinaire désigné et archivée.
 *
 * @property int $id
 * @property string $elevage_id
 * @property int|null $interpretation_id
 * @property int|null $interpretation_visite_id
 * @property int|null $veterinaire_user_id
 * @property string|null $question
 * @property string|null $retour
 * @property string|null $pdf
 * @property Carbon|null $envoyee_le
 * @property Carbon|null $retour_le
 * @property string $statut
 * @property string|null $lot_animaux
 * @property int|null $cree_par
 */
class NoteLiaison extends Model
{
    use CloisonneParElevage, TraceAuteur;

    public const STATUT_BROUILLON = 'brouillon';

    public const STATUT_ENVOYEE = 'envoyee';

    public const STATUT_RETOUR_RECU = 'retour_recu';

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'dossier.note_liaison';

    protected $fillable = [
        'elevage_id', 'interpretation_id', 'interpretation_visite_id', 'veterinaire_user_id', 'question', 'retour', 'pdf',
        'envoyee_le', 'retour_le', 'statut', 'lot_animaux',
    ];

    protected $attributes = ['statut' => self::STATUT_BROUILLON];

    protected function casts(): array
    {
        return ['envoyee_le' => 'datetime', 'retour_le' => 'datetime'];
    }
}
