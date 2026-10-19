<?php

use App\Jobs\EnvoyerNotification;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use Database\Seeders\JeuJ3Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

// ENF-PERF-01 (T-42) : les écrans de consultation ne refont pas une vérification d'autorisation par objet (N+1) ;
// les vérifications de périmètre, d'élevage du prélèvement et de publication sont mémorisées le temps d'une requête.
uses(RefreshDatabase::class);

it('affiche les fourrages et l\'historique des neuf lots de J3 en un nombre de requêtes borné', function (string $page, int $maximum) {
    Queue::fake([EnvoyerNotification::class]);
    (new JeuJ3Seeder)->run();
    $elevage = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', 'ELV-0001')->sole();
    DB::table('fourrage.interpretation')->update(['statut' => 'publie']);
    $this->actingAs(User::query()->where('email', JeuJ3Seeder::ELEVEUR)->sole());
    $this->get(route('dossier.'.$page, $elevage->id))->assertOk(); // chauffe : rôles, référentiels en cache

    app()->forgetScopedInstances(); // une requête réelle repart d'un périmètre vide
    DB::flushQueryLog();
    DB::enableQueryLog();
    $this->get(route('dossier.'.$page, $elevage->id))->assertOk();

    expect(count(DB::getQueryLog()))->toBeLessThanOrEqual($maximum);
})->with([['fourrages', 35], ['historique', 35]]); // un N+1 sur les 9 lots en ajouterait au moins 27
