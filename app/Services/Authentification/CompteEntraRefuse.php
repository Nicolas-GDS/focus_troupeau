<?php

namespace App\Services\Authentification;

use RuntimeException;

/**
 * Connexion Entra ID refusée (compte hors groupes autorisés ou désactivé) — EF-M0-04, T-31.
 * Le message est destiné à l'utilisateur.
 */
class CompteEntraRefuse extends RuntimeException {}
