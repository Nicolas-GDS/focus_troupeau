<?php

namespace App\Services\Import;

use RuntimeException;

/** Le bulletin n'a pas pu être lu (PDF illisible, maquette inconnue, délai dépassé). */
class ExtractionImpossible extends RuntimeException {}
