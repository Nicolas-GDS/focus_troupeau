<?php

namespace App\Services\Administration;

use App\Models\App\Consentement;
use App\Models\App\ElevageAcces;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Cloisonnement\Perimetre;
use App\Services\Dossier\SaisieRefusee;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;

/**
 * Consentements « références » et « vétérinaire » (§ 2.5, EF-M0-07, E17) : accord daté avec son auteur, révocation
 * horodatée (jamais supprimés), effet immédiat ; un consentement vétérinaire ouvre l'accès en lecture au vétérinaire
 * désigné (app.elevage_acces), sa révocation le retire. Chaque changement est journalisé.
 */
class ConsentementsService
{
    public function __construct(private readonly JournalService $journal) {}

    public function accorder(Elevage $elevage, string $type, ?User $veterinaire, User $auteur): Consentement
    {
        if (! in_array($type, [Consentement::TYPE_REFERENCES, Consentement::TYPE_VETERINAIRE], true)) {
            throw new SaisieRefusee('Type de consentement inconnu.');
        }
        if ($type === Consentement::TYPE_VETERINAIRE && ($veterinaire === null || ! $veterinaire->hasRole('veterinaire') || ! $veterinaire->actif)) {
            throw new SaisieRefusee('Choisir un vétérinaire d\'élevage disposant d\'un compte actif.');
        }
        $existant = Consentement::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)->where('type', $type)->whereNull('revoque_le')
            ->when($type === Consentement::TYPE_VETERINAIRE, fn ($q) => $q->where('veterinaire_user_id', $veterinaire?->id))->first();
        if ($existant !== null) {
            throw new SaisieRefusee('Ce consentement est déjà accordé.');
        }

        return DB::transaction(function () use ($elevage, $type, $veterinaire, $auteur) {
            $c = Consentement::query()->create(['elevage_id' => $elevage->id, 'type' => $type, 'veterinaire_user_id' => $veterinaire?->id, 'accorde_le' => now(), 'accorde_par' => $auteur->id]);
            if ($veterinaire !== null) {
                DB::table('app.elevage_acces')->updateOrInsert(['user_id' => $veterinaire->id, 'elevage_id' => $elevage->id],
                    ['role' => ElevageAcces::ROLE_VETERINAIRE, 'consentement_id' => $c->id]);
            }
            app(Perimetre::class)->oublier();
            $this->journal->ecrire('accord_consentement', $auteur->id, 'app.consentement:'.$c->id, ['type' => $type, 'veterinaire' => $veterinaire?->id], $elevage->id);

            return $c;
        });
    }

    public function revoquer(Consentement $consentement, User $auteur): void
    {
        if (! $consentement->estActif()) {
            throw new SaisieRefusee('Ce consentement est déjà révoqué.');
        }
        DB::transaction(function () use ($consentement, $auteur) {
            $consentement->update(['revoque_le' => now(), 'revoque_par' => $auteur->id]);
            if ($consentement->type === Consentement::TYPE_VETERINAIRE) {
                DB::table('app.elevage_acces')->where('elevage_id', $consentement->elevage_id)->where('user_id', $consentement->veterinaire_user_id)
                    ->where('role', ElevageAcces::ROLE_VETERINAIRE)->delete();
            }
            app(Perimetre::class)->oublier();
            $this->journal->ecrire('revocation_consentement', $auteur->id, 'app.consentement:'.$consentement->id, ['type' => $consentement->type], $consentement->elevage_id);
        });
    }
}
