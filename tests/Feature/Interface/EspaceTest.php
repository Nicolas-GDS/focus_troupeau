<?php

use App\Models\App\Gds;
use App\Models\App\Journal;
use App\Models\App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JeuCloisonnement;

// Espace connecté du lot L1 : aiguillage par rôle (annexe 10), notice RGPD (ENF-RGPD-02),
// en-tête co-brandé (§ 8.1), sélecteur d'élevage (EF-M0-08), jetons de marque, version (ENF-EXP-03).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
});

it('oriente chaque compte vers son écran d\'arrivée', function (string $compte, string $route) {
    $this->actingAs($this->jeu->{$compte})->get('/')->assertRedirect(route($route));
})->with([
    'éleveur' => ['eleveur24a', 'accueil'],
    'vétérinaire d\'élevage' => ['veterinaire', 'accueil'],
    'technicien' => ['technicien24', 'travail'],
    'chargé de mission' => ['chargeDeMission', 'travail'],
    'vétérinaire conseil' => ['veterinaireConseil24', 'travail'],
    'administrateur' => ['admin24', 'admin.utilisateurs'],
    'super-administrateur' => ['adminGlobal', 'admin.utilisateurs'],
]);

it('oriente un compte sans rôle vers une page d\'attente', function () {
    $user = User::factory()->create(['notice_rgpd_acceptee_le' => now()]);

    $this->actingAs($user)->get('/')->assertRedirect(route('compte-sans-role'));
    $this->get(route('compte-sans-role'))->assertOk()->assertSee('pas encore de rôle');
});

it('affiche la notice RGPD à la première connexion et ferme l\'espace tant qu\'elle n\'est pas acceptée', function () {
    $eleveur = $this->jeu->eleveur24a;
    $eleveur->forceFill(['notice_rgpd_acceptee_le' => null])->save();

    $this->actingAs($eleveur)->get(route('accueil'))->assertRedirect(route('notice-rgpd'));
    $this->get(route('notice-rgpd'))->assertOk()
        ->assertSee('Pourquoi nous traitons vos données')
        ->assertSee('Texte provisoire');

    $this->post(route('notice-rgpd.accepter'))->assertRedirect(route('accueil'));

    expect($eleveur->fresh()?->notice_rgpd_acceptee_le)->not->toBeNull()
        ->and(Journal::query()->where('action', 'notice_rgpd_acceptee')->where('user_id', $eleveur->id)->exists())->toBeTrue();
    $this->get(route('accueil'))->assertOk();
});

it('ne réenregistre pas une acceptation déjà donnée', function () {
    $eleveur = $this->jeu->eleveur24a;
    $premiere = $eleveur->fresh()?->notice_rgpd_acceptee_le;

    $this->travel(1)->day();
    $this->actingAs($eleveur)->post(route('notice-rgpd.accepter'));

    expect($eleveur->fresh()?->notice_rgpd_acceptee_le?->equalTo($premiere))->toBeTrue()
        ->and(Journal::query()->where('action', 'notice_rgpd_acceptee')->count())->toBe(0);
});

it('réserve chaque écran aux rôles prévus par l\'annexe 10', function (string $compte, string $route, int $statut) {
    $this->actingAs($this->jeu->{$compte})->get(route($route))->assertStatus($statut);
})->with([
    ['eleveur24a', 'accueil', 200],
    ['eleveur24a', 'compte', 200],
    ['eleveur24a', 'compte.consentements', 200],
    ['eleveur24a', 'travail', 403],
    ['eleveur24a', 'admin.utilisateurs', 403],
    ['veterinaire', 'accueil', 200],
    ['veterinaire', 'compte.consentements', 200],
    ['veterinaire', 'travail', 403],
    ['technicien24', 'travail', 200],
    ['technicien24', 'accueil', 403],
    ['technicien24', 'compte', 403],
    ['technicien24', 'admin.conventions', 403],
    ['veterinaireConseil24', 'travail', 200],
    ['veterinaireConseil24', 'admin.gds', 403],
    ['admin24', 'admin.utilisateurs', 200],
    ['admin24', 'admin.conventions', 200],
    ['admin24', 'admin.gds', 200],
    ['admin24', 'travail', 403],
    ['chargeDeMission', 'travail', 200],
    ['chargeDeMission', 'admin.utilisateurs', 200],
    ['adminGlobal', 'admin.gds', 200],
]);

