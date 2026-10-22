<?php

use App\Models\App\User;
use Database\Seeders\JeuJ4Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteLaravel;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Tests\Support\JeuCloisonnement;

/*
|--------------------------------------------------------------------------
| T-40 — Cloisonnement : J4, chaque route, chaque rôle (§ 6.3, § 9.2)
|--------------------------------------------------------------------------
|
| Registre des routes : chaque route de l'application y déclare qui peut l'atteindre. Une route absente du
| registre fait échouer le test (CLAUDE.md : tests de cloisonnement obligatoires sur chaque route ajoutée).
|
|   public      : pages de connexion (E01) — visiteurs ; un compte connecté est renvoyé vers son espace
|   sante       : point de santé /up (supervision)
|   connecte    : tout compte connecté
|   roles       : compte connecté ayant l'un des rôles listés (sinon 403)
|   elevage     : action portant sur un élevage passé dans la requête — 404 hors périmètre
|   dossier     : écran /elevages/{elevage}/… (E04 à E12) — rôles de ROLES_DOSSIER (ou « roles »), 404 hors périmètre ;
|                 appelé ici pour chaque élevage J4 ; contenu publié / non publié testé dans
|                 tests/Feature/Dossier/LectureDossierTest.php
|   objet       : route portant sur un objet (signée + policy), testée avec ses objets dans le fichier de l'écran
|
*/

uses(RefreshDatabase::class);

