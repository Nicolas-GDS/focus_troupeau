<?php

namespace Tests\Support;

use App\Models\App\Consentement;
use App\Models\App\Convention;
use App\Models\App\ElevageAcces;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;

/**
 * Jeu de cloisonnement minimal (inspiré de J4, § 9.1) : deux GDS, trois élevages, un compte par rôle.
 *
 *   GDS 24 : elevage24a (éleveur24a, vétérinaire consenti), elevage24b
 *   GDS 47 : elevage47
 */
final class JeuCloisonnement
{
    public Elevage $elevage24a;

    public Elevage $elevage24b;

    public Elevage $elevage47;

    public User $technicien24;

    public User $technicien47;

    public User $chargeDeMission;

    public User $veterinaireConseil24;

    public User $admin24;

    public User $adminGlobal;

    public User $eleveur24a;

    public User $eleveur47;

    public User $veterinaire;

    public Consentement $consentementVeterinaire;

    public static function creer(): self
    {
        $jeu = new self;

        $jeu->elevage24a = Elevage::factory()->gds(24)->create(['code_pseudo' => 'J4-24A']);
        $jeu->elevage24b = Elevage::factory()->gds(24)->create(['code_pseudo' => 'J4-24B']);
        $jeu->elevage47 = Elevage::factory()->gds(47)->create(['code_pseudo' => 'J4-47']);

        foreach ([$jeu->elevage24a, $jeu->elevage24b, $jeu->elevage47] as $elevage) {
            Convention::factory()->create(['elevage_id' => $elevage->id]);
        }

        $jeu->technicien24 = self::agent('technicien', [24]);
        $jeu->technicien47 = self::agent('technicien', [47]);
        $jeu->chargeDeMission = self::agent(['technicien', 'admin'], [24, 47]);
        $jeu->veterinaireConseil24 = self::agent('veterinaire_gds', [24]);
        $jeu->admin24 = self::agent('admin', [24]);
        $jeu->adminGlobal = self::agent('admin_global', []);

        $jeu->eleveur24a = self::compte('eleveur');
        $jeu->eleveur24a->elevages()->attach($jeu->elevage24a->id, ['role' => ElevageAcces::ROLE_ELEVEUR]);
        $jeu->eleveur47 = self::compte('eleveur');
        $jeu->eleveur47->elevages()->attach($jeu->elevage47->id, ['role' => ElevageAcces::ROLE_ELEVEUR]);

        $jeu->veterinaire = self::compte('veterinaire');
        $jeu->consentementVeterinaire = Consentement::factory()->veterinaire($jeu->veterinaire)
            ->create(['elevage_id' => $jeu->elevage24a->id]);
        $jeu->veterinaire->elevages()->attach($jeu->elevage24a->id, [
            'role' => ElevageAcces::ROLE_VETERINAIRE,
            'consentement_id' => $jeu->consentementVeterinaire->id,
        ]);

        return $jeu;
    }

    /**
     * @param  string|list<string>  $roles
     * @param  list<int>  $gds
     */
    private static function agent(string|array $roles, array $gds): User
    {
        $user = User::factory()->entra()->create();
        $user->assignRole($roles);
        $user->gds()->attach($gds);

        return $user;
    }

    private static function compte(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
