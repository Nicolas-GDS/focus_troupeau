<?php

namespace App\Services\Import;

use App\Domain\Rapprochement\ElevageCandidat;
use App\Domain\Rapprochement\PreRapprochement;
use App\Domain\Rapprochement\PropositionRapprochement;
use App\Models\App\ImportBulletin;
use App\Models\Fourrage\Elevage;
use App\Services\Cloisonnement\RegleConvention;
use Illuminate\Support\Collection;

/**
 * Propositions d'élevages pour les bulletins « à rapprocher » (EF-M9-01) : candidats = élevages du GDS du dépôt sous
 * convention active, dans le périmètre de l'utilisateur (scopes de cloisonnement). Rien n'est écrit : le technicien
 * retient une proposition ou choisit un autre élevage (ImportBulletinService::rapprocher()).
 */
class PreRapprochementService
{
    public function __construct(
        private readonly PreRapprochement $preRapprochement,
        private readonly RegleConvention $regleConvention,
    ) {}

    /**
     * @param  iterable<ImportBulletin>  $imports
     * @return array<int, list<PropositionRapprochement>> propositions par import à rapprocher
     */
    public function propositions(iterable $imports): array
    {
        $aRapprocher = collect($imports)->filter(fn (ImportBulletin $import) => $import->statut === 'a_rapprocher');

        if ($aRapprocher->isEmpty()) {
            return [];
        }

        $candidatsParGds = $this->candidats($aRapprocher->pluck('gds_id')->unique()->values());

        return $aRapprocher->mapWithKeys(fn (ImportBulletin $import) => [$import->id => $this->preRapprochement->proposer(
            (array) ($import->donnees_extraites['entete'] ?? []),
            $candidatsParGds->get($import->gds_id, []),
        )])->all();
    }

    /**
     * @param  Collection<int, int>  $gdsIds
     * @return Collection<int|string, array<int, ElevageCandidat>> élevages candidats par GDS
     */
    private function candidats(Collection $gdsIds): Collection
    {
        return Elevage::query()
            ->leftJoin('prive.elevage_identite as identite', 'identite.elevage_id', '=', 'fourrage.elevage.id')
            ->leftJoin('sig.commune as commune', 'commune.code_insee', '=', 'fourrage.elevage.code_insee_commune')
            ->whereIn('fourrage.elevage.gds_id', $gdsIds)
            ->whereIn('fourrage.elevage.id', $this->regleConvention->conventionsActives()->select('elevage_id'))
            ->select('fourrage.elevage.id', 'fourrage.elevage.gds_id', 'fourrage.elevage.code_pseudo', 'identite.raison_sociale',
                'identite.nom_contact', 'identite.adresse', 'identite.code_postal', 'commune.nom as commune')
            ->toBase()
            ->get()
            ->groupBy('gds_id')
            ->map(fn (Collection $elevages) => $elevages->map(fn (object $e) => new ElevageCandidat(
                (string) $e->id, (string) $e->code_pseudo, $e->raison_sociale, $e->nom_contact, $e->adresse, $e->code_postal, $e->commune,
            ))->values()->all());
    }
}
