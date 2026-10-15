<?php

namespace App\Models\Ref;

use Illuminate\Database\Eloquent\Model;

/**
 * Référence bibliographique d'un seuil ou d'un besoin (EF-GEN-02 : source du repère).
 *
 * @property int $id
 * @property string $cle
 * @property string|null $auteurs
 * @property int|null $annee
 * @property string $titre
 */
class ReferenceBiblio extends Model
{
    protected $table = 'ref.reference_biblio';

    public $timestamps = false;

    protected $fillable = ['cle', 'auteurs', 'annee', 'titre', 'source', 'url', 'type_source', 'statut_verif'];
}
