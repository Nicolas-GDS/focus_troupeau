<?php

use App\Domain\Interpretation\PlagePlausibilite;
use App\Domain\Interpretation\Seuil;
use App\Domain\Referentiels\Edition\ControleGrille;
use App\Domain\Referentiels\Edition\Nomenclatures;
use App\Domain\Referentiels\Edition\SaisiePlage;
use App\Domain\Referentiels\Edition\SaisieSeuil;

// E24 — contrôles de cohérence de la grille (RC-1 « Départage et cohérence de la grille ») et des plages (RC-2).

function nomenclaturesE24(): Nomenclatures
{
    return new Nomenclatures(
        parametres: ['MAT', 'CU', 'MS'],
        familles: ['FOIN', 'ENRUB'],
        familleParType: ['FOIN_PP' => 'FOIN', 'FOIN_LUZ' => 'FOIN', 'ENRUB_PP' => 'ENRUB'],
        especes: ['BOV', 'OVI', 'CAP'],
        especeParCategorie: ['VL' => 'BOV', 'BREBIS_LAIT' => 'OVI'],
        references: [1, 2],
    );
}

/**
 * @param  array<string, mixed>  $surcharges
 */
function saisieSeuilE24(array $surcharges = []): SaisieSeuil
{
    return SaisieSeuil::depuis($surcharges + [
        'parametre_code' => 'MAT', 'nature' => 'nutritionnel', 'borne_max' => '80', 'classe' => 'faible',
        'gravite' => '1', 'sens' => 'bas', 'statut_epistemique' => 'consensus_professionnel',
    ])->normalisee(nomenclaturesE24());
}

it('accepte un seuil cohérent', function () {
    expect((new ControleGrille)->controlerSeuil(saisieSeuilE24(), [], nomenclaturesE24()))->toBe([]);
});

it('lit les nombres à la virgule et ramène les champs vides à null', function () {
    $saisie = saisieSeuilE24(['borne_min' => '0,65', 'borne_max' => '', 'famille_fourrage_code' => '']);

    expect($saisie->borneMin)->toBe(0.65)->and($saisie->borneMax)->toBeNull()->and($saisie->familleFourrageCode)->toBeNull();
});

it('refuse un chevauchement au même niveau de précision', function () {
    $actif = new Seuil(id: 11, parametreCode: 'MAT', classe: 'faible', gravite: 1, borneMax: 80.0, sens: 'bas');

    $erreurs = (new ControleGrille)->controlerSeuil(saisieSeuilE24(['borne_min' => '70', 'borne_max' => '90', 'classe' => 'moyen']), [$actif], nomenclaturesE24());

    expect($erreurs)->toHaveKey('chevauchement')->and($erreurs['chevauchement'])->toContain('n° 11');
});

it('accepte des intervalles contigus [min ; max[ et un chevauchement à un autre niveau de précision', function () {
    $actif = new Seuil(id: 11, parametreCode: 'MAT', classe: 'faible', gravite: 1, borneMax: 80.0, sens: 'bas');
    $controle = new ControleGrille;

    expect($controle->controlerSeuil(saisieSeuilE24(['borne_min' => '80', 'borne_max' => null, 'classe' => 'correct', 'sens' => 'haut']), [$actif], nomenclaturesE24()))->toBe([])
        ->and($controle->controlerSeuil(saisieSeuilE24(['famille_fourrage_code' => 'FOIN', 'borne_max' => '90']), [$actif], nomenclaturesE24()))->toBe([])
        ->and($controle->controlerSeuil(saisieSeuilE24(['espece_code' => 'OVI', 'borne_max' => '90']), [$actif], nomenclaturesE24()))->toBe([]);
});

