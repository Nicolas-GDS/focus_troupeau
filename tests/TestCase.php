<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Les vues se rendent sans les ressources compilées par Vite (absentes en intégration continue).
        $this->withoutVite();

        // Aucun appel réseau réel depuis les tests : chaque appel externe doit être simulé explicitement.
        Http::preventStrayRequests();
    }
}
