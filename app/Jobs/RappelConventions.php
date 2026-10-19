<?php

namespace App\Jobs;

use App\Domain\Referentiels\Referentiels;
use App\Models\App\Convention;
use App\Models\App\Notification;
use App\Models\Fourrage\Elevage;
use App\Services\Autonomie\AlerteAutonomie;
use App\Services\ElevageCourant;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TextesTypes;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

/**
 * Rappel d'échéance des conventions (EF-M0-06) : e-mail au technicien de la dernière visite (à défaut, aux techniciens
 * du GDS) `convention.rappel_jours` avant la date de fin, une seule fois par convention. Tâche quotidienne, hors
 * cloisonnement.
 */
class RappelConventions implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public const TYPE = 'rappel_convention';

    public function handle(Referentiels $referentiels, NotificationService $notifications, TextesTypes $textes, AlerteAutonomie $alertes): void
    {
        $conventions = Convention::query()->withoutGlobalScopes()->where('statut', Convention::STATUT_ACTIVE)->whereDate('date_fin', '>=', today())->orderBy('id')->get();

        foreach ($conventions as $convention) {
            $jours = (int) $referentiels->parametre('convention.rappel_jours', $convention->gds_id);
            $reference = 'app.convention:'.$convention->id;
            if ($convention->date_fin->gt(today()->addDays($jours)) || Notification::query()->where('type', self::TYPE)->where('reference_objet', $reference)->exists()) {
                continue;
            }

            $elevage = Elevage::query()->withoutGlobalScopes()->findOrFail($convention->elevage_id);
            $elevage->setAttribute('raison_sociale', DB::table('prive.elevage_identite')->where('elevage_id', $elevage->id)->value('raison_sociale'));
            $variables = ['elevage' => ElevageCourant::libelle($elevage), 'date' => $convention->date_fin->format('d/m/Y')];
            $objet = $textes->texte('notification.rappel_convention.objet', $variables);
            $lignes = array_map(fn (int $n) => $textes->texte("notification.rappel_convention.ligne{$n}", $variables), [1, 2, 3]);

            foreach ($alertes->techniciens($elevage) as $technicien) {
                $notifications->envoyer($technicien, NotificationService::EMAIL, self::TYPE, $objet, $lignes, route('elevages.fiche', $elevage->id), $elevage->id, $reference);
            }
        }
    }
}