it('co-brande l\'en-tête avec le GDS de l\'élevage de l\'éleveur (§ 8.1)', function () {
    $this->actingAs($this->jeu->eleveur24a)->get(route('accueil'))
        ->assertSee('data-gds="24"', false)
        ->assertSee('GDS Dordogne');

    $this->actingAs($this->jeu->eleveur47)->get(route('accueil'))
        ->assertSee('data-gds="47"', false)
        ->assertSee('GDS Lot-et-Garonne')
        ->assertDontSee('GDS Dordogne');
});

it('affiche le logo officiel du GDS, avec son nom en texte alternatif', function () {
    $this->actingAs($this->jeu->eleveur24a)->get(route('accueil'))
        ->assertSee('img/logos/gds-24.jpg', false)
        ->assertSee('alt="GDS Dordogne"', false);

    $this->actingAs($this->jeu->eleveur47)->get(route('accueil'))
        ->assertSee('img/logos/gds-47.jpg', false)
        ->assertSee('alt="GDS Lot-et-Garonne"', false)
        ->assertDontSee('gds-24.jpg');
});

it('affiche le logo GDS France réduit en pied de page, y compris sur la page de connexion (§ 8.1)', function () {
    $this->actingAs($this->jeu->technicien24)->get(route('travail'))
        ->assertSee('img/logos/gds-france.png', false)
        ->assertSee('alt="Réseau GDS France"', false);

    $this->post(route('logout'));
    $this->get(route('login'))->assertSee('img/logos/gds-france.png', false);
});

it('affiche le logotype co-brandé du GDS de l\'élevage, et le logotype général avant connexion (kit graphique v1)', function () {
    $this->actingAs($this->jeu->eleveur47)->get(route('accueil'))
        ->assertSee('img/marque/focus-troupeau-horizontal-gds47.svg', false)
        ->assertDontSee('focus-troupeau-horizontal-gds24.svg');

    $this->actingAs($this->jeu->eleveur24a)->get(route('accueil'))
        ->assertSee('img/marque/focus-troupeau-horizontal-gds24.svg', false);

    $this->post(route('logout'));
    $this->get(route('login'))
        ->assertSee('img/marque/focus-troupeau-horizontal.svg', false)
        ->assertSee('favicon.svg', false)
        ->assertSee('site.webmanifest', false);
});

it('sert les polices, le signe et les icônes du kit depuis l\'application', function (string $fichier) {
    expect(file_exists(public_path($fichier)))->toBeTrue();
})->with([
    'fonts/Outfit.woff2', 'fonts/SourceSerif4.woff2', 'fonts/SourceSerif4-Italic.woff2', 'fonts/Outfit-OFL.txt',
    'img/marque/signe-focus.svg', 'img/icones/focus-troupeau-icons.svg', 'favicon.ico', 'site.webmanifest',
]);

it('remplace par un cartouche le logo d\'un GDS qui n\'a pas encore de fichier', function () {
    $gds = new Gds(['id' => 33, 'code' => '33', 'nom' => 'GDS Gironde', 'couleur_locale' => '#000000']);

    $html = Blade::render('<x-logo-gds :gds="$gds" />', ['gds' => $gds]);

    expect($html)->toContain('GDS Gironde')->not->toContain('<img');
});

it('change l\'en-tête avec l\'élevage choisi par un agent rattaché aux deux GDS', function () {
    $this->actingAs($this->jeu->chargeDeMission);

    $this->post(route('elevage-courant'), ['elevage' => $this->jeu->elevage24a->id]);
    $this->get(route('travail'))->assertSee('data-gds="24"', false)->assertSee('GDS Dordogne');

    $this->from(route('travail'))
        ->post(route('elevage-courant'), ['elevage' => $this->jeu->elevage47->id])
        ->assertRedirect(route('travail'));

    $this->get(route('travail'))->assertSee('data-gds="47"', false)->assertSee('GDS Lot-et-Garonne');
});

it('ne propose dans le sélecteur que les élevages du périmètre (EF-M0-08)', function () {
    $this->actingAs($this->jeu->technicien24)->get(route('travail'))
        ->assertSee('J4-24A')
        ->assertSee('J4-24B')
        ->assertDontSee('J4-47');
});

