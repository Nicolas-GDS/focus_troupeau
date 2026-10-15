<?php

namespace Database\Seeders;

use App\Models\App\Consentement;
use App\Models\App\Convention;
use App\Models\App\ElevageAcces;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Jeu d'essai J4 — cloisonnement (§ 9.1) : deux GDS, trois élevages, un technicien par GDS, un chargé de mission
 * bi-GDS, un vétérinaire consenti sur un seul élevage. Complété pour couvrir tous les rôles du § 2.2 : un éleveur
 * par GDS, un vétérinaire conseil, un administrateur de GDS, un super-administrateur.
 *
 *   GDS 24 : J4-24A (éleveur 24A, vétérinaire consenti), J4-24B (sans éleveur)
 *   GDS 47 : J4-47  (éleveur 47)
 *
 * Données entièrement synthétiques (aucune donnée réelle, domaine réservé .test), rejouable.
 * Comptes de développement : tous ont un mot de passe local (J4_MOT_DE_PASSE) pour se connecter sans Entra ID ;
 * en production, les agents n'ont pas de mot de passe local. Interdit en production.
 */
class JeuJ4Seeder extends Seeder
{
    /** @var array<string, array{roles: list<string>, gds: list<int>, nom: string, prenom: string}> */
    public const COMPTES = [
        'technicien.24@j4.test' => ['roles' => ['technicien'], 'gds' => [24], 'nom' => 'Technicien', 'prenom' => 'Dordogne'],
        'technicien.47@j4.test' => ['roles' => ['technicien'], 'gds' => [47], 'nom' => 'Technicien', 'prenom' => 'Lot-et-Garonne'],
        'charge.mission@j4.test' => ['roles' => ['technicien', 'admin'], 'gds' => [24, 47], 'nom' => 'Chargé de mission', 'prenom' => 'Bi-GDS'],
        'veterinaire.conseil.24@j4.test' => ['roles' => ['veterinaire_gds'], 'gds' => [24], 'nom' => 'Vétérinaire conseil', 'prenom' => 'Dordogne'],
        'admin.24@j4.test' => ['roles' => ['admin'], 'gds' => [24], 'nom' => 'Administrateur', 'prenom' => 'Dordogne'],
        'admin.global@j4.test' => ['roles' => ['admin_global'], 'gds' => [], 'nom' => 'Super-administrateur', 'prenom' => 'Instance'],
        'eleveur.24a@j4.test' => ['roles' => ['eleveur'], 'gds' => [], 'nom' => 'Éleveur', 'prenom' => '24A'],
        'eleveur.47@j4.test' => ['roles' => ['eleveur'], 'gds' => [], 'nom' => 'Éleveur', 'prenom' => '47'],
        'veterinaire@j4.test' => ['roles' => ['veterinaire'], 'gds' => [], 'nom' => 'Vétérinaire', 'prenom' => 'Cabinet'],
    ];

    /** @var array<string, array{gds: int, raison_sociale: string, eleveur: string|null}> */
    public const ELEVAGES = [
        'J4-24A' => ['gds' => 24, 'raison_sociale' => 'EARL Essai Dordogne A', 'eleveur' => 'eleveur.24a@j4.test'],
        'J4-24B' => ['gds' => 24, 'raison_sociale' => 'GAEC Essai Dordogne B', 'eleveur' => null],
        'J4-47' => ['gds' => 47, 'raison_sociale' => 'EARL Essai Agenais', 'eleveur' => 'eleveur.47@j4.test'],
    ];

    public const ELEVAGE_VETERINAIRE = 'J4-24A';

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Le jeu d\'essai J4 ne doit pas être chargé en production.');
        }

        DB::transaction(function (): void {
            $elevages = $this->elevages();
            $comptes = $this->comptes();

            foreach (self::ELEVAGES as $code => $elevage) {
                if ($elevage['eleveur'] !== null) {
                    $comptes[$elevage['eleveur']]->elevages()->syncWithoutDetaching([
                        $elevages[$code]->id => ['role' => ElevageAcces::ROLE_ELEVEUR],
                    ]);
                }
            }

            $this->consentementVeterinaire($comptes['veterinaire@j4.test'], $elevages[self::ELEVAGE_VETERINAIRE]);
        });
    }

    /**
     * @return array<string, Elevage>
     */
    private function elevages(): array
    {
        $elevages = [];

        foreach (self::ELEVAGES as $code => $donnees) {
            $elevage = Elevage::query()->withoutGlobalScopes()->firstOrCreate(
                ['code_pseudo' => $code],
                ['departement' => (string) $donnees['gds'], 'gds_id' => $donnees['gds']],
            );

            DB::table('prive.elevage_identite')->updateOrInsert(
                ['elevage_id' => $elevage->id],
                ['raison_sociale' => $donnees['raison_sociale'], 'consentement_donnees' => true],
            );

            if (! Convention::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)->exists()) {
                Convention::query()->create([
                    'elevage_id' => $elevage->id,
                    'gds_id' => $donnees['gds'],
                    'date_debut' => now()->startOfYear()->toDateString(),
                    'date_fin' => now()->addYear()->endOfYear()->toDateString(),
                ]);
            }

            $elevages[$code] = $elevage;
        }

        return $elevages;
    }

    /**
     * @return array<string, User>
     */
    private function comptes(): array
    {
        $comptes = [];
        $motDePasse = (string) config('authentification.mot_de_passe_j4');

        foreach (self::COMPTES as $email => $donnees) {
            $user = User::query()->firstOrCreate(['email' => $email], [
                'nom' => $donnees['nom'],
                'prenom' => $donnees['prenom'],
                'password' => $motDePasse,
            ]);
            $user->forceFill(['notice_rgpd_acceptee_le' => $user->notice_rgpd_acceptee_le ?? now()])->save();
            $user->syncRoles($donnees['roles']);
            $user->gds()->sync($donnees['gds']);

            $comptes[$email] = $user;
        }

        return $comptes;
    }

    private function consentementVeterinaire(User $veterinaire, Elevage $elevage): void
    {
        $consentement = Consentement::query()->withoutGlobalScopes()->firstOrCreate(
            ['elevage_id' => $elevage->id, 'type' => Consentement::TYPE_VETERINAIRE, 'veterinaire_user_id' => $veterinaire->id, 'revoque_le' => null],
            ['accorde_le' => now()],
        );

        $veterinaire->elevages()->syncWithoutDetaching([
            $elevage->id => ['role' => ElevageAcces::ROLE_VETERINAIRE, 'consentement_id' => $consentement->id],
        ]);
    }
}
