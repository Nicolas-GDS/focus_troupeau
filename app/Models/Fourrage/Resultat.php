<?php

namespace App\Models\Fourrage;

use App\Models\Concerns\CloisonneParElevage;
use App\Models\Ref\Parametre;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Résultat d'un bulletin, format long (fourrage.resultat) ; clé (analyse_id, parametre_code).
 * Écrit par ImportBulletinService ; lu par Eloquent, la clé composite n'étant pas gérée en mise à jour.
 *
 * @property int $analyse_id
 * @property string $parametre_code
 * @property string|null $valeur
 * @property string|null $censure
 * @property string $unite
 * @property string $origine
 * @property bool $flag_anomalie
 * @property string|null $commentaire
 */
class Resultat extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'analyse_id';

    public const CHEMIN_ELEVAGE = [['fourrage.analyse', 'id', 'prelevement_id'], ['fourrage.prelevement', 'id', 'elevage_id']];

    protected $table = 'fourrage.resultat';

    protected $primaryKey = 'analyse_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $fillable = ['analyse_id', 'parametre_code', 'valeur', 'censure', 'unite', 'origine', 'flag_anomalie', 'commentaire'];

    protected function casts(): array
    {
        return ['flag_anomalie' => 'boolean'];
    }

    /** @return BelongsTo<Analyse, $this> */
    public function analyse(): BelongsTo
    {
        return $this->belongsTo(Analyse::class);
    }

    /** @return BelongsTo<Parametre, $this> */
    public function parametre(): BelongsTo
    {
        return $this->belongsTo(Parametre::class, 'parametre_code', 'code');
    }
}
