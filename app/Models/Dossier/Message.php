<?php

namespace App\Models\Dossier;

use App\Models\Concerns\CloisonneParElevage;
use Illuminate\Database\Eloquent\Model;

/**
 * Message hiérarchisé d'une interprétation de visite (dossier.message, § 5.3, EF-M2-05 : cinq au plus).
 *
 * @property int $id
 * @property int $interpretation_visite_id
 * @property int $ordre
 * @property int $gravite
 * @property string $texte
 * @property string|null $pilier
 * @property string $origine
 * @property string|null $reference_objet
 */
class Message extends Model
{
    use CloisonneParElevage;

    public const COLONNE_ELEVAGE = 'interpretation_visite_id';

    public const CHEMIN_ELEVAGE = [
        ['dossier.interpretation_visite', 'id', 'visite_id'],
        ['dossier.visite', 'id', 'elevage_id'],
    ];

    public const PILIERS = ['hydratation', 'nutrition', 'physique', 'environnement', 'bien_etre'];

    public $timestamps = false;

    protected $table = 'dossier.message';

    protected $fillable = ['interpretation_visite_id', 'ordre', 'gravite', 'texte', 'pilier', 'origine', 'reference_objet'];

    protected function casts(): array
    {
        return ['ordre' => 'integer', 'gravite' => 'integer'];
    }
}
