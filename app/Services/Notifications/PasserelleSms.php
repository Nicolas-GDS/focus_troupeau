<?php

namespace App\Services\Notifications;

/**
 * Envoi d'un SMS transactionnel (EF-M8-02, EF-M8-05) ; renvoie l'identifiant du message chez le fournisseur.
 */
interface PasserelleSms
{
    /** @throws EnvoiImpossible */
    public function envoyer(string $numero, string $texte): ?string;
}
