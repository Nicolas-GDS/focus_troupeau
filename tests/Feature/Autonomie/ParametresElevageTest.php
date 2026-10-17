<?php

use App\Jobs\EnvoyerNotification;
use App\Livewire\Autonomie\Parametres;
use App\Models\App\Convention;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Stock\Inventaire;
use App\Services\Autonomie\ApprovisionnementService;
use App\Services\Autonomie\EcranBilan;
use App\Services\Dossier\SaisieRefusee;
use App\Services\Referentiels\ParametresElevage;
use App\Services\Stocks\StocksService;
use Database\Seeders\JeuJ1Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// E16 — paramètres de l'élevage (EF-M4-07, EF-M7-03, D10) : valeur propre à l'élevage appliquée aux saisies, référence
// et source affichées, écarts listés au technicien, paramètres du référentiel non modifiables (T-32, T-40).
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([EnvoyerNotification::class]);
    Mail::fake();
    (new JeuJ1Seeder)->run();
    $this->elevage = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ1Seeder::CODE)->sole();
    $this->inventaire = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->where('statut', Inventaire::STATUT_COURANT)->sole();
    $this->eleveur = User::query()->where('email', JeuJ1Seeder::ELEVEUR)->sole();
    $this->technicien = User::query()->where('email', JeuJ1Seeder::TECHNICIEN)->sole();
});

it('applique la valeur de l\'élevage aux saisies suivantes, la référence restant affichée (EF-M4-07, D10)', function () {
    $this->actingAs($this->eleveur);
    Livewire::test(Parametres::class, ['elevage' => $this->elevage])
        ->assertSee('Pertes de stockage (balles, vrac)')->assertSee('référence 3 %')
        ->set('valeurs.pertes__stockage__sous_toit', '5')->set('motifs.pertes__stockage__sous_toit', 'Hangar ouvert au vent')->call('enregistrer', 'pertes.stockage.sous_toit')
        ->assertHasNoErrors()->assertSee('5 %')->assertSee('valeur de l\'élevage', false)
        ->set('valeurs.prix__enrubannage_t_ms', '210')->call('enregistrer', 'prix.enrubannage_t_ms')->assertHasNoErrors()
        ->set('valeurs.securite__marge', '20')->call('enregistrer', 'securite.marge')->assertHasNoErrors();

    $lot = app(StocksService::class)->enregistrerLot($this->inventaire, ['libelle' => 'Foin de complément', 'forme' => 'balle_ronde', 'famille_code' => 'FOIN',
        'nombre' => 10, 'poids_brut_kg' => 300, 'mode_stockage' => 'pertes.stockage.sous_toit']);
    $achat = collect(app(ApprovisionnementService::class)->proposition($this->inventaire)['lignes'])->firstWhere('levier', 'achat_fourrage');

    expect([(float) $lot->pertes_pct, (float) $lot->pertes_defaut_pct])->toBe([0.05, 0.05])
        ->and($achat['prix'])->toBe(210.0)
        ->and(app(ParametresElevage::class)->valeur('securite.marge', $this->elevage->id, 24))->toBe(0.2)
        ->and(app(ParametresElevage::class)->valeur('securite.marge', null, 24))->toBe(0.15);

    // l'inventaire en cours garde sa marge ; la valeur de l'élevage est signalée dans les hypothèses du bilan suivant
    app(StocksService::class)->clore($this->inventaire);
    $nouvel = app(StocksService::class)->creerInventaire($this->elevage, ['date_inventaire' => '2027-04-01', 'type' => 'fin_recolte', 'date_debut' => '2027-04-01', 'date_fin' => '2027-10-31', 'annee_seche' => false]);
    expect((float) $nouvel->marge_securite)->toBe(0.2)
        ->and(collect(app(EcranBilan::class)->donnees($nouvel)['hypotheses'])->first()['source'])->toStartWith('Valeur de l\'élevage (E16) ; référence 15 %');
});

it('liste les écarts au technicien et rétablit la référence', function () {
    app(ParametresElevage::class)->modifier($this->elevage, 'ms.defaut.foin', 88, 'Foin récolté très sec', $this->eleveur);
    $this->actingAs($this->technicien);

    Livewire::test(Parametres::class, ['elevage' => $this->elevage])
        ->assertSee('Écarts à la référence')->assertSee('Foin récolté très sec')->assertSee('88 % brut')->assertSee('au lieu de 85 % brut')
        ->call('retablir', 'ms.defaut.foin')->assertSee('Aucune valeur modifiée pour cet élevage.');

    expect(app(ParametresElevage::class)->ecarts($this->elevage))->toBe([]);
});

it('refuse un paramètre du référentiel ou une valeur hors bornes', function () {
    $parametres = app(ParametresElevage::class);

    expect(fn () => $parametres->modifier($this->elevage, 'ugb.kg_ms_par_ugb_jour', 15, null, $this->eleveur))->toThrow(SaisieRefusee::class)
        ->and(fn () => $parametres->modifier($this->elevage, 'pertes.distribution.deroule_sol', 1.5, null, $this->eleveur))->toThrow(SaisieRefusee::class)
        ->and(fn () => $parametres->modifier($this->elevage, 'ms.defaut.paille', 120, null, $this->eleveur))->toThrow(SaisieRefusee::class)
        ->and(collect($parametres->liste($this->elevage))->pluck('cle'))->not->toContain('ugb.kg_ms_par_ugb_jour', 'incertitude.poids_estime');

    $this->actingAs($this->eleveur);
    Livewire::test(Parametres::class, ['elevage' => $this->elevage])
        ->set('valeurs.pertes__stockage__sous_toit', 'beaucoup')->call('enregistrer', 'pertes.stockage.sous_toit')->assertHasErrors('valeurs.pertes__stockage__sous_toit')
        ->set('valeurs.pertes__stockage__sous_toit', '150')->call('enregistrer', 'pertes.stockage.sous_toit')->assertHasErrors('valeurs.pertes__stockage__sous_toit');
});

it('lit sans modifier sous convention échue, et cloisonne l\'écran (T-32, T-40)', function () {
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $this->elevage->id)->update(['date_fin' => now()->subDay()->toDateString()]);
    $this->actingAs($this->eleveur);
    Livewire::test(Parametres::class, ['elevage' => $this->elevage])->assertSee('référence 3 %')->assertDontSee('Enregistrer')
        ->set('valeurs.pertes__stockage__sous_toit', '5')->call('enregistrer', 'pertes.stockage.sous_toit')->assertForbidden();

    $jeu = JeuCloisonnement::creer();
    $this->actingAs($jeu->eleveur24a);
    $this->get(route('parametres', $this->elevage->id))->assertNotFound();
    $this->get(route('parametres', $jeu->elevage24a->id))->assertOk();
});
