<?php

namespace App\Services\Administration;

use App\Models\App\Convention;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Dossier\SaisieRefusee;
use App\Services\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Conventions (E23, § 2.4, EF-M0-02, EF-M0-06) : création et prolongation par le technicien ou l'administrateur,
 * résiliation par l'administrateur ; une seule convention active par élevage ; jamais supprimée. Une prolongation
 * au-delà de la date du jour réactive une convention échue. Chaque opération est journalisée.
 */
class ConventionsService
{
    public function __construct(private readonly JournalService $journal) {}

    /**
     * @param  array{date_debut: string, date_fin: string, type?: string|null, reference_externe?: string|null, commentaire?: string|null}  $d
     */
    public function creer(Elevage $elevage, array $d, User $auteur): Convention
    {
        [$debut, $fin] = [Carbon::parse($d['date_debut']), Carbon::parse($d['date_fin'])];
        if ($fin->lt($debut)) {
            throw new SaisieRefusee('La date de fin précède la date de début.');
        }
        if (Convention::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)->where('statut', Convention::STATUT_ACTIVE)->exists()) {
            throw new SaisieRefusee('Une convention est déjà active pour cet élevage : la prolonger plutôt qu\'en créer une autre.');
        }

        $convention = Convention::query()->create([
            'elevage_id' => $elevage->id, 'gds_id' => $elevage->gds_id, 'type' => ($d['type'] ?? null) ?: 'approche_globale',
            'date_debut' => $debut->toDateString(), 'date_fin' => $fin->toDateString(),
            'statut' => $fin->lt(Carbon::today()) ? Convention::STATUT_ECHUE : Convention::STATUT_ACTIVE,
            'reference_externe' => trim((string) ($d['reference_externe'] ?? '')) ?: null, 'commentaire' => trim((string) ($d['commentaire'] ?? '')) ?: null,
        ]);
        $this->journal->ecrire('creation_convention', $auteur->id, 'app.convention:'.$convention->id, ['date_fin' => $fin->toDateString()], $elevage->id);

        return $convention;
    }

    public function prolonger(Convention $convention, string $dateFin, User $auteur): Convention
    {
        if ($convention->statut === Convention::STATUT_RESILIEE) {
            throw new SaisieRefusee('Une convention résiliée ne se prolonge pas : en créer une nouvelle.');
        }
        $fin = Carbon::parse($dateFin);
        if ($fin->lte($convention->date_fin)) {
            throw new SaisieRefusee('La nouvelle date de fin doit être postérieure à l\'actuelle ('.$convention->date_fin->format('d/m/Y').').');
        }
        $reactivee = $convention->statut === Convention::STATUT_ECHUE && ! $fin->lt(Carbon::today());
        if ($reactivee && Convention::query()->withoutGlobalScopes()->where('elevage_id', $convention->elevage_id)->where('statut', Convention::STATUT_ACTIVE)->exists()) {
            throw new SaisieRefusee('Une autre convention est active pour cet élevage.');
        }

        $avant = $convention->date_fin->toDateString();
        $convention->update(['date_fin' => $fin->toDateString(), 'statut' => $reactivee ? Convention::STATUT_ACTIVE : $convention->statut]);
        $this->journal->ecrire('prolongation_convention', $auteur->id, 'app.convention:'.$convention->id, ['avant' => $avant, 'apres' => $fin->toDateString()], $convention->elevage_id);

        return $convention;
    }

    public function resilier(Convention $convention, string $motif, User $auteur): Convention
    {
        if ($convention->statut === Convention::STATUT_RESILIEE) {
            throw new SaisieRefusee('Convention déjà résiliée.');
        }
        if (trim($motif) === '') {
            throw new SaisieRefusee('Indiquer le motif de la résiliation.');
        }
        DB::transaction(function () use ($convention, $motif, $auteur) {
            $convention->update(['statut' => Convention::STATUT_RESILIEE, 'commentaire' => trim(($convention->commentaire ? $convention->commentaire."\n" : '').'Résiliée le '.now()->format('d/m/Y').' : '.trim($motif))]);
            $this->journal->ecrire('resiliation_convention', $auteur->id, 'app.convention:'.$convention->id, ['motif' => trim($motif)], $convention->elevage_id);
        });

        return $convention;
    }
}
