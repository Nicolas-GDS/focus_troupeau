<?php

use App\Domain\Interpretation\Classement;
use App\Domain\Interpretation\CompatibiliteEvenements;
use App\Domain\Interpretation\EvenementSanitaire;
use App\Domain\Interpretation\LienCompatibilite;
use App\Domain\Interpretation\Seuil;
use App\Domain\Interpretation\ValeurAnalysee;

// RC-1.4 — compatibilité entre alertes et événements sanitaires (chapitre 11, décisions du 15/10/2026).

function alerte(string $parametre, string $sens, float $valeur = 1.0): Classement
{
    return new Classement(new ValeurAnalysee($parametre, $valeur), new Seuil(1, $parametre, 'classe', 1, sens: $sens));
}

function evenement(string $type, string $espece = 'BOV', string $date = '2026-06-01', ?string $categorie = null): EvenementSanitaire
{
    return new EvenementSanitaire($type, $espece, new DateTimeImmutable($date), $categorie);
}

function correspondances(array $alertes, array $evenements, array $liens, string $reference = '2026-10-15'): array
{
    return (new CompatibiliteEvenements)->correspondances($alertes, $evenements, $liens, new DateTimeImmutable($reference), 12);
}

it('relie une alerte à un événement par un lien de même paramètre, même sens et même trouble', function () {
    $liens = [new LienCompatibilite('MG', 'carence', 'tetanie', 3, ['BOV', 'OVI'])];

    expect(correspondances([alerte('MG', 'bas')], [evenement('tetanie')], $liens))->toHaveCount(1)
        ->and(correspondances([alerte('MG', 'haut')], [evenement('tetanie')], $liens))->toBe([])
        ->and(correspondances([alerte('MG', 'bas')], [evenement('boiteries')], $liens))->toBe([])
        ->and(correspondances([alerte('K', 'bas')], [evenement('tetanie')], $liens))->toBe([]);
});

it('fait correspondre un seuil bas aux liens de carence ou de ratio bas, un seuil haut aux liens d\'excès ou de ratio élevé', function () {
    $liens = [
        new LienCompatibilite('CA_P', 'ratio_bas', 'urolithiase', 3),
        new LienCompatibilite('BACA', 'exces', 'hypocalcemie', 3),
    ];

    expect(correspondances([alerte('CA_P', 'bas')], [evenement('urolithiase')], $liens))->toHaveCount(1)
        ->and(correspondances([alerte('BACA', 'haut')], [evenement('hypocalcemie')], $liens))->toHaveCount(1)
        ->and(correspondances([alerte('CA_P', 'haut')], [evenement('urolithiase')], $liens))->toBe([]);
});

it('limite le lien aux espèces et catégories qu\'il concerne', function () {
    $liens = [new LienCompatibilite('CU', 'exces', 'toxicite_cuivre', 3, ['OVI'], ['BREBIS_ALL', 'AGNEAU'])];

    expect(correspondances([alerte('CU', 'haut')], [evenement('toxicite_cuivre', 'OVI', categorie: 'AGNEAU')], $liens))->toHaveCount(1)
        ->and(correspondances([alerte('CU', 'haut')], [evenement('toxicite_cuivre', 'OVI')], $liens))->toHaveCount(1)
        ->and(correspondances([alerte('CU', 'haut')], [evenement('toxicite_cuivre', 'OVI', categorie: 'BREBIS_LAIT')], $liens))->toBe([])
        ->and(correspondances([alerte('CU', 'haut')], [evenement('toxicite_cuivre', 'BOV')], $liens))->toBe([]);
});

it('ne retient que les événements des 12 mois précédant la date de référence', function () {
    $liens = [new LienCompatibilite('MG', 'carence', 'tetanie', 3)];

    expect(correspondances([alerte('MG', 'bas')], [evenement('tetanie', date: '2025-10-15')], $liens))->toHaveCount(1)
        ->and(correspondances([alerte('MG', 'bas')], [evenement('tetanie', date: '2025-10-14')], $liens))->toBe([])
        ->and(correspondances([alerte('MG', 'bas')], [evenement('tetanie', date: '2026-10-16')], $liens))->toBe([]);
});

it('ne rehausse qu\'avec les liens de force au moins égale au minimum, mais rend toutes les correspondances', function () {
    $compatibilite = new CompatibiliteEvenements;
    $liens = [
        new LienCompatibilite('CA', 'carence', 'retention_placentaire', 1),
        new LienCompatibilite('SE', 'carence', 'retention_placentaire', 2),
    ];
    $toutes = correspondances([alerte('CA', 'bas'), alerte('SE', 'bas')], [evenement('retention_placentaire')], $liens);

    expect($toutes)->toHaveCount(2)
        ->and($compatibilite->parametresPourRehaussement($toutes, 2))->toBe(['SE'])
        ->and($compatibilite->parametresPourRehaussement($toutes, 1))->toBe(['CA', 'SE']);
});
