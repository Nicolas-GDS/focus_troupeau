<?php

use Illuminate\Support\Facades\Http;
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

/**
 * Have I Been Pwned (k-anonymat, ENF-SEC-02) : simule la réponse de l'API ; sans argument, aucune fuite connue.
 */
function hibp(?string $motDePasseFuite = null): void
{
    $suffixe = $motDePasseFuite === null ? '' : substr(strtoupper(sha1($motDePasseFuite)), 5).':42';
    Http::fake(['api.pwnedpasswords.com/*' => Http::response($suffixe)]);
}
