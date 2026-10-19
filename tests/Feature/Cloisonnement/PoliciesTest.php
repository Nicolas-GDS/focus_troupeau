<?php

use App\Models\App\Consentement;
use App\Models\App\Convention;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\Support\JeuCloisonnement;

// § 2.3 (matrice des droits), § 6.3 (policies), ENF-SEC-04, D12 / EF-M0-06 / T-32 (convention échue : lecture seule).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
});

it('attribue à chaque rôle les permissions de la matrice du § 2.3', function (string $role, array $permissions) {
    $user = User::factory()->create();
    $user->assignRole($role);

    expect($user->getAllPermissions()->pluck('name')->sort()->values()->all())->toBe($permissions);
})->with([
    'éleveur' => ['eleveur', [
        'bilan_sanitaire.lire', 'bulletin.lire', 'consentement.gerer_propre', 'consentement.lire', 'convention.lire',
        'donnees.exporter', 'eau.lire', 'elevage.lire', 'etiquette.lire', 'etiquette.rediger', 'interpretation.lire',
        'mesure_terrain.lire', 'plan.ecrire', 'plan.lire', 'plan_action.lire', 'plan_action.marquer_fait',
        'profil_sanguin.lire', 'stock.ecrire', 'stock.lire', 'visite.lire',
    ]],
    'technicien' => ['technicien', [
        'bilan_sanitaire.lire', 'bilan_sanitaire.rediger', 'bulletin.controler', 'bulletin.importer', 'bulletin.lire',
        'compte.inviter', 'compte.lire', 'consentement.lire', 'convention.ecrire', 'convention.lire', 'donnees.exporter',
        'eau.lire', 'eau.rediger', 'elevage.ecrire', 'elevage.lire', 'etiquette.lire', 'etiquette.rediger', 'etiquette.valider',
        'interpretation.lire', 'interpretation.rediger', 'mesure_terrain.lire', 'mesure_terrain.rediger',
        'note_liaison.rediger', 'pilotage.lire', 'plan.ecrire', 'plan.lire', 'plan_action.lire', 'plan_action.rediger',
        'profil_sanguin.lire', 'profil_sanguin.rediger', 'profil_sanguin.valider', 'referentiel.lire', 'sagema.importer',
        'stock.ecrire', 'stock.lire', 'visite.lire', 'visite.rediger',
    ]],
    'vétérinaire conseil' => ['veterinaire_gds', [
        'bilan_sanitaire.lire', 'bulletin.lire', 'convention.lire', 'eau.lire', 'elevage.lire', 'etiquette.lire',
        'interpretation.lire', 'interpretation.viser', 'mesure_terrain.lire', 'plan.lire', 'plan_action.lire',
        'plan_action.valider_veterinaire', 'profil_sanguin.lire', 'profil_sanguin.rediger', 'referentiel.lire',
        'stock.lire', 'visite.lire',
    ]],
    'vétérinaire d\'élevage' => ['veterinaire', [
        'bilan_sanitaire.lire', 'bulletin.lire', 'convention.lire', 'eau.lire', 'elevage.lire', 'etiquette.lire',
        'interpretation.lire', 'mesure_terrain.lire', 'plan.lire', 'plan_action.lire', 'profil_sanguin.lire',
        'profil_sanguin.rediger', 'stock.lire', 'visite.lire',
    ]],
    'administrateur' => ['admin', [
        'bilan_sanitaire.lire', 'bulletin.lire', 'compte.ecrire', 'compte.inviter', 'compte.lire',
        'consentement.ecrire', 'consentement.lire', 'convention.ecrire', 'convention.lire', 'convention.resilier',
        'donnees.exporter', 'eau.lire', 'elevage.anonymiser', 'elevage.ecrire', 'elevage.lire', 'etiquette.lire',
        'gds.ecrire', 'interpretation.lire', 'journal.lire', 'mesure_terrain.lire', 'pilotage.lire', 'plan.lire',
        'plan_action.lire', 'profil_sanguin.lire', 'referentiel.ecrire', 'referentiel.lire', 'stock.lire', 'visite.lire',
    ]],
]);

it('autorise la lecture d\'un élevage seulement dans le périmètre', function () {
    $jeu = $this->jeu;

    expect(Gate::forUser($jeu->technicien24)->allows('view', $jeu->elevage24a))->toBeTrue()
        ->and(Gate::forUser($jeu->technicien24)->allows('view', $jeu->elevage47))->toBeFalse()
        ->and(Gate::forUser($jeu->eleveur24a)->allows('view', $jeu->elevage24a))->toBeTrue()
        ->and(Gate::forUser($jeu->eleveur24a)->allows('view', $jeu->elevage24b))->toBeFalse()
        ->and(Gate::forUser($jeu->veterinaire)->allows('view', $jeu->elevage24a))->toBeTrue()
        ->and(Gate::forUser($jeu->veterinaire)->allows('view', $jeu->elevage24b))->toBeFalse()
        ->and(Gate::forUser($jeu->adminGlobal)->allows('view', $jeu->elevage47))->toBeTrue();
});

it('réserve la modification du dossier élevage au technicien et à l\'administrateur de son GDS', function () {
    $jeu = $this->jeu;

    expect(Gate::forUser($jeu->technicien24)->allows('update', $jeu->elevage24a))->toBeTrue()
        ->and(Gate::forUser($jeu->technicien24)->allows('update', $jeu->elevage47))->toBeFalse()
        ->and(Gate::forUser($jeu->admin24)->allows('update', $jeu->elevage24b))->toBeTrue()
        ->and(Gate::forUser($jeu->veterinaireConseil24)->allows('update', $jeu->elevage24a))->toBeFalse()
        ->and(Gate::forUser($jeu->eleveur24a)->allows('update', $jeu->elevage24a))->toBeFalse()
        ->and(Gate::forUser($jeu->adminGlobal)->allows('delete', $jeu->elevage24a))->toBeFalse();
});

