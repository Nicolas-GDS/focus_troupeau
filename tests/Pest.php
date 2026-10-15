<?php

use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Configuration Pest
|--------------------------------------------------------------------------
|
| Les tests Feature tournent sur la base PostgreSQL de test (focustroupeau_test),
| jamais sur SQLite : le socle utilise PostGIS, des schémas et des contraintes
| propres à PostgreSQL.
|
*/

pest()->extend(TestCase::class)
    ->in('Feature');
