<?php

namespace App\Services\Autonomie;

use App\Domain\Referentiels\Referentiels;
use App\Models\Stock\Inventaire;
use App\Models\Stock\PeriodePhase;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Phases de la période de référence (RC-10, voie UGB) : hivernage à pleine ration (coefficient 1), transition de mise
 * à l'herbe et complémentation estivale au coefficient par défaut du référentiel (le second se saisit). Les phases
 * restent dans la période de l'inventaire et ne se chevauchent pas ; l'application ne tranche pas la durée.
 */
class PeriodesService
{
    /** Coefficient de pleine ration de l'hivernage (RC-10 : définition, et non valeur de référence). */
    private const PLEINE_RATION = 1.0;

    public function __construct(private readonly Referentiels $referentiels) {}

    public function coefficientDefaut(string $libelle, ?int $gdsId): float
    {
        return match ($libelle) {
            'transition' => (float) $this->referentiels->parametre('periode.coeff_transition', $gdsId),
            'complementation_estivale' => (float) $this->referentiels->parametre('periode.coeff_estivale', $gdsId),
            default => self::PLEINE_RATION,
        };
    }

    public function ajouter(Inventaire $inventaire, string $libelle, string $debut, string $fin, ?float $coefficient = null): PeriodePhase
    {
        [$d, $f] = [Carbon::parse($debut)->startOfDay(), Carbon::parse($fin)->startOfDay()];
        if ($f->lt($d)) {
            throw new SaisieRefusee('La fin de la phase précède son début.');
        }
        if ($d->lt($inventaire->date_debut) || $f->gt($inventaire->date_fin)) {
            throw new SaisieRefusee('La phase doit rester dans la période de l\'inventaire (du '.$inventaire->date_debut->format('d/m/Y').' au '.$inventaire->date_fin->format('d/m/Y').').');
        }
        $chevauche = PeriodePhase::query()->withoutGlobalScopes()->where('inventaire_id', $inventaire->id)
            ->whereDate('date_debut', '<=', $f)->whereDate('date_fin', '>=', $d)->exists();
        if ($chevauche) {
            throw new SaisieRefusee('Cette phase chevauche une phase déjà décrite.');
        }

        $gds = DB::table('fourrage.elevage')->where('id', $inventaire->elevage_id)->value('gds_id');
        $coefficient = $libelle === 'complementation_estivale' && $coefficient !== null ? $coefficient : $this->coefficientDefaut($libelle, $gds === null ? null : (int) $gds);

        return PeriodePhase::query()->create([
            'inventaire_id' => $inventaire->id, 'libelle' => $libelle, 'date_debut' => $d->toDateString(), 'date_fin' => $f->toDateString(), 'coefficient' => $coefficient,
        ]);
    }

    public function supprimer(PeriodePhase $phase): void
    {
        $phase->delete();
    }
}
