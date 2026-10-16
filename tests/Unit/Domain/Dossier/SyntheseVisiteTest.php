<?php

use App\Domain\Dossier\ElementSynthese;
use App\Domain\Dossier\LecteurEau;
use App\Domain\Dossier\MessagePropose;
use App\Domain\Dossier\SyntheseVisite;
use App\Domain\Interpretation\Seuil;
use App\Domain\Interpretation\ValeurAnalysee;

// EF-M2-05 — synthèse de visite : messages hiérarchisés, niveau proposé ; EF-M1-04 — lecture de l'eau.

function element(string $origine, int $gravite, string $texte, string $reference): ElementSynthese
{
    return new ElementSynthese($origine, $gravite, $texte, $reference);
}

it('hiérarchise les messages par gravité, regroupe un même texte et se limite au maximum', function () {
    $elements = [
        element('lot', 1, 'MAT faible', 'fourrage.interpretation:1'),
        element('lot', 2, 'Cuivre : carence probable', 'fourrage.interpretation:1'),
        element('eau', 1, 'Fer de l\'eau au-dessus du repère', 'dossier.analyse_eau:7'),
        element('lot', 2, 'Cuivre : carence probable', 'fourrage.interpretation:2'),
        element('lot', 0, 'Valeur correcte', 'fourrage.interpretation:2'),
        element('lot', 3, 'Cuivre : toxicité ovins', 'fourrage.interpretation:3'),
    ];

    $messages = (new SyntheseVisite)->messages($elements, 3);

    expect(array_map(fn (MessagePropose $m) => [$m->ordre, $m->gravite, $m->texte, $m->pilier], $messages))->toBe([
        [1, 3, 'Cuivre : toxicité ovins', 'nutrition'],
        [2, 2, 'Cuivre : carence probable', 'nutrition'],
        [3, 1, 'MAT faible', 'nutrition'],
    ])->and($messages[1]->referencesObjet)->toBe(['fourrage.interpretation:1', 'fourrage.interpretation:2']);
});

it('place l\'eau après les lots à gravité égale, avec le pilier hydratation', function () {
    $messages = (new SyntheseVisite)->messages([
        element('eau', 1, 'Fer de l\'eau', 'dossier.analyse_eau:7'),
        element('lot', 1, 'MAT faible', 'fourrage.interpretation:1'),
    ], 5);

    expect(array_map(fn (MessagePropose $m) => [$m->origine, $m->pilier], $messages))->toBe([['lot', 'nutrition'], ['eau', 'hydratation']]);
});

it('propose le niveau de la visite : maximum des niveaux retenus des lots et de l\'eau', function () {
    $synthese = new SyntheseVisite;

    expect($synthese->niveauPropose([1, 2, 0], [element('eau', 1, 'Fer', 'dossier.analyse_eau:7')]))->toBe(2)
        ->and($synthese->niveauPropose([], [element('eau', 1, 'Fer', 'dossier.analyse_eau:7')]))->toBe(1)
        ->and($synthese->niveauPropose([], []))->toBe(0);
});

it('classe l\'eau par les seuils de nature « eau » seulement (fer 39 µg/l conforme, Forgeneuf)', function () {
    $seuils = [
        new Seuil(1, 'EAU_FER', 'conforme', 0, 0, 200, nature: 'eau', valide: false),
        new Seuil(2, 'EAU_FER', 'eleve', 1, 200, null, nature: 'eau', valide: false),
        new Seuil(3, 'EAU_FER', 'leurre', 3, null, null, familleFourrageCode: 'FOIN'),
        new Seuil(4, 'FE', 'contamination', 2, 350, null),
    ];
    $lecteur = new LecteurEau;

    $conforme = $lecteur->classer([new ValeurAnalysee('EAU_FER', 39)], $seuils);
    $eleve = $lecteur->classer([new ValeurAnalysee('EAU_FER', 250), new ValeurAnalysee('EAU_NITRATES', 12)], $seuils);

    expect($conforme[0]->seuil->classe)->toBe('conforme')
        ->and($eleve)->toHaveCount(1)
        ->and($eleve[0]->seuil->classe)->toBe('eleve')
        ->and($eleve[0]->seuil->valide)->toBeFalse();
});
