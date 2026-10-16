<?php

namespace App\Services\Dossier;

use RuntimeException;

/**
 * Saisie du dossier refusée par une règle métier (visite fermée, objet déjà validé, valeur incohérente) :
 * le message est affiché tel quel à l'utilisateur.
 */
class SaisieRefusee extends RuntimeException {}
