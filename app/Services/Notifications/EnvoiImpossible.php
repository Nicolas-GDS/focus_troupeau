<?php

namespace App\Services\Notifications;

use RuntimeException;

/** Échec d'envoi d'une notification (fournisseur, numéro, adresse) : journalisé dans app.notification. */
class EnvoiImpossible extends RuntimeException {}
