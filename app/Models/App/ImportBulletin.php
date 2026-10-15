<?php

namespace App\Models\App;

use App\Models\Concerns\CloisonneParGds;
use App\Models\Fourrage\Analyse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Dépôt d'un bulletin PDF (app.import_bulletin, ajout du lot L2 ; EF-M9-01, E19). Cloisonné par le GDS du dépôt :
 * l'élevage n'est connu qu'après rapprochement, il est porté par l'analyse créée.
 *
 * @property int $id
 * @property int $gds_id
 * @property int|null $depose_par
 * @property string $nom_fichier
 * @property string|null $chemin
 * @property string $hash_sha256
 * @property int $taille_octets
 * @property string $statut
 * @property string|null $erreur
 * @property array<string, mixed>|null $donnees_extraites
 * @property int|null $analyse_id
 * @property Carbon $cree_le
 * @property Carbon $maj_le
 */
class ImportBulletin extends Model
{
    use CloisonneParGds;

    public const CREATED_AT = 'cree_le';

    public const UPDATED_AT = 'maj_le';

    protected $table = 'app.import_bulletin';

    protected $fillable = [
        'gds_id', 'depose_par', 'nom_fichier', 'chemin', 'hash_sha256', 'taille_octets', 'statut', 'erreur',
        'donnees_extraites', 'analyse_id',
    ];

    protected $attributes = ['statut' => 'recu'];

    protected function casts(): array
    {
        return ['donnees_extraites' => 'array'];
    }

    /** @return BelongsTo<Analyse, $this> */
    public function analyse(): BelongsTo
    {
        return $this->belongsTo(Analyse::class);
    }
}
