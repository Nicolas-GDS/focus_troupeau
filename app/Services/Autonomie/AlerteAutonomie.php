<?php

namespace App\Services\Autonomie;

use App\Domain\Referentiels\Referentiels;
use App\Models\App\User;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\Bilan;
use App\Models\Stock\Inventaire;
use App\Services\ElevageCourant;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\TextesTypes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Alerte d'autonomie (EF-M8-03, T-21) : quand la rupture prévisionnelle de l'inventaire courant approche à moins de
 * `alerte.autonomie_jours` jours de la date du jour, ou que la marge de sécurité est déjà entamée, un e-mail part aux
 * éleveurs de l'élevage et au technicien de la dernière visite (à défaut, aux techniciens du GDS de l'élevage). Une
 * seule alerte par franchissement : l'indicateur du bilan est remis à faux quand la rupture repasse au-delà du seuil.
 */
class AlerteAutonomie
{
    public const TYPE = 'alerte_autonomie';

    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly NotificationService $notifications,
        private readonly TextesTypes $textes,
    ) {}

    /** Évalue le bilan mémorisé de l'inventaire ; renvoie vrai si une alerte vient d'être envoyée. */
    public function evaluer(Inventaire $inventaire): bool
    {
        if ($inventaire->statut !== Inventaire::STATUT_COURANT) {
            return false;
        }

        $bilan = Bilan::query()->withoutGlobalScopes()->where('inventaire_id', $inventaire->id)->whereNull('scenario_id')->first();
        if ($bilan === null) {
            return false;
        }

        $franchi = $this->franchi($bilan, $inventaire->elevage_id);
        if (! $franchi) {
            if ($bilan->alerte_envoyee) {
                $bilan->update(['alerte_envoyee' => false]);
            }

            return false;
        }

        if ($bilan->alerte_envoyee) {
            return false;
        }

        $this->envoyer($bilan, $inventaire);
        $bilan->update(['alerte_envoyee' => true]);

        return true;
    }

    /** Évaluation nocturne : la date du jour avance, le seuil peut être franchi sans recalcul. */
    public function evaluerTout(): int
    {
        $envoyees = 0;
        foreach (Inventaire::query()->withoutGlobalScopes()->where('statut', Inventaire::STATUT_COURANT)->orderBy('id')->get() as $inventaire) {
            $envoyees += (int) $this->evaluer($inventaire);
        }

        return $envoyees;
    }

    /** Rupture prévue à moins du seuil (comptée depuis la date du jour), ou marge de sécurité atteinte. */
    public function franchi(Bilan $bilan, string $elevageId): bool
    {
        $aujourdhui = Carbon::today();
        if ($bilan->date_entame_securite !== null && $bilan->date_entame_securite->lte($aujourdhui)) {
            return true;
        }
        if ($bilan->date_rupture === null) {
            return false;
        }

        $seuil = (float) $this->referentiels->parametre('alerte.autonomie_jours', $this->gds($elevageId));

        return $aujourdhui->diffInDays($bilan->date_rupture, false) < $seuil;
    }

    private function envoyer(Bilan $bilan, Inventaire $inventaire): void
    {
        $elevage = Elevage::query()->withoutGlobalScopes()->findOrFail($inventaire->elevage_id);
        $elevage->setAttribute('raison_sociale', DB::table('prive.elevage_identite')->where('elevage_id', $elevage->id)->value('raison_sociale'));
        $format = fn (?Carbon $d) => $d?->format('d/m/Y') ?? '—';
        $variables = [
            'elevage' => ElevageCourant::libelle($elevage),
            'date' => $format($bilan->date_rupture ?? $bilan->date_entame_securite),
            'jours' => (string) max(0, (int) Carbon::today()->diffInDays($bilan->date_rupture ?? Carbon::today(), false)),
            'date_min' => $format($bilan->date_rupture_min), 'date_max' => $format($bilan->date_rupture_max),
            'date_securite' => $format($bilan->date_entame_securite),
        ];
        $objet = $this->textes->texte('notification.autonomie.objet', $variables);
        $lignes = array_map(fn (int $n) => $this->textes->texte("notification.autonomie.ligne{$n}", $variables), [1, 2, 3]);
        $lien = route('autonomie', $elevage->id);
        $reference = 'plan.bilan:'.$bilan->id;

        foreach ($this->eleveurs($elevage) as $eleveur) {
            $this->notifications->envoyer($eleveur, NotificationService::EMAIL, self::TYPE, $objet, $lignes, $lien, $elevage->id, $reference);
        }
        foreach ($this->techniciens($elevage) as $technicien) {
            $this->notifications->envoyer($technicien, NotificationService::EMAIL, self::TYPE,
                $this->textes->texte('notification.copie_technicien.prefixe').$objet, $lignes, $lien, $elevage->id, $reference);
        }
    }

    /** @return Collection<int, User> */
    private function eleveurs(Elevage $elevage): Collection
    {
        return User::query()->whereIn('id', DB::table('app.elevage_acces')->where('elevage_id', $elevage->id)->where('role', 'eleveur')->select('user_id'))
            ->where('actif', true)->orderBy('id')->get();
    }

    /**
     * Technicien de la dernière visite de l'élevage, à défaut les techniciens actifs du GDS de l'élevage.
     *
     * @return Collection<int, User>
     */
    public function techniciens(Elevage $elevage): Collection
    {
        $id = Visite::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)->whereNotNull('technicien_id')
            ->orderByDesc('date_visite')->orderByDesc('id')->value('technicien_id');
        $technicien = $id === null ? null : User::query()->where('actif', true)->where('id', $id)->first();
        if ($technicien !== null) {
            return collect([$technicien]);
        }

        return User::role('technicien')->where('actif', true)->whereHas('gds', fn ($q) => $q->where('id', $elevage->gds_id))->orderBy('id')->get();
    }

    private function gds(string $elevageId): ?int
    {
        $gds = DB::table('fourrage.elevage')->where('id', $elevageId)->value('gds_id');

        return $gds === null ? null : (int) $gds;
    }
}
