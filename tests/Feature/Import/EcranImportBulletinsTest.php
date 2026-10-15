<?php

use App\Livewire\Import\ImportBulletins;
use App\Models\App\ImportBulletin;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Services\Import\ImportBulletinService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// E19 — Import des bulletins (EF-M9-01, EF-M2-01, RC-2) ; ENF-SEC-04 (autorisation de chaque action),
// ENF-SEC-05 (type et taille des fichiers, PDF par route signée) ; T-40 (cloisonnement de l'écran et des PDF).
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Storage::fake('bulletins');
    $this->jeu = JeuCloisonnement::creer();
});

function pdfExemple(string $numero = 'HA25-41526'): UploadedFile
{
    $chemin = base_path("tests/Fixtures/lano/{$numero}_ELV-0001_anonymise.pdf");

    return UploadedFile::fake()->createWithContent(basename($chemin), (string) file_get_contents($chemin));
}

/**
 * Un bulletin importé et rapproché sur l'élevage donné (extraction réelle d'un bulletin d'exemple).
 */
function bulletinImporte(Elevage $elevage, string $numero = 'HA25-41526'): Analyse
{
    $service = app(ImportBulletinService::class);
    $chemin = base_path("tests/Fixtures/lano/{$numero}_ELV-0001_anonymise.pdf");
    $import = $service->traiter($service->deposer($chemin, basename($chemin), $elevage->gds_id, test()->jeu->technicien24));
    $import = $service->rapprocher($import, $elevage, test()->jeu->technicien24);

    return Analyse::query()->withoutGlobalScopes()->findOrFail($import->analyse_id);
}

it('ouvre l\'écran aux seuls techniciens', function (string $profil, int $statut) {
    $this->actingAs($this->jeu->{$profil})->get(route('import.bulletins'))->assertStatus($statut);
})->with([
    ['technicien24', 200], ['chargeDeMission', 200], ['veterinaireConseil24', 403], ['admin24', 403],
    ['adminGlobal', 403], ['eleveur24a', 403], ['veterinaire', 403],
]);

it('propose l\'écran dans la navigation du technicien', function () {
    $this->actingAs($this->jeu->technicien24)->get(route('travail'))->assertSee('Import des bulletins');
    $this->actingAs($this->jeu->veterinaireConseil24)->get(route('travail'))->assertDontSee('Import des bulletins');
});

it('dépose plusieurs PDF pour le GDS du technicien', function () {
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(ImportBulletins::class)
        ->assertSet('gdsId', 24)
        ->set('fichiers', [pdfExemple('HA25-41525'), pdfExemple('HA25-41526')])
        ->call('deposer')
        ->assertHasNoErrors();

    expect(ImportBulletin::query()->pluck('gds_id')->all())->toBe([24, 24])
        ->and(ImportBulletin::query()->pluck('depose_par')->unique()->all())->toBe([$this->jeu->technicien24->id]);
});

it('demande le GDS au chargé de mission rattaché aux deux GDS', function () {
    $this->actingAs($this->jeu->chargeDeMission);

    Livewire::test(ImportBulletins::class)
        ->assertSet('gdsId', null)
        ->set('fichiers', [pdfExemple()])
        ->call('deposer')
        ->assertHasErrors(['gdsId'])
        ->set('gdsId', 47)
        ->call('deposer')
        ->assertHasNoErrors();

    expect(ImportBulletin::query()->value('gds_id'))->toBe(47);
});

it('refuse un dépôt pour un GDS hors périmètre', function () {
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(ImportBulletins::class)
        ->set('gdsId', 47)
        ->set('fichiers', [pdfExemple()])
        ->call('deposer')
        ->assertForbidden();

    expect(ImportBulletin::query()->withoutGlobalScopes()->count())->toBe(0);
});

it('refuse un fichier qui n\'est pas un PDF ou qui dépasse 20 Mo (ENF-SEC-05)', function () {
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(ImportBulletins::class)
        ->set('fichiers', [UploadedFile::fake()->create('bulletin.pdf.exe', 10, 'application/x-msdownload')])
        ->call('deposer')
        ->assertHasErrors(['fichiers.0']);

    // Au-delà de 20 Mo, le fichier est refusé dès l'envoi (limite des envois Livewire), avant tout dépôt.
    Livewire::test(ImportBulletins::class)
        ->set('fichiers', [UploadedFile::fake()->create('enorme.pdf', 20481, 'application/pdf')])
        ->assertHasErrors(['fichiers.0'])
        ->call('deposer')
        ->assertHasErrors(['fichiers']);

    expect(ImportBulletin::query()->count())->toBe(0);
});

it('ne montre que les dépôts du GDS de l\'utilisateur', function () {
    $service = app(ImportBulletinService::class);
    $chemin = base_path('tests/Fixtures/lano/HA25-41526_ELV-0001_anonymise.pdf');
    $service->deposer($chemin, 'bulletin-24.pdf', 24, $this->jeu->technicien24);
    $service->deposer(base_path('tests/Fixtures/lano/HA25-41525_ELV-0001_anonymise.pdf'), 'bulletin-47.pdf', 47, $this->jeu->technicien47);

    $this->actingAs($this->jeu->technicien47);
    Livewire::test(ImportBulletins::class)->assertSee('bulletin-47.pdf')->assertDontSee('bulletin-24.pdf');
});

