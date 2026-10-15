<?php

use App\Jobs\ClotureConventions;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches planifiées (service scheduler)
|--------------------------------------------------------------------------
*/

// § 2.4, EF-M0-06 : passage quotidien des conventions échues en lecture seule (D12).
Schedule::job(new ClotureConventions)->dailyAt('01:00')->name('cloture-conventions')->withoutOverlapping();
