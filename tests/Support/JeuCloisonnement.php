<?php

namespace Tests\Support;

use App\Models\App\Consentement;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use Database\Seeders\JeuJ4Seeder;

/**
 * Accès commode aux objets du jeu d'essai J4 (§ 9.1), chargé par Database\Seeders\JeuJ4Seeder :
 * le jeu n'est décrit qu'à un seul endroit, le seeder, utilisé aussi en développement.
 *
 *   GDS 24 : elevage24a (éleveur24a, vétérinaire consenti), elevage24b
 *   GDS 47 : elevage47 (éleveur47)
 */
final class JeuCloisonnement
{
    /** Profils J4 → adresse e-mail du compte */
    public const PROFILS = [
        'technicien24' => 'technicien.24@j4.test',
        'technicien47' => 'technicien.47@j4.test',
        'chargeDeMission' => 'charge.mission@j4.test',
        'veterinaireConseil24' => 'veterinaire.conseil.24@j4.test',
        'admin24' => 'admin.24@j4.test',
        'adminGlobal' => 'admin.global@j4.test',
        'eleveur24a' => 'eleveur.24a@j4.test',
        'eleveur47' => 'eleveur.47@j4.test',
        'veterinaire' => 'veterinaire@j4.test',
    ];

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
        (new JeuJ4Seeder)->run();

        $jeu = new self;
        $elevages = Elevage::query()->withoutGlobalScopes()->get()->keyBy('code_pseudo');
        $jeu->elevage24a = $elevages['J4-24A'];
        $jeu->elevage24b = $elevages['J4-24B'];
        $jeu->elevage47 = $elevages['J4-47'];

        $comptes = User::query()->whereIn('email', self::PROFILS)->get()->keyBy('email');
        foreach (self::PROFILS as $propriete => $email) {
            $jeu->{$propriete} = $comptes[$email];
        }

        $jeu->consentementVeterinaire = Consentement::query()->withoutGlobalScopes()
            ->where('veterinaire_user_id', $jeu->veterinaire->id)->sole();

        return $jeu;
    }
}
