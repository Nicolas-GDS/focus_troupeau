<?php

use App\Jobs\ExtraireBulletinLano;
use App\Models\App\ImportBulletin;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Prelevement;
use App\Notifications\Interpretation\BulletinEnAnomalieNotification;
use App\Services\Import\ExtracteurBulletin;
use App\Services\Import\ExtractionImpossible;
use App\Services\Import\ImportBulletinService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\JeuCloisonnement;

// EF-M9-01 (import des bulletins), EF-M2-01 et RC-2 (plausibilité, T-02), § 6.4 (circuit d'import).
uses(RefreshDatabase::class);

beforeEach(function () {
    // File simulée : chaque test déclenche lui-même l'extraction (traiter() ou le job), une seule fois.
    Queue::fake();
    Storage::fake('bulletins');
    Notification::fake();
    $this->jeu = JeuCloisonnement::creer();
    $this->service = app(ImportBulletinService::class);
});

function deposerExemple(string $numero, int $gds = 24): ImportBulletin
{
    $pdf = base_path("tests/Fixtures/lano/{$numero}_ELV-0001_anonymise.pdf");

    return test()->service->deposer($pdf, basename($pdf), $gds, test()->jeu->technicien24);
}

/**
 * Remplace l'extraction par un bulletin synthétique (cas T-02, valeurs du bulletin HA25-42515 de la campagne 2025-2026).
 *
 * @param  array<string, float|string>  $resultats
 */
function extractionSimulee(string $numero, array $resultats, ?string $client = null, string $famille = 'FOIN'): void
{
    $avecUnites = [];
    foreach ($resultats as $cle => $valeur) {
        $avecUnites[$cle] = $valeur;
        $avecUnites[$cle.'__unit'] = in_array($cle, ['Cu', 'Zn', 'Mn', 'Fe'], true) ? 'mg/kg MS' : ($cle === 'MS' ? '% brut' : 'g/kg MS');
    }

    app()->instance(ExtracteurBulletin::class, new class($numero, $avecUnites, $client, $famille) implements ExtracteurBulletin
    {
        public function __construct(private string $numero, private array $resultats, private ?string $client, private string $famille) {}

        public function extraire(string $cheminPdf): array
        {
            return [
                'entete' => ['num_echantillon' => $this->numero, 'date_prelevement' => '05/12/2025', 'client_lano' => $this->client,
                    'espece_fourrage' => 'Prairie Naturelle (100 %)', 'nom_echantillon' => 'FOIN ESSAI'],
                'famille_rapport' => $this->famille,
                'resultats' => $this->resultats,
            ];
        }
    });
    test()->service = app(ImportBulletinService::class);
}

it('dépose un bulletin : empreinte, stockage privé, extraction en file d\'attente', function () {

    $import = deposerExemple('HA25-41526');

    expect($import->statut)->toBe('recu')
        ->and($import->hash_sha256)->toBe(hash_file('sha256', base_path('tests/Fixtures/lano/HA25-41526_ELV-0001_anonymise.pdf')))
        ->and($import->depose_par)->toBe($this->jeu->technicien24->id);
    Storage::disk('bulletins')->assertExists((string) $import->chemin);
    Queue::assertPushed(ExtraireBulletinLano::class, fn ($job) => $job->importId === $import->id);
});

it('refuse un fichier déjà déposé (même empreinte)', function () {
    deposerExemple('HA25-41526');

    $doublon = deposerExemple('HA25-41526');

    expect($doublon->statut)->toBe('doublon')->and($doublon->erreur)->toContain('déjà déposé');
    Queue::assertPushed(ExtraireBulletinLano::class, 1);
});