it('laisse le technicien créer et prolonger la convention de son GDS (EF-M0-02, EF-M0-06)', function () {
    $jeu = $this->jeu;
    $convention24 = Convention::query()->withoutGlobalScopes()->where('elevage_id', $jeu->elevage24a->id)->sole();
    $convention47 = Convention::query()->withoutGlobalScopes()->where('elevage_id', $jeu->elevage47->id)->sole();

    expect(Gate::forUser($jeu->technicien24)->allows('create', Convention::class))->toBeTrue()
        ->and(Gate::forUser($jeu->technicien24)->allows('update', $convention24))->toBeTrue()
        ->and(Gate::forUser($jeu->technicien24)->allows('update', $convention47))->toBeFalse()
        ->and(Gate::forUser($jeu->eleveur24a)->allows('view', $convention24))->toBeTrue()
        ->and(Gate::forUser($jeu->eleveur24a)->allows('update', $convention24))->toBeFalse()
        ->and(Gate::forUser($jeu->technicien24)->allows('delete', $convention24))->toBeFalse();
});

it('laisse l\'éleveur gérer les consentements de son élevage, et lui seul parmi les comptes externes (EF-M0-07)', function () {
    $jeu = $this->jeu;

    expect(Gate::forUser($jeu->eleveur24a)->allows('create', [Consentement::class, $jeu->elevage24a]))->toBeTrue()
        ->and(Gate::forUser($jeu->eleveur24a)->allows('revoquer', $jeu->consentementVeterinaire))->toBeTrue()
        ->and(Gate::forUser($jeu->eleveur24a)->allows('create', [Consentement::class, $jeu->elevage24b]))->toBeFalse()
        ->and(Gate::forUser($jeu->veterinaire)->allows('revoquer', $jeu->consentementVeterinaire))->toBeFalse()
        ->and(Gate::forUser($jeu->veterinaire)->allows('view', $jeu->consentementVeterinaire))->toBeFalse()
        ->and(Gate::forUser($jeu->technicien24)->allows('view', $jeu->consentementVeterinaire))->toBeTrue()
        ->and(Gate::forUser($jeu->technicien24)->allows('revoquer', $jeu->consentementVeterinaire))->toBeFalse()
        ->and(Gate::forUser($jeu->admin24)->allows('revoquer', $jeu->consentementVeterinaire))->toBeTrue();
});

it('ne permet pas de révoquer deux fois un consentement', function () {
    $this->jeu->consentementVeterinaire->update(['revoque_le' => now()]);

    expect(Gate::forUser($this->jeu->eleveur24a)->allows('revoquer', $this->jeu->consentementVeterinaire))->toBeFalse();
});

it('ouvre l\'écriture des stocks et du plan à l\'éleveur et au technicien sous convention active', function () {
    $jeu = $this->jeu;

    foreach (['ecrireStocks', 'ecrirePlan'] as $capacite) {
        expect(Gate::forUser($jeu->eleveur24a)->allows($capacite, $jeu->elevage24a))->toBeTrue()
            ->and(Gate::forUser($jeu->technicien24)->allows($capacite, $jeu->elevage24a))->toBeTrue()
            ->and(Gate::forUser($jeu->technicien24)->allows($capacite, $jeu->elevage47))->toBeFalse()
            ->and(Gate::forUser($jeu->veterinaire)->allows($capacite, $jeu->elevage24a))->toBeFalse()
            ->and(Gate::forUser($jeu->veterinaireConseil24)->allows($capacite, $jeu->elevage24a))->toBeFalse()
            ->and(Gate::forUser($jeu->admin24)->allows($capacite, $jeu->elevage24a))->toBeFalse();
    }
});

it('passe l\'élevage en lecture seule quand la convention est échue (T-32, D12)', function () {
    $jeu = $this->jeu;
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $jeu->elevage24a->id)
        ->update(['statut' => 'echue', 'date_fin' => now()->subDay()->toDateString()]);

    foreach ([$jeu->eleveur24a, $jeu->technicien24] as $user) {
        expect(Gate::forUser($user)->allows('ecrireStocks', $jeu->elevage24a))->toBeFalse()
            ->and(Gate::forUser($user)->allows('ecrirePlan', $jeu->elevage24a))->toBeFalse()
            ->and(Gate::forUser($user)->allows('view', $jeu->elevage24a))->toBeTrue();
    }
});

it('ferme l\'écriture dès le lendemain de la date de fin, avant même la tâche de clôture', function () {
    $jeu = $this->jeu;
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $jeu->elevage24a->id)
        ->update(['date_fin' => today()->toDateString()]);

    expect(Gate::forUser($jeu->eleveur24a)->allows('ecrireStocks', $jeu->elevage24a))->toBeTrue();

    $this->travel(1)->day();

    expect(Gate::forUser($jeu->eleveur24a)->allows('ecrireStocks', $jeu->elevage24a))->toBeFalse();
});

it('ferme l\'écriture pour une convention résiliée ou absente', function () {
    $jeu = $this->jeu;
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $jeu->elevage24a->id)->update(['statut' => 'resiliee']);
    $sansConvention = Elevage::factory()->gds(24)->create();

    expect(Gate::forUser($jeu->eleveur24a)->allows('ecrireStocks', $jeu->elevage24a))->toBeFalse()
        ->and(Gate::forUser($jeu->technicien24)->allows('ecrirePlan', $sansConvention))->toBeFalse();
});
