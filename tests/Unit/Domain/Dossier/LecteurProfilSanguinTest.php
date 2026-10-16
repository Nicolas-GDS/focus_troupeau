<?php

use App\Domain\Dossier\LecteurProfilSanguin;
use App\Domain\Dossier\LectureProfil;
use App\Domain\Dossier\Repere;
use App\Domain\Dossier\RepereProfilSanguin;
use App\Domain\Referentiels\StatutEpistemique;

// RC-1.5 — lecture des profils sanguins ; cas de la présentation Forgeneuf (diapositive 13).

function repereSerique(string $element, float $basse, float $haute, ?string $espece = 'BOV', int $id = 1): RepereProfilSanguin
{
    return new RepereProfilSanguin($id, $element, 'serum', $basse, $haute, 'mg/l',
        new Repere(null, StatutEpistemique::ConsensusProfessionnel, false), $espece);
}

function lecteurProfil(): LecteurProfilSanguin
{
    return new LecteurProfilSanguin(partCarenceSevere: 0.5, partNormalBas: 0.25);
}

it('lit les profils de la présentation Forgeneuf', function (string $element, float $basse, float $haute, float $valeur, LectureProfil $attendu) {
    expect(lecteurProfil()->lire($valeur, repereSerique($element, $basse, $haute)))->toBe($attendu);
})->with([
    'sélénium 0,03 : carence sévère (50 % de la borne basse, décision du 16/10/2026)' => ['selenium', 0.06, 0.36, 0.03, LectureProfil::CarenceSevere],
    'iode 0,067 : carence' => ['iode', 0.1, 1.7, 0.067, LectureProfil::Carence],
    'iode 0,049 : carence sévère (sous 0,05)' => ['iode', 0.1, 1.7, 0.049, LectureProfil::CarenceSevere],
    'fer 2,5 : excès' => ['fer', 1.0, 2.0, 2.5, LectureProfil::Exces],
    'cuivre 0,61 : normal, partie basse (sous 0,63)' => ['cupremie', 0.43, 1.23, 0.61, LectureProfil::NormalBas],
    'cuivre 0,70 : normal' => ['cupremie', 0.43, 1.23, 0.70, LectureProfil::Normal],
    'zinc 0,85 : normal, partie basse (sous 0,98)' => ['zincemie', 0.78, 1.58, 0.85, LectureProfil::NormalBas],
]);

it('tient les bornes : basse et haute incluses dans l\'intervalle de référence', function () {
    $repere = repereSerique('selenium', 0.06, 0.36);

    expect(lecteurProfil()->lire(0.06, $repere))->toBe(LectureProfil::NormalBas)
        ->and(lecteurProfil()->lire(0.36, $repere))->toBe(LectureProfil::Normal)
        ->and(lecteurProfil()->lire(0.0301, $repere))->toBe(LectureProfil::Carence);
});

it('prend les fractions dans les référentiels, pas dans le code', function () {
    $lecteur = new LecteurProfilSanguin(partCarenceSevere: 0.4, partNormalBas: 0.5);
    $repere = repereSerique('selenium', 0.06, 0.36);

    expect($lecteur->lire(0.03, $repere))->toBe(LectureProfil::Carence)
        ->and($lecteur->lire(0.20, $repere))->toBe(LectureProfil::NormalBas);
});

it('retient le repère de l\'espèce avant le repère toutes espèces', function () {
    $reperes = [repereSerique('selenium', 0.01, 0.1, null, 1), repereSerique('selenium', 0.06, 0.36, 'BOV', 2)];

    expect(lecteurProfil()->repere('selenium', 'serum', 'BOV', $reperes)?->id)->toBe(2)
        ->and(lecteurProfil()->repere('selenium', 'serum', 'OVI', $reperes)?->id)->toBe(1)
        ->and(lecteurProfil()->repere('selenium', 'sang', 'BOV', $reperes))->toBeNull();
});