it('met en attente de rapprochement un bulletin sans élevage reconnu, puis l\'importe sur l\'élevage choisi', function () {
    $import = $this->service->traiter(deposerExemple('HA25-41526'));

    expect($import->statut)->toBe('a_rapprocher')->and($import->analyse_id)->toBeNull();

    $import = $this->service->rapprocher($import, $this->jeu->elevage24a, $this->jeu->technicien24);
    $analyse = Analyse::query()->withoutGlobalScopes()->findOrFail($import->analyse_id);
    $prelevement = Prelevement::query()->withoutGlobalScopes()->findOrFail($analyse->prelevement_id);
    $resultats = DB::table('fourrage.resultat')->where('analyse_id', $analyse->id)->get()->keyBy('parametre_code');

    expect($import->statut)->toBe('importe')
        ->and($analyse->statut)->toBe('controle')
        ->and($analyse->num_echantillon_labo)->toBe('HA25-41526')
        ->and($analyse->hash_pdf)->toBe($import->hash_sha256)
        ->and($prelevement->elevage_id)->toBe($this->jeu->elevage24a->id)
        ->and($prelevement->type_fourrage_code)->toBe('FOIN_PP')
        ->and($prelevement->a_completer)->toBeTrue()
        ->and($prelevement->campagne_code)->toBe('2025-2026')
        ->and($prelevement->date_prelevement->toDateString())->toBe('2025-12-05')
        ->and($resultats)->toHaveCount(41 + 3)
        ->and((float) $resultats['CU']->valeur)->toBe(4.0)
        ->and($resultats['CA_P']->origine)->toBe('calcul_gds')
        ->and((float) $resultats['CA_P']->valeur)->toEqualWithDelta(5.0 / 1.7, 1e-9)
        ->and($resultats['BACA']->origine)->toBe('labo');
    Storage::disk('bulletins')->assertExists("24/{$this->jeu->elevage24a->id}/HA25-41526.pdf");
    expect(DB::table('app.journal')->where('action', 'import_bulletin')->exists())->toBeTrue();
});

it('rapproche automatiquement par le numéro client du laboratoire, dans le GDS du dépôt seulement', function () {
    DB::table('prive.elevage_identite')->where('elevage_id', $this->jeu->elevage24b->id)->update(['num_client_labo' => '123456']);
    DB::table('prive.elevage_identite')->where('elevage_id', $this->jeu->elevage47->id)->update(['num_client_labo' => '777777']);

    extractionSimulee('HA-CLIENT-24', ['MS' => 86.0, 'MAT' => 110], client: '123456');
    $import = $this->service->traiter(deposerExemple('HA25-41526'));
    expect($import->statut)->toBe('importe')
        ->and(Prelevement::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage24b->id)->exists())->toBeTrue();

    extractionSimulee('HA-CLIENT-47', ['MS' => 86.0, 'MAT' => 110], client: '777777');
    $autreGds = $this->service->traiter(deposerExemple('HA25-41525'));
    expect($autreGds->statut)->toBe('a_rapprocher');
});

it('complète une analyse « attendue » par son numéro d\'échantillon', function () {
    $prelevement = DB::table('fourrage.prelevement')->insertGetId([
        'elevage_id' => $this->jeu->elevage24a->id, 'date_prelevement' => '2025-12-05', 'type_fourrage_code' => 'FOIN_ASSOC',
    ]);
    $attendue = DB::table('fourrage.analyse')->insertGetId([
        'prelevement_id' => $prelevement, 'laboratoire_id' => 1, 'num_echantillon_labo' => 'HA25-41525', 'statut' => 'attendu',
    ]);

    $import = $this->service->traiter(deposerExemple('HA25-41525'));

    expect($import->statut)->toBe('importe')
        ->and($import->analyse_id)->toBe($attendue)
        ->and(Analyse::query()->withoutGlobalScopes()->find($attendue)?->statut)->toBe('controle')
        ->and(Prelevement::query()->withoutGlobalScopes()->count())->toBe(1);
});

it('refuse un bulletin déjà importé (même numéro d\'échantillon, autre fichier)', function () {
    $premier = $this->service->traiter(deposerExemple('HA25-41526'));
    $this->service->rapprocher($premier, $this->jeu->elevage24a, $this->jeu->technicien24);

    extractionSimulee('HA25-41526', ['MS' => 89.0]);
    $second = $this->service->traiter(deposerExemple('HA25-41525'));

    expect($second->statut)->toBe('doublon')->and($second->erreur)->toContain('déjà importé');
});

