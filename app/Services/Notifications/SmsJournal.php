<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Log;

/**
 * Pilote SMS de développement et de test : aucun envoi, le message est écrit dans le journal applicatif.
 */
class SmsJournal implements PasserelleSms
{
    public function envoyer(string $numero, string $texte): ?string
    {
        Log::info('SMS (pilote journal)', ['numero' => $numero, 'texte' => $texte]);

        return 'journal-'.bin2hex(random_bytes(6));
    }
}
