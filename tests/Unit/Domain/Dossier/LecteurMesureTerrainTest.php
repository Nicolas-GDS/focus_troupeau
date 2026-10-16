<?php

use App\Domain\Dossier\ConditionLecture;
use App\Domain\Dossier\LecteurMesureTerrain;
use App\Domain\Dossier\LectureMesure;
use App\Domain\Dossier\LecturesCombinees;
use App\Domain\Dossier\RegleLecture;
use App\Domain\Dossier\Repere;
use App\Domain\Dossier\RepereMesureTerrain;
use App\Domain\Referentiels\StatutEpistemique;

// RC-1.5 — lecture des mesures terrain et lectures combinées ; cas de la présentation Forgeneuf (diapositives 12, 15, 16).

function sourceC(): Repere
{
    return new Repere(null, StatutEpistemique::ConsensusProfessionnel, false);
}

function repereMesure(int $id, string $mesure, ?float $basse, ?float $haute, ?string $stade = 'prepa_velage', ?string $espece = 'BOV'): RepereMesureTerrain
{
    return new RepereMesureTerrain($id, $mesure, $basse, $haute, sourceC(), $espece, $stade);
}

/** @param  list<ConditionLecture>  $conditions */
function regle(string $code, int $ordre, array $conditions): RegleLecture
{
    return new RegleLecture($code, $ordre, $conditions, "Libellé {$code}", sourceC());
}

function siLecture(string $mesure, LectureMesure $lecture): ConditionLecture
{
    return new ConditionLecture($mesure, lecture: $lecture);
}

function siValeur(string $mesure, string $op, float $valeur): ConditionLecture
{
    return new ConditionLecture($mesure, operateur: $op, valeur: $valeur);
}

it('lit une mesure par rapport au repère de son stade, bornes incluses', function (float $valeur, LectureMesure $attendu) {
    expect((new LecteurMesureTerrain)->lire($valeur, repereMesure(1, 'GLYCEMIE', 60, 70)))->toBe($attendu);
})->with([
    'glycémie 36 (Forgeneuf) : bas' => [36, LectureMesure::Bas],
    'borne basse 60 : normal' => [60, LectureMesure::Normal],
    'borne haute 70 : normal' => [70, LectureMesure::Normal],
    'au-delà : haut' => [70.5, LectureMesure::Haut],
]);

it('lit les mesures de Forgeneuf en préparation au vêlage', function (string $mesure, ?float $basse, ?float $haute, float $valeur, LectureMesure $attendu) {
    expect((new LecteurMesureTerrain)->lire($valeur, repereMesure(1, $mesure, $basse, $haute)))->toBe($attendu);
})->with([
    'pH des bouses 5,69 : bas' => ['PH_BOUSE', 6.5, 6.8, 5.69, LectureMesure::Bas],
    'redox −44 mV : haut (peu réducteur)' => ['REDOX_BOUSE', -220, -185, -44, LectureMesure::Haut],
    'BOH 0,5 : haut (moins de 0,4)' => ['BOH', null, 0.4, 0.5, LectureMesure::Haut],
    'BOH 0,2 : normal' => ['BOH', null, 0.4, 0.2, LectureMesure::Normal],
    'urée 92 mg/l : bas' => ['UREE', 150, 250, 92, LectureMesure::Bas],
]);

it('retient le repère le plus précis : espèce × stade, stade, espèce, aucun', function () {
    $reperes = [
        repereMesure(1, 'PH_URINE', 1, 2, null, null),
        repereMesure(2, 'PH_URINE', 3, 4, null, 'BOV'),
        repereMesure(3, 'PH_URINE', 5, 6, 'tarie', null),
        repereMesure(4, 'PH_URINE', 7.8, 8.3, 'tarie', 'BOV'),
    ];
    $lecteur = new LecteurMesureTerrain;

    expect($lecteur->repere('PH_URINE', 'BOV', 'tarie', $reperes)?->id)->toBe(4)
        ->and($lecteur->repere('PH_URINE', 'OVI', 'tarie', $reperes)?->id)->toBe(3)
        ->and($lecteur->repere('PH_URINE', 'BOV', 'lactation', $reperes)?->id)->toBe(2)
        ->and($lecteur->repere('PH_URINE', 'OVI', null, $reperes)?->id)->toBe(1)
        ->and($lecteur->repere('GLYCEMIE', 'BOV', 'tarie', $reperes))->toBeNull();
});