it('T-02 : bloque le bulletin HA25-42515 — anomalie, quatre résultats signalés, exclu des références, techniciens du GDS prévenus', function () {
    extractionSimulee('HA25-42515', ['MS' => 86.0, 'MAT' => 110, 'Na' => 13.6, 'Cu' => 57.9, 'Mn' => 1765, 'Fe' => 9012, 'K' => 20, 'Zn' => 40]);
    $import = $this->service->traiter(deposerExemple('HA25-41526'));
    $import = $this->service->rapprocher($import, $this->jeu->elevage24a, $this->jeu->technicien24);
    $analyse = Analyse::query()->withoutGlobalScopes()->findOrFail($import->analyse_id);

    expect($import->statut)->toBe('anomalie')
        ->and($analyse->statut)->toBe('anomalie')
        ->and($analyse->exclu_references)->toBeTrue()
        ->and($analyse->anomalie_detail)->toContain('NA = 13.6')
        ->and(DB::table('fourrage.resultat')->where('analyse_id', $analyse->id)->where('flag_anomalie', true)->orderBy('parametre_code')->pluck('parametre_code')->all())
        ->toBe(['CU', 'FE', 'MN', 'NA']);

    Notification::assertSentTo([$this->jeu->technicien24, $this->jeu->chargeDeMission], BulletinEnAnomalieNotification::class);
    Notification::assertNotSentTo($this->jeu->technicien47, BulletinEnAnomalieNotification::class);
    expect(DB::table('app.notification')->where('type', 'bulletin_anomalie')->count())->toBe(2);
});

it('lève une anomalie avec motif et réintègre le bulletin dans les références', function () {
    extractionSimulee('HA25-42515', ['MS' => 86.0, 'Na' => 13.6]);
    $import = $this->service->rapprocher($this->service->traiter(deposerExemple('HA25-41526')), $this->jeu->elevage24a, $this->jeu->technicien24);
    $analyse = Analyse::query()->withoutGlobalScopes()->findOrFail($import->analyse_id);

    $this->service->leverAnomalie($analyse, 'Valeur confirmée par le laboratoire (sel en libre-service sur le foin)', $this->jeu->technicien24);

    expect($analyse->fresh()?->statut)->toBe('controle')
        ->and($analyse->fresh()?->exclu_references)->toBeFalse()
        ->and($analyse->fresh()?->anomalie_levee_par)->toBe($this->jeu->technicien24->id)
        ->and(DB::table('fourrage.resultat')->where('analyse_id', $analyse->id)->where('flag_anomalie', true)->count())->toBe(1)
        ->and(DB::table('app.journal')->where('action', 'levee_anomalie')->exists())->toBeTrue();

    expect(fn () => $this->service->leverAnomalie($analyse->fresh(), 'encore', $this->jeu->technicien24))->toThrow(RuntimeException::class);
});

it('annule un bulletin sans rien supprimer, avec un motif obligatoire', function () {
    $import = $this->service->rapprocher($this->service->traiter(deposerExemple('HA25-41526')), $this->jeu->elevage24a, $this->jeu->technicien24);
    $analyse = Analyse::query()->withoutGlobalScopes()->findOrFail($import->analyse_id);

    expect(fn () => $this->service->annuler($analyse, ' ', $this->jeu->technicien24))->toThrow(RuntimeException::class);

    $this->service->annuler($analyse, 'Échantillon mal identifié', $this->jeu->technicien24);

    expect($analyse->fresh()?->statut)->toBe('annule')
        ->and($analyse->fresh()?->exclu_references)->toBeTrue()
        ->and(DB::table('fourrage.resultat')->where('analyse_id', $analyse->id)->count())->toBeGreaterThan(0);
});

it('refuse de rapprocher un bulletin sur un élevage d\'un autre GDS', function () {
    $import = $this->service->traiter(deposerExemple('HA25-41526'));

    expect(fn () => $this->service->rapprocher($import, $this->jeu->elevage47, $this->jeu->technicien24))->toThrow(RuntimeException::class);
});

it('signale une erreur d\'extraction sans interrompre le circuit', function () {
    app()->instance(ExtracteurBulletin::class, new class implements ExtracteurBulletin
    {
        public function extraire(string $cheminPdf): array
        {
            throw new ExtractionImpossible('Maquette de bulletin inconnue.');
        }
    });
    $service = app(ImportBulletinService::class);

    $import = $service->traiter(deposerExemple('HA25-41526'));

    expect($import->statut)->toBe('erreur')->and($import->erreur)->toBe('Maquette de bulletin inconnue.');
});

it('exécute le job d\'extraction de bout en bout sur un vrai bulletin', function () {
    $import = deposerExemple('HA25-41527');
    (new ExtraireBulletinLano($import->id))->handle(app(ImportBulletinService::class));

    expect($import->fresh()?->statut)->toBe('a_rapprocher')
        ->and($import->fresh()?->donnees_extraites['entete']['num_echantillon'])->toBe('HA25-41527');
});
