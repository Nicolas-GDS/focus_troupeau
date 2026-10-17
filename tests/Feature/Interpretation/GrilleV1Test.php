<?php

use App\Domain\Interpretation\Classeur;
use App\Domain\Interpretation\CompatibiliteEvenements;
use App\Domain\Interpretation\ContexteLot;
use App\Domain\Interpretation\EvenementSanitaire;
use App\Domain\Interpretation\NiveauGlobal;
use App\Domain\Interpretation\NiveauPropose;
use App\Domain\Interpretation\Plausibilite;
use App\Domain\Interpretation\Ratios;
use App\Domain\Interpretation\ValeurAnalysee;
use App\Domain\Referentiels\Referentiels;
use App\Domain\Referentiels\StatutEpistemique;
use Database\Seeders\Ref\TroublesSanitairesSeeder;
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
    // version 4 : référentiels du dossier (lot L3, étape 2)
    expect(referentiels()->versionGrilleCourante())->toBe('5')
        ->and(referentiels()->parametre('interpretation.fenetre_evenements_mois'))->toBe(12.0)
        ->and(referentiels()->parametre('cle.inconnue'))->toBeNull()
        ->and(referentiels()->familleDuType('ENRUB_LUZ'))->toBe('ENRUB')
        ->and(count(referentiels()->liensCompatibilite()))->toBe(111);
});

it('préfère la valeur par défaut propre au GDS à celle de l\'instance', function () {
    DB::table('app.parametre_defaut')->insert([
        'gds_id' => 47, 'cle' => 'interpretation.fenetre_evenements_mois', 'valeur' => 18, 'libelle' => 'Essai', 'valide_du' => '2026-01-01',
    ]);

    expect(referentiels()->parametre('interpretation.fenetre_evenements_mois', 47))->toBe(18.0)
        ->and(referentiels()->parametre('interpretation.fenetre_evenements_mois', 24))->toBe(12.0);
});

/**
 * Niveau proposé d'un bulletin avec les événements sanitaires de l'élevage (RC-1.4, chapitre 11), sur les référentiels réels.
 *
 * @param  array<string, float>  $valeurs
 * @param  list<EvenementSanitaire>  $evenements
 */
function niveauAvecEvenements(array $valeurs, array $evenements): NiveauPropose
{
    $lot = new ContexteLot('FOIN_PP', 'FOIN');
    $alertes = array_values(array_filter(
        (new Classeur)->classerTout(bulletin($valeurs), $lot, referentiels()->seuilsActifs()),
        fn ($c) => $c->produitUneAlerte(),
    ));
    $compatibilite = new CompatibiliteEvenements;
    $correspondances = $compatibilite->correspondances(
        $alertes, $evenements, referentiels()->liensCompatibilite(), new DateTimeImmutable('2026-10-15'),
        (int) referentiels()->parametre('interpretation.fenetre_evenements_mois'),
    );

    return (new NiveauGlobal)->proposer(
        array_map(fn ($c) => $c->seuil->gravite, $alertes),
        array_map(fn ($c) => $c->valeur->parametreCode, $alertes),
        $compatibilite->parametresPourRehaussement($correspondances, (int) referentiels()->parametre('interpretation.force_min_rehaussement')),
        $lot,
        null,
        null,
    );
}

it('T-04 (proposé) : foin à Mg bas (niveau 1) et tétanie dans l\'année → rehaussement proposé au niveau 2, motif affiché', function () {
    $niveau = niveauAvecEvenements(['MG' => 1.6], [new EvenementSanitaire('tetanie', 'BOV', new DateTimeImmutable('2026-04-20'))]);

    expect($niveau->niveauAvantRehaussement)->toBe(1)
        ->and($niveau->niveau)->toBe(2)
        ->and($niveau->rehaussements[0])->toContain('MG');
});

it('ne rehausse pas le lot de T-03 pour une non-délivrance : aucun lien MAT ou Cu, et niveau déjà à 2', function () {
    $niveau = niveauAvecEvenements(
        ['MAT' => 61, 'CU' => 4.2, 'FE' => 166, 'K' => 18],
        [new EvenementSanitaire('retention_placentaire', 'BOV', new DateTimeImmutable('2026-03-01'), 'VA')],
    );

    expect($niveau->niveau)->toBe(2)->and($niveau->rehaussements)->toBe([]);
});

it('ne rehausse pas pour un lien de force 1 (minimum paramétré à 2)', function () {
    // Ca bas ↔ rétention placentaire : force 1 dans le référentiel du chapitre 11 ; seuil Ca bas absent de la grille v1 :
    // on vérifie le paramètre de force et la présence du lien.
    $lien = collect(referentiels()->liensCompatibilite())
        ->first(fn ($l) => $l->parametreCode === 'CA' && $l->troubleCode === 'retention_placentaire');

    expect($lien?->force)->toBe(1)
        ->and(referentiels()->parametre('interpretation.force_min_rehaussement'))->toBe(2.0);
});

it('charge le référentiel des troubles avec ses codes historiques et ses références résolues', function () {
    expect(DB::table('ref.trouble_sanitaire')->count())->toBe(32)
        ->and(DB::table('ref.trouble_sanitaire')->whereNotNull('type_evenement_existant')->count())->toBe(10)
        ->and(DB::table('ref.compatibilite_parametre_trouble')->whereNull('reference_id')->count())->toBe(0)
        ->and(DB::table('ref.reference_biblio')->where('cle', 'REG2018_1039')->value('statut_verif'))->toBe('verifie')
        ->and(DB::table('ref.seuil_interpretation')->whereNull('sens')->count())->toBe(0);
});

it('rejoue le chargement du référentiel des troubles sans doublon', function () {
    (new TroublesSanitairesSeeder)->run();

    expect(DB::table('ref.compatibilite_parametre_trouble')->count())->toBe(111)
        ->and(DB::table('ref.reference_biblio')->count())->toBe(67);   // dont 3 références du dossier (lot L3)
});
