<?php

namespace App\Services\Interpretation;

use RuntimeException;

/**
 * Opération refusée sur une interprétation : transition interdite (EF-M2-04), objet non modifiable, saisie incomplète.
 * Le message est rédigé pour être affiché tel quel au technicien.
 */
class InterpretationRefusee extends RuntimeException {}
