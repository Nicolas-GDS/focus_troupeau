<?php

namespace App\Services\Agds;

use RuntimeException;

/**
 * La source AGDS (connexion SAGEMA) n'a pas répondu : base injoignable, droits manquants, vue absente.
 */
class SourceAgdsIndisponible extends RuntimeException {}