/** @var array<string, array{acces: string, roles?: list<string>, statut?: int}> */
const REGISTRE_ROUTES = [
    // E01 — connexion, lien, code, activation, mot de passe oublié, Entra ID
    'GET connexion' => ['acces' => 'public'],
    'POST connexion' => ['acces' => 'public'],
    'GET connexion/code' => ['acces' => 'public'],
    'POST connexion/code' => ['acces' => 'public'],
    'POST connexion/code/renvoyer' => ['acces' => 'public'],
    'GET connexion/lien' => ['acces' => 'public'],
    'POST connexion/lien' => ['acces' => 'public'],
    'GET connexion/lien/{jeton}' => ['acces' => 'public'],
    'POST connexion/lien/{jeton}' => ['acces' => 'public'],
    'GET activation/{jeton}' => ['acces' => 'public'],
    'POST activation/{jeton}' => ['acces' => 'public'],
    'GET mot-de-passe/oublie' => ['acces' => 'public'],
    'POST mot-de-passe/oublie' => ['acces' => 'public'],
    'GET mot-de-passe/reinitialisation/{token}' => ['acces' => 'public'],
    'POST mot-de-passe/reinitialisation' => ['acces' => 'public'],
    'GET auth/entra' => ['acces' => 'public'],
    'GET auth/entra/retour' => ['acces' => 'public'],

    'GET up' => ['acces' => 'sante'],

    // Espace connecté
    'GET /' => ['acces' => 'connecte', 'statut' => 302],
    'GET notice-rgpd' => ['acces' => 'connecte'],
    'POST notice-rgpd' => ['acces' => 'connecte', 'statut' => 302],
    'POST deconnexion' => ['acces' => 'connecte', 'statut' => 302],
    'PUT compte/mot-de-passe' => ['acces' => 'connecte', 'statut' => 302],
    'GET compte/sans-role' => ['acces' => 'connecte'],
    'POST elevage-courant' => ['acces' => 'elevage'],
    'GET accueil' => ['acces' => 'roles', 'roles' => ['eleveur', 'veterinaire']],
    'GET compte' => ['acces' => 'roles', 'roles' => ['eleveur', 'veterinaire']],
    'GET compte/consentements' => ['acces' => 'roles', 'roles' => ['eleveur', 'veterinaire']],
    'GET travail' => ['acces' => 'roles', 'roles' => ['technicien', 'veterinaire_gds']],
    'GET admin/gds' => ['acces' => 'roles', 'roles' => ['admin', 'admin_global']],
    'GET admin/utilisateurs' => ['acces' => 'roles', 'roles' => ['admin', 'admin_global']],
    'GET admin/conventions' => ['acces' => 'roles', 'roles' => ['admin', 'admin_global']],
    // Lot L7 — journal d'audit (E23) ; écrans testés dans tests/Feature/Administration/*
    'GET admin/journal' => ['acces' => 'roles', 'roles' => ['admin', 'admin_global']],
    // Lot L8 — exploitation ; testé dans tests/Feature/Exploitation/*
    'GET admin/exploitation' => ['acces' => 'roles', 'roles' => ['admin', 'admin_global']],
    'GET elevages' => ['acces' => 'roles', 'roles' => ['technicien', 'admin', 'admin_global']],
    'GET elevages/{elevage}/fiche' => ['acces' => 'objet'],
    'GET import/sagema' => ['acces' => 'roles', 'roles' => ['technicien']],
    // E25 — pilotage ; testé dans tests/Feature/Administration/PilotageTest.php
    'GET admin/pilotage' => ['acces' => 'roles', 'roles' => ['technicien', 'admin', 'admin_global']],
    'GET admin/pilotage/indicateurs.csv' => ['acces' => 'roles', 'roles' => ['technicien', 'admin', 'admin_global']],
    'GET admin/pilotage/references.csv' => ['acces' => 'objet'],
    // exports de l'élevage ; testés dans tests/Feature/Administration/ExportsTest.php
    'GET elevages/{elevage}/export/rationnement.csv' => ['acces' => 'objet'],
    'GET elevages/{elevage}/export/donnees.zip' => ['acces' => 'objet'],

    // Lot L2 — E19 et PDF des bulletins (objets testés dans tests/Feature/Import/EcranImportBulletinsTest.php)
    'GET import/bulletins' => ['acces' => 'roles', 'roles' => ['technicien']],
    'GET import/bulletins/{import}/pdf' => ['acces' => 'objet'],
    'GET bulletins/{analyse}/pdf' => ['acces' => 'objet'],

    // Lot L2 — E20 (objet testé dans tests/Feature/Interpretation/EcranInterpretationTest.php)
    'GET analyses/{analyse}/interpretation' => ['acces' => 'objet'],
    // Lot L2 — E24 (référentiels versionnés) : appelée ici avec une table fictive, la route répond 404 aux
    // administrateurs et 403 aux autres rôles ; accès réel (seuils, plages) testé dans
    // tests/Feature/Referentiels/EcranReferentielsTest.php
    // Lot L3 — visites (objets testés dans tests/Feature/Dossier/EcranSaisieVisiteTest.php)
    'GET visites' => ['acces' => 'roles', 'roles' => ['eleveur', 'technicien', 'veterinaire_gds', 'veterinaire', 'admin', 'admin_global']],
    'GET visites/{visite}/saisie' => ['acces' => 'objet'],
    'GET visites/{visite}/interpretation' => ['acces' => 'objet'],
    'GET visites/{visite}/fiches/{version}/pdf' => ['acces' => 'objet'],
    // Note de liaison (objets testés dans tests/Feature/Dossier/NoteLiaisonTest.php)
    'GET notes-liaison/{note}' => ['acces' => 'objet'],
    'GET notes-liaison/{note}/pdf' => ['acces' => 'objet'],
    'GET admin/referentiels/{table}' => ['acces' => 'roles', 'roles' => ['admin', 'admin_global'], 'statut' => 404],
    // Lot L3, étape 7 — dossier en lecture (E03 à E11, annexe 10) ; objets testés dans tests/Feature/Dossier/LectureDossierTest.php
    'GET elevages/{elevage}/visites/{visite}' => ['acces' => 'objet'],
    'GET elevages/{elevage}/fourrages' => ['acces' => 'dossier'],
    'GET elevages/{elevage}/fourrages/{prelevement}' => ['acces' => 'objet'],
    'GET elevages/{elevage}/mineraux-matrice' => ['acces' => 'dossier'],
    'GET elevages/{elevage}/eau' => ['acces' => 'dossier'],
    'GET elevages/{elevage}/animaux' => ['acces' => 'dossier'],
    'GET elevages/{elevage}/aliments' => ['acces' => 'dossier'],
    'GET elevages/{elevage}/sanitaire' => ['acces' => 'dossier'],
    'GET elevages/{elevage}/historique' => ['acces' => 'dossier'],
    'GET elevages/{elevage}/plan-action' => ['acces' => 'dossier'],
    // Lot L5 — E12 (annexe 10 : éleveur, technicien) ; objets testés dans tests/Feature/Stock/EcranInventaireTest.php
    'GET elevages/{elevage}/stocks' => ['acces' => 'dossier', 'roles' => ['eleveur', 'technicien']],
    'GET elevages/{elevage}/stocks/{inventaire}' => ['acces' => 'objet'],
    'GET elevages/{elevage}/troupeau' => ['acces' => 'dossier', 'roles' => ['eleveur', 'technicien']],
    // Lot L6 — E14 (annexe 10 : éleveur, technicien) ; écran testé dans tests/Feature/Autonomie/EcranBilanTest.php
    'GET elevages/{elevage}/autonomie' => ['acces' => 'dossier', 'roles' => ['eleveur', 'technicien']],
    // E15 ; scénarios testés dans tests/Feature/Autonomie/ApprovisionnementTest.php
    'GET elevages/{elevage}/approvisionnement' => ['acces' => 'dossier', 'roles' => ['eleveur', 'technicien']],
    'GET elevages/{elevage}/scenarios/{scenario}' => ['acces' => 'objet'],
    // E16 ; écran testé dans tests/Feature/Autonomie/ParametresElevageTest.php
    'GET elevages/{elevage}/parametres' => ['acces' => 'dossier', 'roles' => ['eleveur', 'technicien']],
    // PDF du bilan et du scénario (EF-M6-06) ; testés dans tests/Feature/Autonomie/PdfAutonomieTest.php
    'GET elevages/{elevage}/autonomie/pdf' => ['acces' => 'objet'],
    'GET elevages/{elevage}/scenarios/{scenario}/pdf' => ['acces' => 'objet'],
    // EF-M9-03, § 6.4 — données AGDS lues dans SAGEMA (agds.lire : agents GDS) ; écran testé dans tests/Feature/Agds/DonneesAgdsTest.php
    'GET elevages/{elevage}/agds' => ['acces' => 'dossier', 'roles' => ['technicien', 'veterinaire_gds', 'admin', 'admin_global']],
];