it('refuse de choisir un élevage hors périmètre', function () {
    $this->actingAs($this->jeu->technicien24)
        ->post(route('elevage-courant'), ['elevage' => $this->jeu->elevage47->id])
        ->assertNotFound();

    $this->actingAs($this->jeu->eleveur24a)
        ->post(route('elevage-courant'), ['elevage' => $this->jeu->elevage24b->id])
        ->assertNotFound();
});

it('affiche la raison sociale de l\'élevage quand elle est connue', function () {
    DB::table('prive.elevage_identite')->where('elevage_id', $this->jeu->elevage24a->id)->update(['raison_sociale' => 'GAEC des Essais']);

    $this->actingAs($this->jeu->eleveur24a)->get(route('accueil'))->assertSee('GAEC des Essais');
});

it('lit le nom, la signature et la version dans les jetons, jamais en dur (§ 8.1, ENF-EXP-03)', function () {
    config(['brand.nom' => 'Mise au point', 'brand.signature' => 'Signature d\'essai.', 'app.version' => '9.9.9']);

    $this->actingAs($this->jeu->eleveur24a)->get(route('accueil'))
        ->assertSee('Mise au point')
        ->assertSee('Signature d&#039;essai.', false)
        ->assertSee('Version 9.9.9')
        ->assertDontSee('Focus Troupeau');

    $this->post(route('logout'));
    $this->get(route('login'))->assertSee('Mise au point')->assertDontSee('Focus Troupeau');
});

it('n\'écrit le nom de l\'application en dur dans aucune vue', function () {
    $vues = collect(File::allFiles(resource_path('views')))
        ->filter(fn ($fichier) => str_contains($fichier->getContents(), 'Focus Troupeau'))
        ->map(fn ($fichier) => $fichier->getRelativePathname())
        ->values()
        ->all();

    expect($vues)->toBe([]);
});

it('déconnecte un compte désactivé en cours de session', function () {
    $eleveur = $this->jeu->eleveur24a;
    $this->actingAs($eleveur);
    $eleveur->update(['actif' => false]);

    $this->get(route('accueil'))->assertRedirect(route('login'));
    $this->assertGuest();
});

it('range les imports et l\'administration dans des menus déroulants de la barre de navigation', function () {
    $page = $this->actingAs($this->jeu->chargeDeMission)->get(route('admin.journal'))->assertOk();
    $html = $page->getContent();

    expect($html)->toMatch('#<summary\s+data-actif\s*>Administration</summary>#')
        ->toMatch('#<summary\s*>Imports</summary>#')
        ->toMatch('#href="'.preg_quote(route('admin.journal'), '#').'" class="option"\s+aria-current="page"#');
    $page->assertSee(route('admin.utilisateurs'), false)->assertSee(route('import.sagema'), false)
        ->assertSeeInOrder(['Navigation principale', 'Dossier', '>Élevage</summary>', 'Stocks', 'Paramètres', 'Élevages', 'Imports', 'Administration'], false);

    $this->actingAs($this->jeu->veterinaireConseil24)->get(route('travail'))
        ->assertDontSee('>Imports</summary>', false)->assertDontSee('>Administration</summary>', false);
});

it('garde les outils de l\'élevage en liens directs pour l\'éleveur et les range dans le menu « Élevage » pour un agent', function () {
    $eleveur = $this->actingAs($this->jeu->eleveur24a)->get(route('accueil'))->assertDontSee('>Élevage</summary>', false)->getContent();
    expect($eleveur)->toMatch('#href="'.preg_quote(route('stocks', $this->jeu->elevage24a->id), '#').'" class="lien-navigation"#');

    $this->actingAs($this->jeu->technicien24);
    $this->post(route('elevage-courant'), ['elevage' => $this->jeu->elevage24a->id]);
    $html = $this->get(route('stocks', $this->jeu->elevage24a->id))->assertOk()->getContent();

    expect($html)->toMatch('#<summary\s+data-actif\s*>Élevage</summary>#')
        ->toMatch('#href="'.preg_quote(route('stocks', $this->jeu->elevage24a->id), '#').'" class="option"\s+aria-current="page"#');
});
