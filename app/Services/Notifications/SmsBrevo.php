<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\Http;

/**
 * SMS transactionnel par l'API Brevo (POST /transactionalSMS/sms ; chapitre 6.6), par le client HTTP de Laravel :
 * aucun SDK ajouté.
 */
class SmsBrevo implements PasserelleSms
{
    public function envoyer(string $numero, string $texte): ?string
    {
        $cle = config('services.brevo.cle_api');

        if (! is_string($cle) || $cle === '') {
            throw new EnvoiImpossible('Clé d\'API Brevo absente (BREVO_CLE_API).');
        }

        $reponse = Http::withHeaders(['api-key' => $cle, 'accept' => 'application/json'])
            ->timeout(15)
            ->post(rtrim((string) config('services.brevo.url_api'), '/').'/transactionalSMS/sms', [
                'sender' => (string) config('services.brevo.expediteur_sms'),
                'recipient' => $numero,
                'content' => $texte,
                'type' => 'transactional',
            ]);

        if ($reponse->failed()) {
            throw new EnvoiImpossible('Brevo a refusé le SMS ('.$reponse->status().') : '.mb_substr($reponse->body(), 0, 300));
        }

        $id = $reponse->json('messageId');

        return $id === null ? null : (string) $id;
    }
}
