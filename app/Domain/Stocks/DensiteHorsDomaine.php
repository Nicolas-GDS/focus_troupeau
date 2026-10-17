<?php

namespace App\Domain\Stocks;

use DomainException;

/**
 * Équation de densité appliquée hors de son domaine de validité (RC-5 : équations herbe au-delà de 36 % de MS,
 * document 05, vigilance n° 2) : une densité mesurée ou saisie est exigée.
 */
final class DensiteHorsDomaine extends DomainException {}
