<?php

use App\Domain\Agds\IdentiteAgds;
use App\Livewire\Elevages\Liste;
use App\Models\App\User;
use App\Services\Agds\SourceAgds;
use App\Services\Agds\SourceAgdsIndisponible;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// EF-M0-02, EF-M9-03 — pré-remplissage de la création d'un élevage depuis AGDS (vues SAGEMA) : champs repris, cheptel
// cherché dans le département du GDS choisi, EDE déjà suivi refusé, source indisponible journalisée, permission agds.lire.
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['agds.actif' => true]);
    $this->jeu = JeuCloisonnement::creer();

    $this->source = new class implements SourceAgds
    {
        /** @var list<array{string, string}> */
        public array $appels = [];

        public bool $panne = false;

        public ?DateTimeImmutable $cessation = null;

        public function identite(string $departement, string $numEde): ?IdentiteAgds
        {
            $this->appels[] = [$departement, $numEde];
            if ($this->panne) {
                throw new SourceAgdsIndisponible('connexion refusée');
            }

            return $departement === '24' && $numEde === '24001007'
                ? new IdentiteAgds('24001007', 'EARL DU PUY', 'EARL', ['Le Puy'], '24300', 'ABJAT SUR BANDIAT', null, [], null, null,
                    null, '0611223344', 'earl.puy@example.test', new DateTimeImmutable('2015-06-01'), $this->cessation)
                : null;
        }

        public function bovinsPresents(string $departement, string $numEde): array
        {
            return [];
        }
    };
    app()->instance(SourceAgds::class, $this->source);
});

it('pré-remplit l\'identité depuis AGDS puis crée l\'élevage avec son numéro EDE', function () {
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(Liste::class)
        ->assertSee('Pré-remplir depuis AGDS')
        ->set('nouveau.num_cheptel_ede', 'fr 24 001 007')->set('nouveau.nom_contact', 'Paul Martin')
        ->call('preremplirAgds')->assertHasNoErrors()
        ->assertSet('nouveau.num_cheptel_ede', 'FR24001007')
        ->assertSet('nouveau.raison_sociale', 'EARL DU PUY')
        ->assertSet('nouveau.code_postal', '24300')
        ->assertSet('nouveau.telephone', '0611223344')
        ->assertSet('nouveau.courriel', 'earl.puy@example.test')
        ->assertSet('nouveau.nom_contact', 'Paul Martin')
        ->assertSee('Identité pré-remplie depuis AGDS')
        ->call('creer')->assertHasNoErrors()->assertRedirect();

    $identite = DB::table('prive.elevage_identite')->where('num_cheptel_ede', 'FR24001007')->first();
    expect($identite->raison_sociale)->toBe('EARL DU PUY')
        ->and(DB::table('fourrage.elevage')->where('id', $identite->elevage_id)->value('departement'))->toBe('24')
        ->and($this->source->appels)->toBe([['24', '24001007']]);
});

it('cherche le cheptel dans le département du GDS choisi', function () {
    $this->actingAs($this->jeu->chargeDeMission);

    Livewire::test(Liste::class)
        ->set('nouveau.gds_id', '47')->set('nouveau.num_cheptel_ede', 'FR24001007')
        ->call('preremplirAgds')->assertHasErrors('nouveau.num_cheptel_ede')
        ->assertSee('Aucun cheptel FR24001007 n&#039;est connu d&#039;AGDS dans le département 47', false)
        ->assertSet('nouveau.raison_sociale', '');

    expect($this->source->appels)->toBe([['47', '24001007']]);
});

it('refuse un numéro EDE mal formé ou déjà suivi, sans interroger AGDS', function () {
    $this->actingAs($this->jeu->technicien24);
    DB::table('prive.elevage_identite')->where('elevage_id', $this->jeu->elevage24a->id)->update(['num_cheptel_ede' => 'FR24001007']);

    Livewire::test(Liste::class)
        ->set('nouveau.num_cheptel_ede', 'FR2400100')->call('preremplirAgds')->assertHasErrors('nouveau.num_cheptel_ede')
        ->set('nouveau.num_cheptel_ede', 'FR24001007')->call('preremplirAgds')->assertHasErrors('nouveau.num_cheptel_ede')
        ->assertSee('déjà celui d&#039;un élevage suivi', false);

    expect($this->source->appels)->toBe([]);
});

it('signale une activité cessée selon AGDS', function () {
    $this->source->cessation = new DateTimeImmutable('2024-12-31');
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(Liste::class)
        ->set('nouveau.num_cheptel_ede', 'FR24001007')->call('preremplirAgds')->assertHasNoErrors()
        ->assertSee('activité cessée le 31/12/2024');
});

it('journalise une source AGDS indisponible et laisse la saisie manuelle', function () {
    $this->source->panne = true;
    Log::spy();
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(Liste::class)
        ->set('nouveau.num_cheptel_ede', 'FR24001007')->set('nouveau.raison_sociale', 'Saisie manuelle')
        ->call('preremplirAgds')->assertHasErrors('nouveau.num_cheptel_ede')
        ->assertSet('nouveau.raison_sociale', 'Saisie manuelle');

    Log::shouldHaveReceived('error')->once();
});

it('réserve le pré-remplissage à agds.lire et le masque quand la lecture AGDS est désactivée', function () {
    $sansAgds = User::factory()->create();
    $sansAgds->givePermissionTo('elevage.ecrire');
    $sansAgds->gds()->sync([24]);
    $this->actingAs($sansAgds);

    Livewire::test(Liste::class)->assertDontSee('Pré-remplir depuis AGDS')
        ->set('nouveau.num_cheptel_ede', 'FR24001007')->call('preremplirAgds')->assertForbidden();

    config(['agds.actif' => false]);
    $this->actingAs($this->jeu->technicien24);
    Livewire::test(Liste::class)->assertDontSee('Pré-remplir depuis AGDS');

    expect($this->source->appels)->toBe([]);
});
