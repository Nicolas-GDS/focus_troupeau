<?php

namespace App\Policies;

use App\Models\App\User;
use App\Models\Fourrage\Analyse;
use App\Services\Cloisonnement\Perimetre;
use App\Services\Cloisonnement\RegleStatut;
use Illuminate\Support\Facades\DB;

/**
 * Bulletins de laboratoire (§ 2.3, ENF-SEC-04, ENF-SEC-05) : lecture dans le périmètre ; les comptes externes
 * (éleveur, vétérinaire d'élevage) ne lisent qu'un bulletin publié, c'est-à-dire dont une interprétation est publiée
 * (lot L3 : publication de la visite, EF-M1-02). Contrôle (levée d'anomalie, annulation) : technicien.
 */
class AnalysePolicy
{
    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly RegleStatut $regleStatut,
    ) {}

    public function view(User $user, Analyse $analyse): bool
    {
        return $user->can('bulletin.lire')
            && $this->perimetre->couvreElevage($user, $this->elevageDe($analyse))
            && ($this->regleStatut->voitTousLesStatuts($user) || DB::table('fourrage.interpretation')
                ->where('analyse_id', $analyse->id)->where('statut', RegleStatut::PUBLIE)->exists());
    }

    public function leverAnomalie(User $user, Analyse $analyse): bool
    {
        return $this->controle($user, $analyse) && $analyse->statut === Analyse::STATUT_ANOMALIE;
    }

    public function annuler(User $user, Analyse $analyse): bool
    {
        return $this->controle($user, $analyse) && $analyse->statut !== Analyse::STATUT_ANNULE;
    }

    private function controle(User $user, Analyse $analyse): bool
    {
        return $user->can('bulletin.controler') && $this->perimetre->couvreElevage($user, $this->elevageDe($analyse));
    }

    private function elevageDe(Analyse $analyse): string
    {
        return (string) DB::table('fourrage.prelevement')->where('id', $analyse->prelevement_id)->value('elevage_id');
    }
}
