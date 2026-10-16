<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\NoteLiaison;
use App\Models\Dossier\Visite;
use App\Services\Dossier\NoteLiaisonService;

/**
 * Note de liaison vétérinaire (EF-M2-09, § 2.5) : rédigée et envoyée par le technicien ; lue par les agents du
 * périmètre et par le vétérinaire destinataire tant que son consentement est actif ; le destinataire y répond.
 */
class NoteLiaisonPolicy extends PolitiqueDossier
{
    public function view(User $user, NoteLiaison $note): bool
    {
        if (! $this->couvre($user, $note->elevage_id)) {
            return false;
        }

        return ($this->agent($user) && $user->can('interpretation.lire')) || $note->veterinaire_user_id === $user->id;
    }

    public function create(User $user, InterpretationVisite $interpretation): bool
    {
        $elevage = (string) Visite::query()->withoutGlobalScopes()->whereKey($interpretation->visite_id)->value('elevage_id');

        return $user->can('note_liaison.rediger') && $this->couvre($user, $elevage) && NoteLiaisonService::eligible($interpretation);
    }

    public function repondre(User $user, NoteLiaison $note): bool
    {
        return $note->veterinaire_user_id === $user->id && $note->statut === NoteLiaison::STATUT_ENVOYEE
            && $this->couvre($user, $note->elevage_id);
    }
}