/** Rôles des volets du dossier (annexe 10 : éleveur, technicien, vétérinaire ; vétérinaire conseil pour E07, E11, E21). */
const ROLES_DOSSIER = ['eleveur', 'veterinaire', 'technicien', 'veterinaire_gds'];

/**
 * Routes de cadriciel hors registre : leur sécurité relève de chaque composant qui les utilise.
 * Livewire : les actions des composants sont autorisées côté serveur par les policies (ENF-SEC-04).
 */
const PREFIXES_HORS_REGISTRE = ['livewire/'];

/** Périmètre attendu de chaque profil J4 (élevages par code pseudonyme, § 2.2). */
const PERIMETRES_J4 = [
    'technicien24' => ['J4-24A', 'J4-24B'],
    'technicien47' => ['J4-47'],
    'chargeDeMission' => ['J4-24A', 'J4-24B', 'J4-47'],
    'veterinaireConseil24' => ['J4-24A', 'J4-24B'],
    'admin24' => ['J4-24A', 'J4-24B'],
    'adminGlobal' => ['J4-24A', 'J4-24B', 'J4-47'],
    'eleveur24a' => ['J4-24A'],
    'eleveur47' => ['J4-47'],
    'veterinaire' => ['J4-24A'],
];

/**
 * @return array<string, RouteLaravel>
 */
function routesApplication(): array
{
    $routes = [];

    foreach (Route::getRoutes()->getRoutes() as $route) {
        if (collect(PREFIXES_HORS_REGISTRE)->contains(fn (string $prefixe) => str_starts_with($route->uri(), $prefixe))) {
            continue;
        }

        foreach (array_diff($route->methods(), ['HEAD']) as $methode) {
            $routes[$methode.' '.$route->uri()] = $route;
        }
    }

    return $routes;
}