it('rapproche un bulletin sur un élevage du GDS, jamais sur un élevage hors périmètre', function () {
    $service = app(ImportBulletinService::class);
    $chemin = base_path('tests/Fixtures/lano/HA25-41526_ELV-0001_anonymise.pdf');
    $import = $service->traiter($service->deposer($chemin, basename($chemin), 24, $this->jeu->technicien24));
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(ImportBulletins::class)
        ->assertSee('À rapprocher')
        ->assertSee('EARL Essai Dordogne A')
        ->assertDontSee('EARL Essai Agenais')
        ->set("elevagesChoisis.{$import->id}", $this->jeu->elevage47->id)
        ->tap(fn ($composant) => expect(fn () => $composant->call('rapprocher', $import->id))
            ->toThrow(ModelNotFoundException::class));

    Livewire::test(ImportBulletins::class)
        ->set("elevagesChoisis.{$import->id}", $this->jeu->elevage24a->id)
        ->call('rapprocher', $import->id)
        ->assertHasNoErrors()
        ->assertSee('Importé et contrôlé');
});

it('lève une anomalie ou annule un bulletin avec un motif obligatoire', function () {
    $analyse = bulletinImporte($this->jeu->elevage24a);
    $analyse->update(['statut' => 'anomalie', 'anomalie_detail' => 'NA = 13.6 hors de la plage plausible [0 – 6]']);
    ImportBulletin::query()->where('analyse_id', $analyse->id)->update(['statut' => 'anomalie']);
    $this->actingAs($this->jeu->technicien24);

    Livewire::test(ImportBulletins::class)
        ->assertSee('NA = 13.6')
        ->call('leverAnomalie', $analyse->id)
        ->assertHasErrors(["motifs.{$analyse->id}"])
        ->set("motifs.{$analyse->id}", 'Valeur confirmée par le laboratoire')
        ->call('leverAnomalie', $analyse->id)
        ->assertHasNoErrors();

    expect($analyse->fresh()?->statut)->toBe('controle');
});

it('refuse les actions de contrôle hors périmètre ou sans le droit', function () {
    $analyse = bulletinImporte($this->jeu->elevage24a);
    $analyse->update(['statut' => 'anomalie']);

    // Analyse hors périmètre : introuvable à travers les scopes (404 en conditions réelles)
    $this->actingAs($this->jeu->technicien47);
    $composant = Livewire::test(ImportBulletins::class)->set("motifs.{$analyse->id}", 'Tentative hors périmètre');
    expect(fn () => $composant->call('annuler', $analyse->id))->toThrow(ModelNotFoundException::class);

    // Dans le périmètre mais sans le droit de contrôle : refusé
    $this->actingAs($this->jeu->veterinaireConseil24);
    Livewire::test(ImportBulletins::class)->assertForbidden();

    expect($analyse->fresh()?->statut)->toBe('anomalie');
});

it('sert le PDF d\'un bulletin par une route signée, dans le périmètre seulement (ENF-SEC-05, T-40)', function () {
    $analyse = bulletinImporte($this->jeu->elevage24a);
    $lien = URL::temporarySignedRoute('bulletins.pdf', now()->addMinutes(30), ['analyse' => $analyse->id]);

    $this->actingAs($this->jeu->technicien24)->get($lien)->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($this->jeu->veterinaireConseil24)->get($lien)->assertOk();
    $this->actingAs($this->jeu->technicien24)->get(route('bulletins.pdf', $analyse->id))->assertForbidden();
    $this->actingAs($this->jeu->technicien47)->get($lien)->assertNotFound();
    // Comptes externes : pas d'accès avant la publication (lot L3)
    $this->actingAs($this->jeu->eleveur24a)->get($lien)->assertForbidden();
    $this->actingAs($this->jeu->veterinaire)->get($lien)->assertForbidden();

    $this->travel(31)->minutes();
    $this->actingAs($this->jeu->technicien24)->get($lien)->assertForbidden();
});

it('sert le PDF d\'un dépôt au seul technicien du GDS', function () {
    $service = app(ImportBulletinService::class);
    $chemin = base_path('tests/Fixtures/lano/HA25-41526_ELV-0001_anonymise.pdf');
    $import = $service->deposer($chemin, basename($chemin), 24, $this->jeu->technicien24);
    $lien = URL::temporarySignedRoute('import.bulletins.pdf', now()->addMinutes(30), ['import' => $import->id]);

    $this->actingAs($this->jeu->technicien24)->get($lien)->assertOk();
    $this->actingAs($this->jeu->technicien47)->get($lien)->assertNotFound();
    $this->actingAs($this->jeu->admin24)->get($lien)->assertForbidden();
    expect(DB::table('app.import_bulletin')->count())->toBe(1);
});
