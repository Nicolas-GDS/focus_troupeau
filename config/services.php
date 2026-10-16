<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    // Entra ID du tenant GDS (EF-M0-04) : socialiteproviders/microsoft, OpenID Connect.
    'microsoft' => [
        'client_id' => env('MICROSOFT_CLIENT_ID'),
        'client_secret' => env('MICROSOFT_CLIENT_SECRET'),
        'redirect' => env('MICROSOFT_REDIRECT_URI', env('APP_URL').'/auth/entra/retour'),
        'tenant' => env('MICROSOFT_TENANT_ID'),
    ],

    // Brevo (EF-M8-05, chapitre 6.6) : e-mails par le SMTP Brevo (MAIL_*), SMS par l'API transactionnelle.
    // Pilote SMS « journal » en développement et en test : aucun envoi, le texte est écrit dans le journal.
    'brevo' => [
        'cle_api' => env('BREVO_CLE_API'),
        'url_api' => env('BREVO_URL_API', 'https://api.brevo.com/v3'),
        'expediteur_sms' => env('BREVO_SMS_EXPEDITEUR', 'GDS'),
        'pilote_sms' => env('SMS_PILOTE', 'journal'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