function urlDe(string $cle): string
{
    $uri = explode(' ', $cle, 2)[1];

    return '/'.ltrim((string) preg_replace('/\{[^}]+\}/', 'jeton-quelconque', $uri), '/');
}

function appeler(string $cle): TestResponse
{
    [$methode] = explode(' ', $cle, 2);

    return test()->call($methode, urlDe($cle));
}

it('déclare chaque route de l\'application dans le registre de cloisonnement', function () {
    $nonDeclarees = array_values(array_diff(array_keys(routesApplication()), array_keys(REGISTRE_ROUTES)));
    $disparues = array_values(array_diff(array_keys(REGISTRE_ROUTES), array_keys(routesApplication())));

    expect($nonDeclarees)->toBe([], 'Routes à déclarer dans REGISTRE_ROUTES (avec leur test de cloisonnement)')
        ->and($disparues)->toBe([], 'Routes du registre qui n\'existent plus');
});

it('protège chaque route de l\'espace : un visiteur est renvoyé vers la connexion', function (string $cle) {
    appeler($cle)->assertRedirect(route('login'));
})->with(fn () => array_keys(array_filter(REGISTRE_ROUTES, fn ($r) => in_array($r['acces'], ['connecte', 'roles', 'elevage', 'dossier', 'objet'], true))));

it('renvoie un compte connecté vers son espace depuis les pages de connexion', function (string $cle) {
    $jeu = JeuCloisonnement::creer();

    $this->actingAs($jeu->eleveur24a);
    appeler($cle)->assertRedirect('/');
})->with(fn () => array_keys(array_filter(REGISTRE_ROUTES, fn ($r) => $r['acces'] === 'public')));

it('ouvre chaque écran aux seuls rôles prévus, pour chaque profil J4', function (string $profil) {
    $jeu = JeuCloisonnement::creer();
    $user = $jeu->{$profil};
    $this->actingAs($user);

    foreach (REGISTRE_ROUTES as $cle => $regle) {
        if (! in_array($regle['acces'], ['connecte', 'roles'], true) || str_starts_with($cle, 'POST deconnexion')) {
            continue;
        }

        $autorise = $regle['acces'] === 'connecte' || $user->hasAnyRole($regle['roles'] ?? []);
        $attendu = $autorise ? ($regle['statut'] ?? 200) : 403;

        expect(appeler($cle)->status())->toBe($attendu, "{$profil} → {$cle}");
    }
})->with(array_keys(PERIMETRES_J4));

it('n\'affiche aucun élevage hors du périmètre, sur aucune page (aucun accès transversal entre GDS ni entre élevages)', function (string $profil) {
    $jeu = JeuCloisonnement::creer();
    $user = $jeu->{$profil};
    $this->actingAs($user);
    $horsPerimetre = collect(JeuJ4Seeder::ELEVAGES)->except(PERIMETRES_J4[$profil]);

    foreach (REGISTRE_ROUTES as $cle => $regle) {
        $accessible = str_starts_with($cle, 'GET ') && ($regle['acces'] === 'connecte'
            || ($regle['acces'] === 'roles' && $user->hasAnyRole($regle['roles'] ?? [])));

        if (! $accessible || ($regle['statut'] ?? 200) !== 200) {
            continue;
        }

        $page = appeler($cle)->assertOk()->getContent();

        // str_contains explicite : toContain() de Pest prend plusieurs aiguilles, pas un message.
        foreach ($horsPerimetre as $code => $elevage) {
            expect(str_contains($page, $code))->toBeFalse("{$profil} → {$cle} affiche {$code}")
                ->and(str_contains($page, $elevage['raison_sociale']))->toBeFalse("{$profil} → {$cle} affiche {$elevage['raison_sociale']}");
        }
    }
})->with(array_keys(PERIMETRES_J4));

