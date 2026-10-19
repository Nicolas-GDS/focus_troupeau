<?php

namespace App\Services\Administration;

use App\Models\App\Consentement;
use App\Models\App\Convention;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Cloisonnement\Perimetre;
use App\Services\Dossier\SaisieRefusee;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;

/**
 * Anonymisation sur demande RGPD (§ 2.4, § 3.13 « Précisions du lot L7 ») : action de l'administrateur, confirmée et
 * irréversible. L'identité (prive.elevage_identite) est vidée, la raison sociale remplacée par le code pseudonyme ;
 * le siège géocodé est effacé ; les comptes éleveurs rattachés à ce seul élevage sont anonymisés et désactivés, les
 * autres simplement détachés ; la convention active est résiliée et le partage vétérinaire révoqué. Les données
 * pseudonymisées restent (références agrégées si le consentement « références » était actif). Journalisé.
 */
class AnonymisationService
{
    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly ConventionsService $conventions,
        private readonly ConsentementsService $consentements,
        private readonly JournalService $journal,
    ) {}

    public function anonymiser(Elevage $elevage, string $motif, User $admin): void
    {
        if (! $admin->can('elevage.anonymiser') || ! $this->perimetre->couvreElevage($admin, $elevage)) {
            throw new SaisieRefusee('Anonymisation réservée à l\'administrateur du GDS de l\'élevage.');
        }
        if (trim($motif) === '') {
            throw new SaisieRefusee('Indiquer la demande qui motive l\'anonymisation.');
        }

        DB::transaction(function () use ($elevage, $motif, $admin) {
            DB::table('prive.elevage_identite')->where('elevage_id', $elevage->id)->update([
                'raison_sociale' => $elevage->code_pseudo, 'num_cheptel_ede' => null, 'siret' => null, 'nom_contact' => null, 'adresse' => null, 'code_postal' => null,
                'telephone' => null, 'courriel' => null, 'num_client_labo' => null, 'consentement_partage_veto' => false, 'maj_le' => now(),
            ]);
            DB::table('fourrage.elevage')->where('id', $elevage->id)->update(['geom_siege' => null, 'commentaire' => null, 'tiers_facture' => null]);

            $comptes = DB::table('app.elevage_acces')->where('elevage_id', $elevage->id)->where('role', 'eleveur')->pluck('user_id');
            foreach ($comptes as $userId) {
                $autres = DB::table('app.elevage_acces')->where('user_id', $userId)->where('elevage_id', '!=', $elevage->id)->exists();
                if (! $autres) {
                    User::query()->whereKey($userId)->update(['email' => 'anonyme-'.$userId.'@anonymise.invalid', 'nom' => 'Compte anonymisé', 'prenom' => null,
                        'telephone' => null, 'password' => null, 'entra_oid' => null, 'remember_token' => null, 'actif' => false]);
                }
            }
            DB::table('app.elevage_acces')->where('elevage_id', $elevage->id)->where('role', 'eleveur')->delete();

            foreach (Convention::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)->where('statut', '!=', Convention::STATUT_RESILIEE)->get() as $c) {
                $this->conventions->resilier($c, 'anonymisation sur demande RGPD', $admin);
            }
            foreach (Consentement::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)->where('type', Consentement::TYPE_VETERINAIRE)->whereNull('revoque_le')->get() as $c) {
                $this->consentements->revoquer($c, $admin);
            }
            $this->journal->ecrire('anonymisation_elevage', $admin->id, 'fourrage.elevage:'.$elevage->id, ['motif' => trim($motif), 'comptes' => $comptes->count()], $elevage->id);
        });
    }
}
