<?php

use App\Domain\Troupeau\ClasseCouverture;
use App\Jobs\EnvoyerNotification;
use App\Models\App\Notification;
use App\Models\App\User;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Fourrage\Elevage;
use App\Models\Plan\LotAnimaux;
use App\Services\Pdf\RenduPdf;
use App\Services\Troupeau\AnalyseRationService;
use Database\Seeders\JeuJ2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// Jeu J2 (§ 9.1) : brebis nourries avec un seau pour bovins — T-13k, T-13l (cuivre au-delà du maximum réglementaire,
// gravité 3) et T-22 (publication de niveau 3 : SMS à l'éleveur et au technicien, journalisés).
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake([EnvoyerNotification::class]);
    Mail::fake();
    Storage::fake('local');
    app()->instance(RenduPdf::class, new class implements RenduPdf
    {
        public function pdf(string $html, string $piedDePage): string
        {
            return '%PDF-1.7';
        }
    });
    (new JeuJ2Seeder)->run();
    $this->elevage = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ2Seeder::CODE)->sole();
    $this->eleveur = User::query()->where('email', JeuJ2Seeder::ELEVEUR)->sole();
});

it('T-13k, T-13l : le cuivre de la ration des brebis dépasse le maximum réglementaire, gravité 3', function () {
    $this->actingAs($this->eleveur);
    $lot = LotAnimaux::query()->where('libelle', 'Brebis allaitantes')->sole();
    $cu = collect(app(AnalyseRationService::class)->analyser($lot->phases()->sole(), $lot, true)['elements'])->firstWhere('code', 'CU');

    expect($cu['resultat']->concentration)->toEqualWithDelta((1.5 * 5 + 0.1 * 300) / 1.6, 1e-9)
        ->and($cu['resultat']->classe)->toBe(ClasseCouverture::MaximumDepasse)
        ->and($cu['resultat']->gravite)->toBe(3)
        ->and($cu['besoin']->maximum)->toBe(15.0);
});

it('T-22 : la visite de niveau 3 visée et publiée envoie un SMS à l\'éleveur et au technicien, journalisés', function () {
    $iv = InterpretationVisite::query()->withoutGlobalScopes()->whereIn('visite_id', DB::table('dossier.visite')->where('elevage_id', $this->elevage->id)->select('id'))->sole();
    $technicien = User::query()->where('email', JeuJ2Seeder::TECHNICIEN)->sole();
    $sms = Notification::query()->where('reference_objet', 'dossier.interpretation_visite:'.$iv->id)->where('canal', 'sms')->orderBy('id')->get();

    expect([$iv->statut, $iv->niveau_global, $iv->vise_par !== null])->toBe(['publie', 3, true])
        ->and($sms->pluck('user_id')->all())->toBe([$this->eleveur->id, $technicien->id])
        ->and((string) $sms[0]->corps)->toContain('alerte de niveau 3')->toContain('Retirer dès aujourd')
        ->and(DB::table('app.journal')->where('elevage_id', $this->elevage->id)->where('action', 'like', 'publication%')->exists())->toBeTrue();
});

it('se rejoue sans doublon et refuse la production', function () {
    (new JeuJ2Seeder)->run();
    expect(Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ2Seeder::CODE)->count())->toBe(1);
    app()->detectEnvironment(fn () => 'production');
    expect(fn () => (new JeuJ2Seeder)->run())->toThrow(RuntimeException::class);
});
