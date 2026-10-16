<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Dossier\IndicateurSanitaire;
use App\Models\Fourrage\Elevage;

/**
 * Indicateurs du bilan sanitaire (§ 2.3 : éleveur L propre ; technicien L/E (import) ; autres rôles L), EF-M1-07.
 * Ces indicateurs n'ont pas de circuit de publication : ils sont lisibles dans le périmètre.
 */
class IndicateurSanitairePolicy extends PolitiqueDossier
{
    public function view(User $user, IndicateurSanitaire $indicateur): bool
    {
        return $user->can('bilan_sanitaire.lire') && $this->couvre($user, $indicateur->elevage_id);
    }

    public function create(User $user, Elevage $elevage): bool
    {
        return $user->can('bilan_sanitaire.rediger') && $this->couvre($user, $elevage);
    }

    public function update(User $user, IndicateurSanitaire $indicateur): bool
    {
        return $user->can('bilan_sanitaire.rediger') && $this->couvre($user, $indicateur->elevage_id);
    }

    public function delete(User $user, IndicateurSanitaire $indicateur): bool
    {
        return $this->update($user, $indicateur);
    }
}
