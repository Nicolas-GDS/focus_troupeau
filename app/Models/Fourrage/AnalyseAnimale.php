<?php

namespace App\Models\Fourrage;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Concerns\TraceAuteur;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Profil sanguin ou analyse animale (fourrage.analyse_animale, document 02 ; colonnes visite_id, statut,
 * lecture_code ajoutées au lot L3), lu par RC-1.5 (EF-M1-05). Circuit : saisie → validee → publiee.
 *
 * @property int $id
 * @property string $elevage_id
 * @property int|null $visite_id
 * @property Carbon $date_prelevement
 * @property string $espece_code
 * @property string|null $categorie_code
 * @property int|null $nb_animaux
 * @property string $matrice
 * @property string $parametre
 * @property string|null $valeur_moyenne
 * @property string|null $valeur_min
 * @property string|null $valeur_max
 * @property string $unite
 * @property string|null $interpretation_labo
 * @property int|null $laboratoire_id
 * @property string|null $commentaire
 * @property string $statut
 * @property string|null $lecture_code
 */
class AnalyseAnimale extends Model
{
    use CloisonneParElevage, TraceAuteur;

    public const MATRICES = ['sang', 'serum', 'plasma', 'foie', 'urine', 'lait', 'poils'];

    public const STATUT_SAISIE = 'saisie';

    public const STATUT_VALIDEE = 'validee';

    public const STATUT_PUBLIEE = 'publiee';

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'fourrage.analyse_animale';

    protected $fillable = [
        'elevage_id', 'visite_id', 'date_prelevement', 'espece_code', 'categorie_code', 'nb_animaux', 'matrice', 'parametre',
        'valeur_moyenne', 'valeur_min', 'valeur_max', 'unite', 'interpretation_labo', 'laboratoire_id', 'commentaire',
        'statut', 'lecture_code',
    ];

    protected $attributes = ['statut' => self::STATUT_SAISIE];

    protected function casts(): array
    {
        return ['date_prelevement' => 'date', 'nb_animaux' => 'integer'];
    }
}