it('compare le niveau de précision après déduction de la famille (type) et de l\'espèce (catégorie)', function () {
    // Seuil existant chargé avec la catégorie seule ; la saisie porte l'espèce et la catégorie : même niveau.
    $actif = new Seuil(id: 7, parametreCode: 'CU', classe: 'bas', gravite: 2, borneMax: 10.0, categorieCode: 'VL', sens: 'bas');
    $saisie = saisieSeuilE24(['parametre_code' => 'CU', 'espece_code' => 'BOV', 'categorie_code' => 'VL', 'borne_max' => '8']);

    expect((new ControleGrille)->controlerSeuil($saisie, [$actif], nomenclaturesE24()))->toHaveKey('chevauchement')
        ->and(saisieSeuilE24(['type_fourrage_code' => 'FOIN_LUZ'])->familleFourrageCode)->toBe('FOIN');
});

it('refuse les incohérences de nomenclature, de bornes et de listes', function (array $surcharges, string $champ) {
    expect((new ControleGrille)->controlerSeuil(saisieSeuilE24($surcharges), [], nomenclaturesE24()))->toHaveKey($champ);
})->with([
    'paramètre inconnu' => [['parametre_code' => 'XX'], 'parametre_code'],
    'type hors famille' => [['famille_fourrage_code' => 'ENRUB', 'type_fourrage_code' => 'FOIN_LUZ'], 'type_fourrage_code'],
    'type inconnu' => [['type_fourrage_code' => 'ZZZ'], 'type_fourrage_code'],
    'espèce incohérente avec la catégorie' => [['espece_code' => 'OVI', 'categorie_code' => 'VL'], 'categorie_code'],
    'bornes inversées' => [['borne_min' => '90', 'borne_max' => '80'], 'borne_max'],
    'bornes égales' => [['borne_min' => '80', 'borne_max' => '80'], 'borne_max'],
    'aucune borne' => [['borne_max' => null], 'borne_min'],
    'gravité hors 0-3' => [['gravite' => '4'], 'gravite'],
    'sens absent' => [['sens' => null], 'sens'],
    'sens inconnu' => [['sens' => 'milieu'], 'sens'],
    'nature inconnue' => [['nature' => 'autre'], 'nature'],
    'statut inconnu' => [['statut_epistemique' => 'certain'], 'statut_epistemique'],
    'référence inconnue' => [['reference_id' => '99'], 'reference_id'],
    'classe absente' => [['classe' => ''], 'classe'],
]);

it('exige la décision du comité technique', function () {
    expect((new ControleGrille)->controlerDecision('   '))->toHaveKey('decision')
        ->and((new ControleGrille)->controlerDecision('Comité du 15/10/2026'))->toBe([]);
});

it('contrôle les plages : bornes, unicité par paramètre et famille', function () {
    $controle = new ControleGrille;
    $actives = [new PlagePlausibilite('MS', 75, 95, 'FOIN')];
    $plage = fn (array $d) => SaisiePlage::depuis($d + ['parametre_code' => 'MS', 'statut_epistemique' => 'hypothese_locale']);

    expect($controle->controlerPlage($plage(['famille_fourrage_code' => 'ENRUB', 'borne_min' => '25', 'borne_max' => '80']), $actives, nomenclaturesE24()))->toBe([])
        ->and($controle->controlerPlage($plage(['famille_fourrage_code' => 'ENRUB', 'borne_min' => '5', 'borne_max' => '5']), $actives, nomenclaturesE24()))->toBe([])
        ->and($controle->controlerPlage($plage(['famille_fourrage_code' => 'FOIN', 'borne_min' => '70', 'borne_max' => '95']), $actives, nomenclaturesE24()))->toHaveKey('chevauchement')
        ->and($controle->controlerPlage($plage(['borne_min' => '95', 'borne_max' => '75']), $actives, nomenclaturesE24()))->toHaveKey('borne_max')
        ->and($controle->controlerPlage($plage(['famille_fourrage_code' => 'XX', 'borne_min' => '1']), $actives, nomenclaturesE24()))->toHaveKey('famille_fourrage_code');
});

it('affiche les intervalles à la française', function () {
    expect(ControleGrille::intervalle(null, 0.65, true))->toBe('[−∞ ; 0,65[')
        ->and(ControleGrille::intervalle(75, 95, false))->toBe('[75 ; 95]');
});
