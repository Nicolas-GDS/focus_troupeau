<?php

// ENF-EXP-02 : point de santé de l'application, utilisé par la supervision.
it('répond sur le point de santé /up', function () {
    $this->get('/up')->assertOk();
});

it('est configurée en français', function () {
    expect(app()->getLocale())->toBe('fr');
});

it('utilise PostgreSQL avec PostGIS disponible', function () {
    $extension = DB::selectOne("SELECT name FROM pg_available_extensions WHERE name = 'postgis'");

    expect(DB::getDriverName())->toBe('pgsql')
        ->and($extension)->not->toBeNull();
});
