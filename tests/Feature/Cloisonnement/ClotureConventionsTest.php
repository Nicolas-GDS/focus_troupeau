<?php

use App\Jobs\ClotureConventions;
use App\Models\App\Convention;
use App\Models\App\Journal;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;

// § 2.4, EF-M0-06, D12 : passage automatique des conventions à « echue » à la date de fin (tâche quotidienne).
uses(RefreshDatabase::class);

it('passe à « echue » les conventions actives dont la date de fin est passée, et journalise', function () {
    $echue = Convention::factory()->create(['date_fin' => now()->subDay()->toDateString()]);
    $enCours = Convention::factory()->create(['date_fin' => today()->toDateString()]);
    $resiliee = Convention::factory()->create(['date_fin' => now()->subMonth()->toDateString(), 'statut' => 'resiliee']);

    ClotureConventions::dispatchSync();

    expect($echue->fresh()?->statut)->toBe('echue')
        ->and($enCours->fresh()?->statut)->toBe('active')
        ->and($resiliee->fresh()?->statut)->toBe('resiliee')
        ->and(Journal::query()->where('action', 'convention_echue')->sole()->objet)->toBe('app.convention:'.$echue->id);
});

it('traite les conventions de tous les GDS, hors cloisonnement', function () {
    $user = User::factory()->entra()->create();
    $user->assignRole('technicien');
    $user->gds()->attach(24);
    $convention47 = Convention::factory()->create([
        'elevage_id' => Elevage::factory()->gds(47),
        'date_fin' => now()->subDay()->toDateString(),
    ]);
    $this->actingAs($user);

    ClotureConventions::dispatchSync();

    expect(Convention::query()->withoutGlobalScopes()->find($convention47->id)?->statut)->toBe('echue');
});

it('est planifiée chaque jour', function () {
    $evenement = collect(app(Schedule::class)->events())->first(fn ($e) => $e->description === 'cloture-conventions');

    expect($evenement)->not->toBeNull()
        ->and($evenement->expression)->toBe('0 1 * * *');
});