it('ouvre chaque volet du dossier aux seuls rôles prévus et à aucun élevage hors périmètre (E04 à E11)', function (string $profil) {
    $jeu = JeuCloisonnement::creer();
    $user = $jeu->{$profil};
    $this->actingAs($user);
    $elevages = ['J4-24A' => $jeu->elevage24a, 'J4-24B' => $jeu->elevage24b, 'J4-47' => $jeu->elevage47];
    $horsPerimetre = collect(JeuJ4Seeder::ELEVAGES)->except(PERIMETRES_J4[$profil]);

    foreach (array_filter(REGISTRE_ROUTES, fn ($r) => $r['acces'] === 'dossier') as $cle => $regle) {
        foreach ($elevages as $code => $elevage) {
            $url = '/'.str_replace('{elevage}', $elevage->id, explode(' ', $cle, 2)[1]);
            $dansPerimetre = in_array($code, PERIMETRES_J4[$profil], true);
            $attendu = ! $dansPerimetre ? 404 : ($user->hasAnyRole($regle['roles'] ?? ROLES_DOSSIER) ? 200 : 403);
            $reponse = $this->get($url);

            expect($reponse->status())->toBe($attendu, "{$profil} → {$cle} ({$code})");

            if ($attendu === 200) {
                foreach ($horsPerimetre as $autre => $donnees) {
                    expect(str_contains((string) $reponse->getContent(), $autre))->toBeFalse("{$profil} → {$cle} affiche {$autre}")
                        ->and(str_contains((string) $reponse->getContent(), $donnees['raison_sociale']))->toBeFalse("{$profil} → {$cle} affiche {$donnees['raison_sociale']}");
                }
            }
        }
    }
})->with(array_keys(PERIMETRES_J4));

it('ne laisse choisir que les élevages du périmètre (404 sinon)', function (string $profil) {
    $jeu = JeuCloisonnement::creer();
    $this->actingAs($jeu->{$profil});

    foreach (['J4-24A' => $jeu->elevage24a, 'J4-24B' => $jeu->elevage24b, 'J4-47' => $jeu->elevage47] as $code => $elevage) {
        $attendu = in_array($code, PERIMETRES_J4[$profil], true) ? 302 : 404;

        expect($this->post(route('elevage-courant'), ['elevage' => $elevage->id])->status())
            ->toBe($attendu, "{$profil} → {$code}");
    }
})->with(array_keys(PERIMETRES_J4));

it('limite le vétérinaire à l\'élevage consenti et rend la révocation effective', function () {
    $jeu = JeuCloisonnement::creer();
    $this->actingAs($jeu->veterinaire);

    $this->get(route('accueil'))->assertSee('EARL Essai Dordogne A');
    $this->post(route('elevage-courant'), ['elevage' => $jeu->elevage24a->id])->assertRedirect();

    $jeu->consentementVeterinaire->update(['revoque_le' => now()]);

    $this->get(route('accueil'))->assertDontSee('EARL Essai Dordogne A')->assertDontSee('J4-24A');
    $this->post(route('elevage-courant'), ['elevage' => $jeu->elevage24a->id])->assertNotFound();
});

it('couvre tous les rôles du § 2.2 dans les profils J4', function () {
    $jeu = JeuCloisonnement::creer();

    $roles = collect(array_keys(PERIMETRES_J4))
        ->flatMap(fn (string $profil) => $jeu->{$profil}->getRoleNames())
        ->unique()->sort()->values()->all();

    expect($roles)->toBe(['admin', 'admin_global', 'eleveur', 'technicien', 'veterinaire', 'veterinaire_gds']);
});

it('charge J4 conformément au § 9.1 et peut être rejoué', function () {
    (new JeuJ4Seeder)->run();
    (new JeuJ4Seeder)->run();

    expect(DB::table('fourrage.elevage')->count())->toBe(3)
        ->and(DB::table('fourrage.elevage')->distinct()->count('gds_id'))->toBe(2)
        ->and(User::role('technicien')->count())->toBe(3)
        ->and(User::query()->whereHas('gds', fn ($q) => $q->where('id', 24))->whereHas('gds', fn ($q) => $q->where('id', 47))->role('technicien')->count())->toBe(1)
        ->and(DB::table('app.consentement')->where('type', 'veterinaire')->whereNull('revoque_le')->count())->toBe(1)
        ->and(User::query()->count())->toBe(count(JeuJ4Seeder::COMPTES));
});

it('refuse de charger J4 en production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => (new JeuJ4Seeder)->run())->toThrow(RuntimeException::class);
});