it('calcule la médiane d\'un groupe de mesures', function () {
    expect(LecturesCombinees::mediane([49.0, 36.0, 41.0]))->toBe(41.0)
        ->and(LecturesCombinees::mediane([36.0, 49.0, 41.0, 45.0]))->toBe(43.0)
        ->and(LecturesCombinees::mediane([]))->toBeNull();
});

it('propose la lecture combinée de Forgeneuf et écarte les lectures qu\'elle contient', function () {
    $regles = [
        regle('PH_BOUSE_BAS', 10, [siLecture('PH_BOUSE', LectureMesure::Bas)]),
        regle('REDOX_BOUSE_HAUT', 20, [siLecture('REDOX_BOUSE', LectureMesure::Haut)]),
        regle('GLYCEMIE_BASSE', 30, [siLecture('GLYCEMIE', LectureMesure::Bas)]),
        regle('UREE_BASSE', 50, [siLecture('UREE', LectureMesure::Bas)]),
        regle('GLY_BASSE_BOH_HAUT', 100, [siValeur('GLYCEMIE', '<', 45), siLecture('BOH', LectureMesure::Haut)]),
        regle('GLY_BASSE_UREE_BASSE_PH_BAS', 140, [
            siLecture('GLYCEMIE', LectureMesure::Bas), siLecture('UREE', LectureMesure::Bas), siLecture('PH_BOUSE', LectureMesure::Bas),
        ]),
    ];
    $medianes = ['GLYCEMIE' => 42.0, 'UREE' => 100.0, 'PH_BOUSE' => 5.8, 'BOH' => 0.35, 'REDOX_BOUSE' => -90.0];
    $lectures = [
        'GLYCEMIE' => LectureMesure::Bas, 'UREE' => LectureMesure::Bas, 'PH_BOUSE' => LectureMesure::Bas,
        'BOH' => LectureMesure::Normal, 'REDOX_BOUSE' => LectureMesure::Haut,
    ];

    $codes = array_map(fn (RegleLecture $r) => $r->code, (new LecturesCombinees)->proposer($medianes, $lectures, $regles));

    expect($codes)->toBe(['REDOX_BOUSE_HAUT', 'GLY_BASSE_UREE_BASSE_PH_BAS']);
});

it('évalue les conditions sur la valeur, bornes comprises selon l\'opérateur', function () {
    $moyen = regle('TAMIS_MOYEN', 131, [siValeur('TAMIS_1', '>=', 10), siValeur('TAMIS_1', '<=', 15)]);
    $lecteur = new LecturesCombinees;

    expect($lecteur->proposer(['TAMIS_1' => 10.0], [], [$moyen]))->toHaveCount(1)
        ->and($lecteur->proposer(['TAMIS_1' => 15.0], [], [$moyen]))->toHaveCount(1)
        ->and($lecteur->proposer(['TAMIS_1' => 17.0], [], [$moyen]))->toBe([])
        ->and($lecteur->proposer([], [], [$moyen]))->toBe([]);
});

it('refuse une condition qui porte à la fois sur la lecture et sur la valeur, ou sur aucune', function () {
    expect(fn () => new ConditionLecture('UREE'))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ConditionLecture('UREE', LectureMesure::Bas, '<', 1))->toThrow(InvalidArgumentException::class)
        ->and(fn () => new ConditionLecture('UREE', operateur: '=', valeur: 1))->toThrow(InvalidArgumentException::class);
});
