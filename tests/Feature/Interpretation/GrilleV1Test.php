<?php

use App\Domain\Interpretation\Classeur;
use App\Domain\Interpretation\ContexteLot;
use App\Domain\Interpretation\NiveauGlobal;
use App\Domain\Interpretation\Plausibilite;
use App\Domain\Interpretation\Ratios;
use App\Domain\Interpretation\ValeurAnalysee;
use App\Domain\Referentiels\Referentiels;
use App\Domain\Referentiels\StatutEpistemique;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

// Domaine d'interprétation appliqué aux référentiels réels (grille v1 du document 02, plages de RC-2) :
// T-02 (RC-2), T-03 (RC-1, EF-M2-02), cuivre des ovins (RC-1.4).
uses(RefreshDatabase::class);

function referentiels(): Referentiels
{
    return app(Referentiels::class);
}

/**
 * @param  array<string, float>  $valeurs
 * @return list<ValeurAnalysee>
 */
function bulletin(array $valeurs): array
{
    return array_map(fn (string $code, float $v) => new ValeurAnalysee($code, $v), array_keys($valeurs), $valeurs);
}

it('T-02 : bloque le bulletin HA25-42515 sur ses quatre valeurs hors plage (Na, Cu, Mn, Fe)', function () {
    $valeurs = bulletin(['MS' => 86.0, 'MAT' => 110, 'NA' => 13.6, 'CU' => 57.9, 'MN' => 1765, 'FE' => 9012, 'K' => 20, 'ZN' => 40]);

    $anomalies = (new Plausibilite)->controler($valeurs, 'FOIN', referentiels()->plagesPlausibiliteActives());

    expect(array_map(fn ($a) => $a['valeur']->parametreCode, $anomalies))->toBe(['NA', 'CU', 'MN', 'FE']);
});

it('T-03 : foin MAT 61 g, Cu 4,2 mg, Fe 166 mg, K 18 g → MAT faible (1), Cu carence probable (2), niveau proposé 2', function () {
    $lot = new ContexteLot('FOIN_PP', referentiels()->familleDuType('FOIN_PP'));
    $classements = (new Classeur)->classerTout(
        bulletin(['MAT' => 61, 'CU' => 4.2, 'FE' => 166, 'K' => 18]),
        $lot,
        referentiels()->seuilsActifs(),
    );
    $alertes = array_values(array_filter($classements, fn ($c) => $c->produitUneAlerte()));

    $resume = array_map(fn ($c) => [$c->valeur->parametreCode, $c->seuil->classe, $c->seuil->gravite], $alertes);
    expect($resume)->toBe([['MAT', 'faible', 1], ['CU', 'carence_probable', 2]]);

    $niveau = (new NiveauGlobal)->proposer(
        array_map(fn ($c) => $c->seuil->gravite, $alertes),
        array_map(fn ($c) => $c->valeur->parametreCode, $alertes),
        [],
        $lot,
        4.2,
        null,
    );
    expect($niveau->niveau)->toBe(2);
});

it('porte avec chaque alerte son message, sa référence et son statut épistémique (EF-M2-02, EF-GEN-02)', function () {
    $classement = (new Classeur)->classer(new ValeurAnalysee('CU', 4.2), new ContexteLot('FOIN_PP', 'FOIN'), referentiels()->seuilsActifs());

    expect($classement?->seuil->message)->toContain('Cu < 7 mg/kg MS')
        ->and($classement?->seuil->statutEpistemique)->toBe(StatutEpistemique::PublieSourceSecondaire)
        ->and($classement?->seuil->referenceId)->not->toBeNull();
});

it('classe le cuivre d\'un lot pour brebis sur le seuil ovin et propose le niveau 3', function () {
    $especeBrebis = referentiels()->especeDeLaCategorie('BREBIS_ALL');
    $lot = new ContexteLot('FOIN_PP', 'FOIN', $especeBrebis, 'BREBIS_ALL');
    $seuils = referentiels()->seuilsActifs();
    $seuilOvin = collect($seuils)->first(fn ($s) => $s->parametreCode === 'CU' && $s->especeCode === 'OVI');

    $classement = (new Classeur)->classer(new ValeurAnalysee('CU', 23.0), $lot, $seuils);
    $niveau = (new NiveauGlobal)->proposer([$classement?->seuil->gravite ?? 0], ['CU'], [], $lot, 23.0, $seuilOvin?->borneMin);

    expect($especeBrebis)->toBe('OVI')
        ->and($classement?->seuil->classe)->toBe('toxicite_ovins')
        ->and($niveau->niveau)->toBe(3);
});

it('laisse sans alerte le même cuivre pour des bovins (23 mg : sous la valeur anormale de 30 mg)', function () {
    $classement = (new Classeur)->classer(new ValeurAnalysee('CU', 23.0), new ContexteLot('FOIN_PP', 'FOIN', 'BOV', 'VA'), referentiels()->seuilsActifs());

    expect($classement === null || ! $classement->produitUneAlerte())->toBeTrue();
});

it('ne contient aucun chevauchement de seuils au même niveau de précision dans la grille v1', function () {
    $seuils = referentiels()->seuilsActifs();
    $chevauchements = [];

    foreach ($seuils as $i => $a) {
        foreach (array_slice($seuils, $i + 1) as $b) {
            if ($a->chevauche($b)) {
                $chevauchements[] = "{$a->parametreCode} : {$a->classe} / {$b->classe}";
            }
        }
    }

    expect($chevauchements)->toBe([]);
});

it('calcule les ratios d\'un bulletin réel avant classement (Ca/P de la grille)', function () {
    $ratios = (new Ratios)->calculer(bulletin(['CA' => 6.0, 'P' => 2.4]));

    expect($ratios[0]->parametreCode)->toBe('CA_P')
        ->and(DB::table('ref.parametre')->where('code', 'CA_P')->exists())->toBeTrue();
});

it('lit la version de grille, les paramètres par défaut et les correspondances des référentiels', function () {
    expect(referentiels()->versionGrilleCourante())->toBe('1')
        ->and(referentiels()->parametre('interpretation.fenetre_evenements_mois'))->toBe(12.0)
        ->and(referentiels()->parametre('cle.inconnue'))->toBeNull()
        ->and(referentiels()->familleDuType('ENRUB_LUZ'))->toBe('ENRUB')
        ->and(referentiels()->parametresCompatibles(['non_delivrance']))->toBe([]);
});

it('préfère la valeur par défaut propre au GDS à celle de l\'instance', function () {
    DB::table('app.parametre_defaut')->insert([
        'gds_id' => 47, 'cle' => 'interpretation.fenetre_evenements_mois', 'valeur' => 18, 'libelle' => 'Essai', 'valide_du' => '2026-01-01',
    ]);

    expect(referentiels()->parametre('interpretation.fenetre_evenements_mois', 47))->toBe(18.0)
        ->and(referentiels()->parametre('interpretation.fenetre_evenements_mois', 24))->toBe(12.0);
});
