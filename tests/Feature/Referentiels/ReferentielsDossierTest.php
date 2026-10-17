<?php

use App\Domain\Dossier\ClasseBilan;
use App\Domain\Dossier\Croisement;
use App\Domain\Dossier\LecteurMesureTerrain;
use App\Domain\Dossier\LecteurProfilSanguin;
use App\Domain\Dossier\LectureMesure;
use App\Domain\Dossier\LectureProfil;
use App\Domain\Dossier\LecturesCombinees;
use App\Domain\Dossier\PositionBilanSanitaire;
use App\Domain\Dossier\RegleLecture;
use App\Domain\Referentiels\Referentiels;
use App\Domain\Referentiels\StatutEpistemique;
use Database\Seeders\Ref\ReferentielsDossierSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// Référentiels du dossier (lot L3, étape 2) chargés par la migration depuis database/seeders/ref/dossier :
// transcription de la présentation Forgeneuf (décisions du 16/10/2026) et lecture de bout en bout du cas.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->ref = app(Referentiels::class);
});

it('charge les référentiels du dossier en version 4, au statut [C] et à valider', function () {
    expect(DB::table('ref.repere_mesure_terrain')->count())->toBe(37)
        ->and(DB::table('ref.grille_lecture_mesure')->count())->toBe(16)
        ->and(DB::table('ref.repere_analyse_animale')->count())->toBe(5)
        ->and(DB::table('ref.seuil_bilan_sanitaire')->count())->toBe(7)
        ->and(DB::table('ref.grille_trouble_cause')->count())->toBe(30)
        ->and(DB::table('ref.mesure_terrain')->count())->toBe(13)
        ->and($this->ref->versionGrilleCourante())->toBe('5');

    foreach ($this->ref->reperesMesuresTerrain() as $repere) {
        expect($repere->source->statutEpistemique)->toBe(StatutEpistemique::ConsensusProfessionnel)
            ->and($repere->source->aValider())->toBeTrue()
            ->and($repere->source->referenceId)->not->toBeNull();
    }
});

it('retient les choix d\'unités et de repères du 16/10/2026', function () {
    expect(DB::table('ref.mesure_terrain')->where('code', 'UREE')->value('unite'))->toBe('mg/l')
        ->and(DB::table('ref.mesure_terrain')->where('code', 'BOH')->value('unite'))->toBe('mmol/l')
        ->and((float) DB::table('ref.repere_mesure_terrain')->where('mesure_code', 'BRIX_COLOSTRUM')->value('borne_basse'))->toBe(22.0)
        ->and(DB::table('ref.seuil_bilan_sanitaire')->where('indicateur_code', 'MORTALITE_VEAUX_0_12M')->first(['s1', 's2']))
        ->toEqual((object) ['s1' => null, 's2' => '5'])
        ->and(DB::table('ref.seuil_bilan_sanitaire')->where('indicateur_code', 'IVV')->exists())->toBeFalse();
});

it('ne crée de seuil d\'eau que pour le fer, non validé ; les autres paramètres sont sans repère', function () {
    $seuilsEau = array_values(array_filter($this->ref->seuilsActifs(), fn ($s) => $s->nature === 'eau'));

    expect(array_map(fn ($s) => [$s->parametreCode, $s->classe, $s->valide], $seuilsEau))
        ->toBe([['EAU_FER', 'conforme', false], ['EAU_FER', 'eleve', false]])
        ->and(DB::table('ref.parametre')->where('famille_code', 'EAU')->count())->toBe(7)
        ->and(collect($this->ref->seuilsActifs())->where('nature', '!=', 'eau')->every(fn ($s) => $s->valide))->toBeTrue();
});

it('lit le cas Forgeneuf de bout en bout avec les référentiels chargés', function () {
    $lecteur = new LecteurMesureTerrain;
    $reperes = $this->ref->reperesMesuresTerrain();
    $mesures = ['GLYCEMIE' => [36, 41, 42, 45, 49], 'UREE' => [92, 96, 100, 104, 109], 'PH_BOUSE' => [5.69, 5.75, 5.8, 5.88, 5.94],
        'BOH' => [0.2, 0.3, 0.3, 0.4, 0.5], 'REDOX_BOUSE' => [-44, -70, -90, -110, -136]];
    $medianes = [];
    $lectures = [];

    foreach ($mesures as $code => $valeurs) {
        $medianes[$code] = LecturesCombinees::mediane(array_map('floatval', $valeurs));
        $lectures[$code] = $lecteur->lire($medianes[$code], $lecteur->repere($code, 'BOV', 'prepa_velage', $reperes));
    }

    $regles = (new LecturesCombinees)->proposer($medianes, $lectures, $this->ref->reglesLectureMesures(), 'BOV', 'prepa_velage');

    expect($lectures['BOH'])->toBe(LectureMesure::Normal)
        ->and(array_map(fn (RegleLecture $r) => $r->libellePropose, $regles))->toBe([
            'Fermentation digestive peu réductrice',
            'Manque d\'azote avec excès relatif de sucres probable',
        ]);

    $profil = new LecteurProfilSanguin(
        (float) $this->ref->parametre('profil.part_carence_severe'),
        (float) $this->ref->parametre('profil.part_normal_bas'),
    );
    $repereSe = $profil->repere('selenium', 'serum', 'BOV', $this->ref->reperesProfilsSanguins());
    expect($profil->lire(0.03, $repereSe))->toBe(LectureProfil::CarenceSevere);

    $position = new PositionBilanSanitaire;
    $seuils = $this->ref->seuilsBilanSanitaire();
    $classes = [];
    foreach (['NON_DELIVRANCE' => 12.0, 'METRITE' => 12.0, 'AVORTEMENT' => 2.5, 'MORTALITE_VEAUX_0_12M' => 6.0, 'RETOURNEMENT' => 2.5] as $code => $taux) {
        $classes[$code] = $position->classer($taux, $position->seuil($code, 'BOV', 'viande', $seuils));
    }
    expect($classes['RETOURNEMENT'])->toBe(ClasseBilan::TresDegradee)
        ->and($classes['NON_DELIVRANCE'])->toBe(ClasseBilan::Degradee);

    $croisement = new Croisement;
    $presents = $croisement->troublesPresents($classes, [], $this->ref->troublesCroisement(), new DateTimeImmutable('2026-09-03'), 12);
    $familles = $croisement->familles($presents, $this->ref->liensTroubleCause());

    expect($presents)->toBe(['non_delivrance', 'metrite', 'avortement', 'veaux'])
        ->and($familles[0]->familleCause)->toBe('immunite_oligo');
});

it('se rejoue sans dupliquer les lignes', function () {
    (new ReferentielsDossierSeeder)->run();

    expect(DB::table('ref.repere_mesure_terrain')->count())->toBe(37)
        ->and(DB::table('ref.grille_trouble_cause')->count())->toBe(30)
        ->and(DB::table('ref.seuil_interpretation')->where('nature', 'eau')->count())->toBe(2)
        ->and(DB::table('ref.reference_biblio')->where('cle', 'GDS24_FORGENEUF2026')->count())->toBe(1);
});

it('lit les conditions des lectures combinées depuis leur forme texte', function () {
    expect(ReferentielsDossierSeeder::conditions('GLYCEMIE<45|BOH:haut'))->toBe([
        ['mesure' => 'GLYCEMIE', 'op' => '<', 'valeur' => 45.0],
        ['mesure' => 'BOH', 'lecture' => 'haut'],
    ])->and(fn () => ReferentielsDossierSeeder::conditions('GLYCEMIE=45'))->toThrow(RuntimeException::class);
});
