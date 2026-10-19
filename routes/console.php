<?php

use App\Jobs\ClotureConventions;
use App\Jobs\EvaluerAlertesAutonomie;
use App\Jobs\RafraichirContextePrelevements;
use App\Jobs\RappelConventions;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Tâches planifiées (service scheduler)
|--------------------------------------------------------------------------
*/

// § 2.4, EF-M0-06 : passage quotidien des conventions échues en lecture seule (D12).
Schedule::job(new ClotureConventions)->dailyAt('01:00')->name('cloture-conventions')->withoutOverlapping();

// EF-M0-06 : rappel au technicien avant l'échéance d'une convention, une fois par convention.
Schedule::job(new RappelConventions)->dailyAt('01:15')->name('rappel-conventions')->withoutOverlapping();

// § 5.6, RC-3 : contexte pédo-géologique des prélèvements (population lithologique des références), chaque nuit.
Schedule::job(new RafraichirContextePrelevements)->dailyAt('02:00')->name('contexte-prelevements')->withoutOverlapping();

// EF-M8-03 : alertes d'autonomie, la rupture prévisionnelle étant comptée depuis la date du jour.
Schedule::job(new EvaluerAlertesAutonomie)->dailyAt('03:00')->name('alertes-autonomie')->withoutOverlapping();
