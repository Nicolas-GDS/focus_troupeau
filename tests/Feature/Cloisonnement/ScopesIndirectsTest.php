<?php

use App\Models\App\ImportBulletin;
use App\Models\Fourrage\Alerte;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Interpretation;
use App\Models\Fourrage\Prelevement;
use App\Models\Fourrage\Resultat;
use App\Scopes\GdsScope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\JeuCloisonnement;

// Chapitre 6.3 : cloisonnement des objets rattachés à l'élevage par une chaîne de tables
// (analyse → prélèvement → élevage ; résultat, interprétation → analyse ; alerte → interprétation).
uses(RefreshDatabase::class);

/**
 * Un bulletin complet (prélèvement, analyse, résultat, interprétation, alerte) pour l'élevage donné.
 */
function bulletinPour(Elevage $elevage, string $numero): void
{
    $prelevement = DB::table('fourrage.prelevement')->insertGetId([
        'elevage_id' => $elevage->id, 'date_prelevement' => '2025-12-05', 'type_fourrage_code' => 'FOIN_PP',
    ]);
    $analyse = DB::table('fourrage.analyse')->insertGetId([
        'prelevement_id' => $prelevement, 'laboratoire_id' => DB::table('ref.laboratoire')->value('id'),
        'num_echantillon_labo' => $numero,
    ]);
    DB::table('fourrage.resultat')->insert(['analyse_id' => $analyse, 'parametre_code' => 'MAT', 'valeur' => 61, 'unite' => 'g/kg MS']);
    $interpretation = DB::table('fourrage.interpretation')->insertGetId(['analyse_id' => $analyse]);
    DB::table('fourrage.alerte')->insert([
        'interpretation_id' => $interpretation, 'parametre_code' => 'MAT', 'classe' => 'faible', 'gravite' => 1, 'message' => 'MAT faible',
    ]);
}

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    bulletinPour($this->jeu->elevage24a, 'HA-24A');
    bulletinPour($this->jeu->elevage24b, 'HA-24B');
    bulletinPour($this->jeu->elevage47, 'HA-47');
});

it('limite les bulletins et tout ce qui en dépend au périmètre de chaque profil J4', function (string $profil, array $attendus) {
    $this->actingAs($this->jeu->{$profil});

    $numeros = Analyse::query()->orderBy('num_echantillon_labo')->pluck('num_echantillon_labo')->all();
    $n = count($attendus);

    expect($numeros)->toBe($attendus)
        ->and(Prelevement::query()->count())->toBe($n)
        ->and(Resultat::query()->count())->toBe($n)
        ->and(Interpretation::query()->count())->toBe($n)
        ->and(Alerte::query()->count())->toBe($n);
})->with([
    'technicien du GDS 24' => ['technicien24', ['HA-24A', 'HA-24B']],
    'technicien du GDS 47' => ['technicien47', ['HA-47']],
    'chargé de mission bi-GDS' => ['chargeDeMission', ['HA-24A', 'HA-24B', 'HA-47']],
    'vétérinaire conseil du GDS 24' => ['veterinaireConseil24', ['HA-24A', 'HA-24B']],
    'super-administrateur' => ['adminGlobal', ['HA-24A', 'HA-24B', 'HA-47']],
    'éleveur de 24A' => ['eleveur24a', ['HA-24A']],
    'éleveur du 47' => ['eleveur47', ['HA-47']],
    'vétérinaire consenti sur 24A' => ['veterinaire', ['HA-24A']],
]);

it('retire au vétérinaire les bulletins de l\'élevage dès la révocation du consentement', function () {
    $this->actingAs($this->jeu->veterinaire);
    expect(Analyse::query()->count())->toBe(1);

    $this->jeu->consentementVeterinaire->update(['revoque_le' => now()]);

    expect(Analyse::query()->count())->toBe(0)
        ->and(Alerte::query()->count())->toBe(0);
});

it('rend un bulletin hors périmètre introuvable par son identifiant', function () {
    $analyse47 = DB::table('fourrage.analyse')->where('num_echantillon_labo', 'HA-47')->value('id');

    $this->actingAs($this->jeu->technicien24);

    expect(Analyse::query()->find($analyse47))->toBeNull();
});

it('cloisonne les dépôts de bulletins par GDS', function () {
    expect(array_keys((new ImportBulletin)->getGlobalScopes()))->toContain(GdsScope::class);

    foreach ([24, 47] as $gds) {
        ImportBulletin::query()->create([
            'gds_id' => $gds, 'nom_fichier' => "bulletin-{$gds}.pdf", 'hash_sha256' => str_repeat((string) $gds, 32), 'taille_octets' => 1,
        ]);
    }

    $this->actingAs($this->jeu->technicien47);
    expect(ImportBulletin::query()->pluck('gds_id')->all())->toBe([47]);

    $this->actingAs($this->jeu->chargeDeMission);
    expect(ImportBulletin::query()->count())->toBe(2);
});
