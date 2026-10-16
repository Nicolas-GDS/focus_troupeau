<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Dossier\AnalyseEau;
use App\Models\Fourrage\Elevage;

/**
 * Analyses d'eau (§ 2.3 : éleveur L propre publiées ; technicien L/E/V ; autres rôles L), EF-M1-04, EF-M9-02.
 * Saisie et suppression tant que l'analyse n'est pas validée ; validation par le technicien.
 */
class AnalyseEauPolicy extends PolitiqueDossier
{
    private const MODIFIABLE = [AnalyseEau::STATUT_RECU, AnalyseEau::STATUT_CONTROLE, AnalyseEau::STATUT_ANOMALIE];

    public function view(User $user, AnalyseEau $analyse): bool
    {
        return $this->lisible($user, 'eau.lire', $analyse->elevage_id, $analyse->statut === AnalyseEau::STATUT_PUBLIE);
    }

    public function create(User $user, Elevage $elevage, ?int $visiteId = null): bool
    {
        return $user->can('eau.rediger') && $this->couvre($user, $elevage) && $this->visiteOuverte($visiteId);
    }

    public function update(User $user, AnalyseEau $analyse): bool
    {
        return $user->can('eau.rediger') && $this->couvre($user, $analyse->elevage_id)
            && in_array($analyse->statut, self::MODIFIABLE, true) && $this->visiteOuverte($analyse->visite_id);
    }

    public function valider(User $user, AnalyseEau $analyse): bool
    {
        return $this->update($user, $analyse) && $analyse->statut === AnalyseEau::STATUT_CONTROLE;
    }

    public function delete(User $user, AnalyseEau $analyse): bool
    {
        return $this->update($user, $analyse);
    }
}
