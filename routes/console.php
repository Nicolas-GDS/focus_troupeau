<?php

use App\Jobs\ClotureConventions;
use App\Jobs\RafraichirContextePrelevements;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches planifiées (service scheduler)
|--------------------------------------------------------------------------
*/

// § 2.4, EF-M0-06 : passage quotidien des conventions échues en lecture seule (D12).
Schedule::job(new ClotureConventions)->dailyAt('01:00')->name('cloture-conventions')->withoutOverlapping();

// § 5.6, RC-3 : contexte pédo-géologique des prélèvements (population lithologique des références), chaque nuit.
Schedule::job(new RafraichirContextePrelevements)->dailyAt('02:00')->name('contexte-prelevements')->withoutOverlapping();
