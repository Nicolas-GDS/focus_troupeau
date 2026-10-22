<?php

use App\Livewire\Import\ImportBulletins;
use App\Models\App\Convention;
use App\Models\App\ImportBulletin;
use App\Models\Fourrage\Prelevement;
use App\Services\Import\ExtracteurBulletin;
use App\Services\Import\ImportBulletinService;
use App\Services\Import\PreRapprochementService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// Pré-rapprochement des bulletins « à rapprocher » (EF-M9-01) : propositions parmi les élevages du GDS du dépôt sous
// convention active, retenues en un clic par le technicien ; T-40 (cloisonnement de l'écran).
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Storage::fake('bulletins');
    $this->jeu = JeuCloisonnement::creer();

    // Le jeu J4 met chaque élevage sous convention active. 24B : même code postal que le bulletin ; 24A : convention
    // échue ; 47 : même raison sociale que 24B, mais autre GDS.
    DB::table('prive.elevage_identite')->where('elevage_id', $this->jeu->elevage24b->id)->update(['code_postal' => '24260']);
    DB::table('prive.elevage_identite')->where('elevage_id', $this->jeu->elevage47->id)->update(['raison_sociale' => 'GAEC Essai Dordogne B']);
    echoir($this->jeu->elevage24a->id);
});

function echoir(string $elevageId): void
{
    Convention::query()->withoutGlobalScopes()->where('elevage_id', $elevageId)
        ->update(['statut' => Convention::STATUT_ECHUE, 'date_fin' => now()->subDay()->toDateString()]);
}

/**
 * Un bulletin du GDS 24 resté « à rapprocher », dont l'en-tête porte l'identité donnée.
 *
 * @param  array<string, string|null>  $identite
 */
function bulletinARapprocher(array $identite): ImportBulletin
{
    app()->instance(ExtracteurBulletin::class, new class($identite) implements ExtracteurBulletin
    {
        public function __construct(private array $identite) {}

        public function extraire(string $cheminPdf): array
        {
            return [
                'entete' => ['num_echantillon' => 'HA-PRE-01', 'date_prelevement' => '05/12/2025', 'client_lano' => null,
                    'espece_fourrage' => 'Prairie Naturelle (100 %)', 'nom_echantillon' => 'FOIN ESSAI'] + $this->identite,
                'famille_rapport' => 'FOIN',
                'resultats' => ['MS' => 86.0, 'MS__unit' => '% brut', 'MAT' => 110, 'MAT__unit' => 'g/kg MS'],
            ];
        }
    });
    $service = app(ImportBulletinService::class);
    $chemin = base_path('tests/Fixtures/lano/HA25-41526_ELV-0001_anonymise.pdf');

    return $service->traiter($service->deposer($chemin, basename($chemin), 24, test()->jeu->technicien24));
}

it('propose les seuls élevages du GDS du dépôt sous convention active', function () {
    $import = bulletinARapprocher(['eleveur' => 'GAEC ESSAI DORDOGNE B', 'code_postal' => '24260', 'commune' => 'LE BUGUE']);
    $this->actingAs($this->jeu->technicien24);

    $propositions = app(PreRapprochementService::class)->propositions([$import])[$import->id];

    expect($import->statut)->toBe('a_rapprocher')
        ->and(array_map(fn ($p) => $p->elevage->elevageId, $propositions))->toBe([$this->jeu->elevage24b->id])
        ->and($propositions[0]->score)->toBe(80)
        ->and($propositions[0]->motifs)->toBe(['Nom identique', 'Code postal identique']);
});

it('ne propose plus un élevage dont la convention est échue', function () {
    echoir($this->jeu->elevage24b->id);
    $import = bulletinARapprocher(['eleveur' => 'GAEC ESSAI DORDOGNE B']);
    $this->actingAs($this->jeu->technicien24);

    expect(app(PreRapprochementService::class)->propositions([$import])[$import->id])->toBe([]);
});

it('affiche la proposition et la retient en un clic, avec une trace au journal', function () {
    $import = bulletinARapprocher(['eleveur' => 'GAEC ESSAI DORDOGNE B', 'code_postal' => '24260']);
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(ImportBulletins::class)
        ->assertSee('Correspondance forte — 80 / 100')
        ->assertSee('Rapprocher sur cet élevage')
        ->call('retenirProposition', $import->id, $this->jeu->elevage24b->id)
        ->assertHasNoErrors()
        ->assertSee('Importé et contrôlé');

    $journal = DB::table('app.journal')->where('action', 'rapprochement_bulletin')->sole();
    expect($import->fresh()?->statut)->toBe('importe')
        ->and(Prelevement::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage24b->id)->exists())->toBeTrue()
        ->and(json_decode((string) $journal->detail, true)['pre_rapprochement']['score'])->toBe(80);
});

it('refuse de retenir en un clic un élevage qui n\'est pas proposé', function () {
    $import = bulletinARapprocher(['eleveur' => 'GAEC ESSAI DORDOGNE B']);
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(ImportBulletins::class)
        ->call('retenirProposition', $import->id, $this->jeu->elevage24a->id)
        ->assertHasErrors("elevagesChoisis.{$import->id}");

    expect($import->fresh()?->statut)->toBe('a_rapprocher');
});

it('n\'ouvre ni les propositions ni le rapprochement en un clic hors du GDS du technicien', function () {
    $import = bulletinARapprocher(['eleveur' => 'GAEC ESSAI DORDOGNE B']);
    $this->actingAs($this->jeu->technicien47);

    $composant = Livewire::test(ImportBulletins::class)->assertDontSee('Rapprocher sur cet élevage');

    expect(fn () => $composant->call('retenirProposition', $import->id, $this->jeu->elevage24b->id))->toThrow(ModelNotFoundException::class)
        ->and($import->fresh()?->statut)->toBe('a_rapprocher');
});
