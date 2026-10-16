<?php

use App\Domain\Dossier\ClasseBilan;
use App\Domain\Dossier\Croisement;
use App\Domain\Dossier\FamilleCauseProposee;
use App\Domain\Dossier\LienTroubleCause;
use App\Domain\Dossier\Repere;
use App\Domain\Dossier\TroubleCroisement;
use App\Domain\Interpretation\EvenementSanitaire;
use App\Domain\Referentiels\StatutEpistemique;

// EF-M2-05 — étape « croiser » : troubles présents et familles de cause (grille NBVC, diapositive 18).

/** @return list<TroubleCroisement> */
function troublesNbvc(): array
{
    return [
        new TroubleCroisement('non_delivrance', 'Non-délivrance', 10, ['NON_DELIVRANCE'], ['retention_placentaire']),
        new TroubleCroisement('metrite', 'Métrite', 20, ['METRITE']),
        new TroubleCroisement('troubles_reproduction', 'Troubles de reproduction', 30, ['IVV'], ['infertilite']),
        new TroubleCroisement('avortement', 'Avortement', 40, ['AVORTEMENT'], ['avortement']),
        new TroubleCroisement('veaux', 'Veaux : mortalité, maladies', 50, ['MORTALITE_VEAUX_0_12M'], ['diarrhee_veaux']),
        new TroubleCroisement('boiterie', 'Boiterie', 60, [], ['boiteries']),
    ];
}

/** @return list<LienTroubleCause> grille de la diapositive 18 */
function grilleNbvc(): array
{
    $grille = [
        'non_delivrance' => [3, 3, 1, 0, 0],
        'metrite' => [2, 2, 2, 1, 3],
        'troubles_reproduction' => [2, 2, 3, 1, 1],
        'avortement' => [2, 0, 0, 1, 3],
        'veaux' => [3, 1, 1, 1, 3],
        'boiterie' => [1, 3, 2, 3, 0],
    ];
    $source = new Repere(null, StatutEpistemique::ConsensusProfessionnel, false);
    $liens = [];

    foreach ($grille as $trouble => $forces) {
        foreach (LienTroubleCause::FAMILLES as $i => $famille) {
            $liens[] = new LienTroubleCause($trouble, $famille, $forces[$i], $source);
        }
    }

    return $liens;
}

it('repère les troubles présents par le bilan sanitaire et par les événements de la fenêtre', function () {
    $classes = [
        'NON_DELIVRANCE' => ClasseBilan::Degradee,
        'METRITE' => ClasseBilan::TresDegradee,
        'AVORTEMENT' => ClasseBilan::Favorable,
        'MORTALITE_VEAUX_0_12M' => ClasseBilan::TresDegradee,
    ];
    $evenements = [
        new EvenementSanitaire('boiteries', 'BOV', new DateTimeImmutable('2026-04-15')),
        new EvenementSanitaire('avortement', 'BOV', new DateTimeImmutable('2024-01-10')),   // hors fenêtre
    ];

    $presents = (new Croisement)->troublesPresents($classes, $evenements, troublesNbvc(), new DateTimeImmutable('2026-09-03'), 12);

    expect($presents)->toBe(['non_delivrance', 'metrite', 'veaux', 'boiterie']);
});

it('additionne les forces par famille sur les troubles présents de Forgeneuf', function () {
    $familles = (new Croisement)->familles(['non_delivrance', 'metrite', 'avortement', 'veaux'], grilleNbvc());

    expect(array_map(fn (FamilleCauseProposee $f) => [$f->familleCause, $f->force], $familles))->toBe([
        ['immunite_oligo', 10],
        ['infectieux', 9],
        ['metabolisme_mineral', 6],
        ['metabolisme_energetique', 4],
        ['confort', 3],
    ])->and($familles[0]->troubles)->toBe(['non_delivrance' => 3, 'metrite' => 2, 'avortement' => 2, 'veaux' => 3]);
});

it('ne propose aucune famille sans trouble présent', function () {
    expect((new Croisement)->familles([], grilleNbvc()))->toBe([]);
});
