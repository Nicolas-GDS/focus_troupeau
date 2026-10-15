<?php

use App\Domain\Referentiels\Referentiels;
use App\Models\App\Journal;
use App\Models\Fourrage\Alerte;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\EvenementSanitaire;
use App\Models\Fourrage\Interpretation;
use App\Services\Interpretation\InterpretationRefusee;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// E20 — service d'interprétation : EF-M2-02 (alertes automatiques, niveau proposé), EF-M2-03 (rédaction),
// EF-M2-04 (circuit et visa), RC-1, RC-1.4 ; cas de recette T-03, T-04, T-05 ; ENF-SEC-08 (journal).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->service = app(InterpretationService::class);
});

/**
 * @param  array<string, float>  $valeurs
 */
function brouillon(array $valeurs, ?string $categorie = null): Interpretation
{
    $analyse = AnalyseEssai::creer(test()->jeu->elevage24a, $valeurs);

    return test()->service->creerBrouillon($analyse, test()->jeu->technicien24, $categorie);
}

/**
 * Brouillon rédigé au niveau retenu donné, puis validé.
 */
function interpretationValidee(int $niveau): Interpretation
{
    $interpretation = brouillon(['MAT' => 61, 'CU' => 4.2]);
    $interpretation = test()->service->enregistrer($interpretation, new RedactionInterpretation(
        synthese: "Foin pauvre en azote.\nCuivre en carence probable.\nComplémenter avant la mise à la reproduction.",
        niveauRetenu: $niveau,
    ));

    return test()->service->valider($interpretation, test()->jeu->technicien24);
}

/**
 * @return list<array{0: string|null, 1: string, 2: int}>
 */
function resumeAlertes(Interpretation $interpretation): array
{
    return Alerte::query()->withoutGlobalScopes()->where('interpretation_id', $interpretation->id)->orderBy('id')->get()
        ->map(fn (Alerte $a) => [$a->parametre_code, $a->classe, $a->gravite])->all();
}

it('T-03 : persiste les alertes MAT faible (1) et Cu carence probable (2), sans alerte Fe ni K, niveau proposé 2', function () {
    $interpretation = brouillon(['MAT' => 61, 'CU' => 4.2, 'FE' => 166, 'K' => 18]);

    expect(resumeAlertes($interpretation))->toBe([['MAT', 'faible', 1], ['CU', 'carence_probable', 2]])
        ->and($interpretation->niveau_propose)->toBe(2)
        ->and($interpretation->niveau_global)->toBeNull()
        ->and($interpretation->statut)->toBe('brouillon')
        ->and($interpretation->version_grille)->toBe(app(Referentiels::class)->versionGrilleCourante());

    $cuivre = Alerte::query()->withoutGlobalScopes()->where('interpretation_id', $interpretation->id)->where('parametre_code', 'CU')->sole();
    expect($cuivre->origine)->toBe('automatique')
        ->and($cuivre->retenue)->toBeTrue()
        ->and($cuivre->seuil_id)->not->toBeNull()
        ->and($cuivre->message)->toContain('Cu < 7 mg/kg MS')
        ->and((float) $cuivre->valeur)->toBe(4.2);
});

it('relie le technicien à sa fiche personne, créée à sa première interprétation', function () {
    $premiere = brouillon(['MAT' => 61]);
    $seconde = brouillon(['MAT' => 70]);
    $technicien = $this->jeu->technicien24->fresh();

    expect($technicien?->personne_id)->not->toBeNull()
        ->and($premiere->technicien_id)->toBe($technicien?->personne_id)
        ->and($seconde->technicien_id)->toBe($technicien?->personne_id)
        ->and(DB::table('prive.personne')->where('id', $technicien?->personne_id)->value('role'))->toBe('technicien_gds')
        ->and(DB::table('prive.personne')->count())->toBe(1);
});

it('reporte la censure d\'un résultat sous la limite de quantification dans l\'alerte', function () {
    $analyse = AnalyseEssai::creer($this->jeu->elevage24a, ['CU' => 5.0]);
    DB::table('fourrage.resultat')->where('analyse_id', $analyse->id)->update(['censure' => '<']);

    $interpretation = $this->service->creerBrouillon($analyse, $this->jeu->technicien24);

    expect(Alerte::query()->withoutGlobalScopes()->where('interpretation_id', $interpretation->id)->value('censure'))->toBe('<');
});

it('T-04 : Mg 1,6 g et tétanie dans l\'année → rehaussement proposé au niveau 2 avec son motif, niveau retenu laissé au technicien', function () {
    EvenementSanitaire::query()->create([
        'elevage_id' => $this->jeu->elevage24a->id, 'espece_code' => 'BOV', 'date_evenement' => today()->subMonths(3),
        'type_evenement' => 'tetanie',
    ]);

    $interpretation = brouillon(['MG' => 1.6]);

    expect(resumeAlertes($interpretation))->toBe([['MG', 'bas', 1]])
        ->and($interpretation->niveau_propose)->toBe(2)
        ->and($this->service->motifsRehaussement($interpretation))->toHaveCount(1)
        ->and($this->service->motifsRehaussement($interpretation)[0])->toContain('MG')
        ->and($interpretation->niveau_global)->toBeNull();

    // Le technicien retient le niveau 1 : les deux niveaux sont conservés (RC-1.4).
    $interpretation = $this->service->enregistrer($interpretation, new RedactionInterpretation(niveauRetenu: 1));
    expect($interpretation->niveau_global)->toBe(1)->and($interpretation->niveau_propose)->toBe(2);
});

it('T-04 (contrôle) : le lot de T-03 avec une non-délivrance n\'est pas rehaussé', function () {
    EvenementSanitaire::query()->create([
        'elevage_id' => $this->jeu->elevage24a->id, 'espece_code' => 'BOV', 'categorie_code' => 'VA',
        'date_evenement' => today()->subMonths(2), 'type_evenement' => 'retention_placentaire',
    ]);

    $interpretation = brouillon(['MAT' => 61, 'CU' => 4.2, 'FE' => 166, 'K' => 18]);

    expect($interpretation->niveau_propose)->toBe(2)->and($this->service->motifsRehaussement($interpretation))->toBe([]);
});

it('ne rehausse pas pour un événement hors de la fenêtre paramétrée, ni pour un autre élevage', function () {
    EvenementSanitaire::query()->create([
        'elevage_id' => $this->jeu->elevage24a->id, 'espece_code' => 'BOV', 'date_evenement' => today()->subMonths(13),
        'type_evenement' => 'tetanie',
    ]);
    EvenementSanitaire::query()->create([
        'elevage_id' => $this->jeu->elevage24b->id, 'espece_code' => 'BOV', 'date_evenement' => today()->subMonth(),
        'type_evenement' => 'tetanie',
    ]);

    expect(brouillon(['MG' => 1.6])->niveau_propose)->toBe(1);
});

it('refuse d\'interpréter un bulletin non contrôlé, ou déjà interprété', function (string $statut) {
    $analyse = AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 61], $statut);

    expect(fn () => $this->service->creerBrouillon($analyse, $this->jeu->technicien24))->toThrow(InterpretationRefusee::class);
})->with(['anomalie', 'annule', 'recu']);

it('refuse un second brouillon pour le même bulletin', function () {
    $analyse = AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 61]);
    $this->service->creerBrouillon($analyse, $this->jeu->technicien24);

    expect(fn () => $this->service->creerBrouillon($analyse, $this->jeu->technicien24))->toThrow(InterpretationRefusee::class);
});

it('écarte une alerte avec motif obligatoire et recalcule le niveau proposé ; la retenir le rétablit', function () {
    $interpretation = brouillon(['MAT' => 61, 'CU' => 4.2]);
    $cuivre = Alerte::query()->withoutGlobalScopes()->where('interpretation_id', $interpretation->id)->where('parametre_code', 'CU')->sole();

    expect(fn () => $this->service->ecarterAlerte($cuivre, '  '))->toThrow(InterpretationRefusee::class);

    $this->service->ecarterAlerte($cuivre, 'Complémentation minérale déjà en place');
    expect($cuivre->fresh()?->retenue)->toBeFalse()
        ->and($cuivre->fresh()?->motif_ecart)->toBe('Complémentation minérale déjà en place')
        ->and($interpretation->fresh()?->niveau_propose)->toBe(1);

    $this->service->retenirAlerte($cuivre);
    expect($cuivre->fresh()?->retenue)->toBeTrue()
        ->and($cuivre->fresh()?->motif_ecart)->toBeNull()
        ->and($interpretation->fresh()?->niveau_propose)->toBe(2);
});

it('ajoute une alerte du technicien, comptée dans le niveau proposé', function () {
    $interpretation = brouillon(['MAT' => 61]);

    $alerte = $this->service->ajouterAlerte($interpretation, 'MAT', 'odeur_anormale', 3, 'Odeur butyrique signalée au prélèvement');

    expect($alerte->origine)->toBe('technicien')
        ->and($alerte->retenue)->toBeTrue()
        ->and($alerte->seuil_id)->toBeNull()
        ->and($interpretation->fresh()?->niveau_propose)->toBe(3);
});

it('reclasse le bulletin quand la catégorie cible change, en conservant les alertes et les écarts du technicien', function () {
    $interpretation = brouillon(['CU' => 23.0, 'MAT' => 61]);
    expect(resumeAlertes($interpretation))->toBe([['MAT', 'faible', 1]]);

    $mat = Alerte::query()->withoutGlobalScopes()->where('interpretation_id', $interpretation->id)->where('parametre_code', 'MAT')->sole();
    $this->service->ecarterAlerte($mat, 'Lot réservé aux animaux à l\'entretien');
    $this->service->ajouterAlerte($interpretation, null, 'stockage', 1, 'Balles stockées dehors sans protection');

    $interpretation = $this->service->changerCategorieCible($interpretation, 'BREBIS_ALL');

    $alertes = Alerte::query()->withoutGlobalScopes()->where('interpretation_id', $interpretation->id)->orderBy('id')->get();
    expect($interpretation->categorie_cible_code)->toBe('BREBIS_ALL')
        ->and($alertes->where('origine', 'technicien')->pluck('classe')->all())->toBe(['stockage'])
        ->and($alertes->firstWhere('parametre_code', 'CU')?->classe)->toBe('toxicite_ovins')
        ->and($alertes->firstWhere('parametre_code', 'MAT')?->retenue)->toBeFalse()
        ->and($alertes->firstWhere('parametre_code', 'MAT')?->motif_ecart)->toBe('Lot réservé aux animaux à l\'entretien')
        ->and($interpretation->niveau_propose)->toBe(3);
});

it('enregistre les trois lectures, la synthèse, l\'utilisation et l\'orientation vétérinaire (EF-M2-03)', function () {
    $interpretation = brouillon(['MAT' => 61]);

    $interpretation = $this->service->enregistrer($interpretation, new RedactionInterpretation(
        lectureConservation: 'Fourrage propre, bien conservé.',
        lectureMineraux: 'Profil minéral sans particularité.',
        lectureNutritionnelle: 'Pauvre en azote.',
        synthese: "Ligne 1\nLigne 2\nLigne 3",
        recommandations: 'Associer à un fourrage plus riche en azote.',
        utilisationConseillee: 'Vaches allaitantes à l\'entretien',
        utilisationDeconseillee: ['VL', 'GEN'],
        niveauRetenu: 1,
        orientationVeterinaire: true,
    ));

    expect($interpretation->lecture_sanitaire)->toBe('Fourrage propre, bien conservé.')
        ->and($interpretation->lecture_mineraux)->toBe('Profil minéral sans particularité.')
        ->and($interpretation->lecture_nutritionnelle)->toBe('Pauvre en azote.')
        ->and($this->service->utilisationDeconseillee($interpretation))->toBe(['VL', 'GEN'])
        ->and($interpretation->orientation_veterinaire)->toBeTrue()
        ->and($interpretation->niveau_global)->toBe(1)
        ->and($interpretation->niveau_propose)->toBe(1);

    expect(fn () => $this->service->enregistrer($interpretation, new RedactionInterpretation(synthese: "1\n2\n3\n4\n5\n6")))
        ->toThrow(InterpretationRefusee::class)
        ->and(fn () => $this->service->enregistrer($interpretation, new RedactionInterpretation(utilisationDeconseillee: ['INCONNUE'])))
        ->toThrow(InterpretationRefusee::class)
        ->and(fn () => $this->service->enregistrer($interpretation, new RedactionInterpretation(niveauRetenu: 4)))
        ->toThrow(InterpretationRefusee::class);
});

it('exige la synthèse et le niveau retenu pour valider ; la validation passe le bulletin au statut valide et se journalise', function () {
    $interpretation = brouillon(['MAT' => 61]);

    expect(fn () => $this->service->valider($interpretation, $this->jeu->technicien24))->toThrow(InterpretationRefusee::class);

    $this->service->enregistrer($interpretation, new RedactionInterpretation(synthese: 'Foin pauvre en azote.'));
    expect(fn () => $this->service->valider($interpretation->fresh(), $this->jeu->technicien24))->toThrow(InterpretationRefusee::class);

    $interpretation = interpretationValidee(1);

    expect($interpretation->statut)->toBe('valide')
        ->and(Analyse::query()->withoutGlobalScopes()->find($interpretation->analyse_id)?->statut)->toBe('valide')
        ->and(Journal::query()->where('action', 'validation_interpretation')->where('objet', 'fourrage.interpretation:'.$interpretation->id)->exists())->toBeTrue();
});

it('fige une interprétation validée, visée ou publiée', function () {
    $interpretation = interpretationValidee(2);
    $alerte = Alerte::query()->withoutGlobalScopes()->where('interpretation_id', $interpretation->id)->firstOrFail();

    expect(fn () => $this->service->enregistrer($interpretation, new RedactionInterpretation(synthese: 'Autre')))->toThrow(InterpretationRefusee::class)
        ->and(fn () => $this->service->ecarterAlerte($alerte, 'Motif quelconque'))->toThrow(InterpretationRefusee::class)
        ->and(fn () => $this->service->retenirAlerte($alerte))->toThrow(InterpretationRefusee::class)
        ->and(fn () => $this->service->ajouterAlerte($interpretation, null, 'x', 1, 'Message'))->toThrow(InterpretationRefusee::class)
        ->and(fn () => $this->service->changerCategorieCible($interpretation, 'VL'))->toThrow(InterpretationRefusee::class)
        ->and(fn () => $this->service->valider($interpretation, $this->jeu->technicien24))->toThrow(InterpretationRefusee::class);

    $publiee = $this->service->publier($interpretation, $this->jeu->technicien24);
    expect(fn () => $this->service->enregistrer($publiee, new RedactionInterpretation(synthese: 'Autre')))->toThrow(InterpretationRefusee::class);
});

it('publie un niveau 0 à 2 depuis « valide », jamais depuis le brouillon', function () {
    expect(fn () => $this->service->publier(brouillon(['MAT' => 61]), $this->jeu->technicien24))->toThrow(InterpretationRefusee::class);

    $publiee = $this->service->publier(interpretationValidee(2), $this->jeu->technicien24);

    expect($publiee->statut)->toBe('publie')
        ->and(Journal::query()->where('action', 'publication_interpretation')->where('user_id', $this->jeu->technicien24->id)->exists())->toBeTrue();
});

it('T-05 : refuse la publication d\'un niveau 3 par le technicien ; l\'accepte après le visa du vétérinaire conseil', function () {
    $interpretation = interpretationValidee(3);
    $jeu = $this->jeu;

    expect(Gate::forUser($jeu->technicien24)->allows('publier', $interpretation))->toBeFalse()
        ->and(fn () => $this->service->publier($interpretation, $jeu->technicien24))->toThrow(InterpretationRefusee::class)
        ->and($interpretation->fresh()?->statut)->toBe('valide');

    // Le visa revient au vétérinaire conseil, pas au technicien.
    expect(Gate::forUser($jeu->technicien24)->allows('viser', $interpretation))->toBeFalse()
        ->and(Gate::forUser($jeu->veterinaireConseil24)->allows('viser', $interpretation))->toBeTrue();

    $visee = $this->service->viser($interpretation, $jeu->veterinaireConseil24);
    expect($visee->statut)->toBe('visee')
        ->and($visee->vise_par)->toBe($jeu->veterinaireConseil24->id)
        ->and($visee->vise_le)->not->toBeNull()
        ->and(Journal::query()->where('action', 'visa_interpretation')->where('user_id', $jeu->veterinaireConseil24->id)->exists())->toBeTrue()
        ->and(Gate::forUser($jeu->veterinaireConseil24)->allows('publier', $visee))->toBeFalse()
        ->and(Gate::forUser($jeu->technicien24)->allows('publier', $visee))->toBeTrue();

    expect($this->service->publier($visee, $jeu->technicien24)->statut)->toBe('publie');
});

it('ne vise qu\'une interprétation validée de niveau 3', function () {
    expect(fn () => $this->service->viser(interpretationValidee(2), $this->jeu->veterinaireConseil24))->toThrow(InterpretationRefusee::class)
        ->and(fn () => $this->service->viser(brouillon(['MAT' => 61]), $this->jeu->veterinaireConseil24))->toThrow(InterpretationRefusee::class);
});

it('n\'ouvre la lecture d\'une interprétation aux comptes externes qu\'une fois publiée (§ 2.3)', function () {
    $jeu = $this->jeu;
    $interpretation = interpretationValidee(1);

    expect(Gate::forUser($jeu->eleveur24a)->allows('view', $interpretation))->toBeFalse()
        ->and(Gate::forUser($jeu->veterinaire)->allows('view', $interpretation))->toBeFalse()
        ->and(Gate::forUser($jeu->technicien24)->allows('view', $interpretation))->toBeTrue()
        ->and(Gate::forUser($jeu->admin24)->allows('view', $interpretation))->toBeTrue()
        ->and(Gate::forUser($jeu->technicien47)->allows('view', $interpretation))->toBeFalse();

    $publiee = $this->service->publier($interpretation, $jeu->technicien24);

    expect(Gate::forUser($jeu->eleveur24a)->allows('view', $publiee))->toBeTrue()
        ->and(Gate::forUser($jeu->veterinaire)->allows('view', $publiee))->toBeTrue()
        ->and(Gate::forUser($jeu->eleveur47)->allows('view', $publiee))->toBeFalse()
        ->and(Gate::forUser($jeu->admin24)->allows('update', $publiee))->toBeFalse();
});

it('réserve la rédaction au technicien du périmètre', function () {
    $jeu = $this->jeu;
    $interpretation = brouillon(['MAT' => 61]);

    expect(Gate::forUser($jeu->technicien24)->allows('update', $interpretation))->toBeTrue()
        ->and(Gate::forUser($jeu->chargeDeMission)->allows('update', $interpretation))->toBeTrue()
        ->and(Gate::forUser($jeu->technicien47)->allows('update', $interpretation))->toBeFalse()
        ->and(Gate::forUser($jeu->veterinaireConseil24)->allows('update', $interpretation))->toBeFalse()
        ->and(Gate::forUser($jeu->admin24)->allows('update', $interpretation))->toBeFalse()
        ->and(Gate::forUser($jeu->adminGlobal)->allows('update', $interpretation))->toBeFalse();
});

it('laisse le vétérinaire conseil refuser un visa : retour en brouillon avec le motif, puis reprise et nouveau visa (décision du 15/10/2026)', function () {
    $jeu = $this->jeu;
    $interpretation = interpretationValidee(3);

    expect(Gate::forUser($jeu->technicien24)->allows('refuserVisa', $interpretation))->toBeFalse()
        ->and(Gate::forUser($jeu->veterinaireConseil24)->allows('refuserVisa', $interpretation))->toBeTrue()
        ->and(fn () => $this->service->refuserVisa($interpretation, '  ', $jeu->veterinaireConseil24))->toThrow(InterpretationRefusee::class);

    $refusee = $this->service->refuserVisa($interpretation, 'Le niveau 3 ne me paraît pas justifié : revoir la lecture minérale.', $jeu->veterinaireConseil24);

    expect($refusee->statut)->toBe('brouillon')
        ->and($refusee->refus_visa_motif)->toContain('lecture minérale')
        ->and($refusee->refus_visa_par)->toBe($jeu->veterinaireConseil24->id)
        ->and(Analyse::query()->withoutGlobalScopes()->find($refusee->analyse_id)?->statut)->toBe('controle')
        ->and(Journal::query()->where('action', 'refus_visa_interpretation')->exists())->toBeTrue()
        ->and(Gate::forUser($jeu->technicien24)->allows('update', $refusee))->toBeTrue();

    $revalidee = $this->service->valider($refusee, $jeu->technicien24);
    expect($this->service->viser($revalidee, $jeu->veterinaireConseil24)->statut)->toBe('visee');
});

it('ne refuse le visa que d\'une interprétation validée de niveau 3', function () {
    expect(fn () => $this->service->refuserVisa(interpretationValidee(2), 'Motif quelconque', $this->jeu->veterinaireConseil24))
        ->toThrow(InterpretationRefusee::class);
});

it('laisse le technicien retenir un niveau 2 sur un lot proposé au niveau 3, publiable sans visa (décision du 15/10/2026)', function () {
    $analyse = AnalyseEssai::creer($this->jeu->elevage24a, ['CU' => 23.0]);
    $interpretation = $this->service->creerBrouillon($analyse, $this->jeu->technicien24, 'BREBIS_ALL');
    expect($interpretation->niveau_propose)->toBe(3);

    $interpretation = $this->service->enregistrer($interpretation, new RedactionInterpretation(
        synthese: 'Cuivre élevé pour des brebis : à vérifier avant distribution.',
        niveauRetenu: 2,
    ));
    $validee = $this->service->valider($interpretation, $this->jeu->technicien24);

    expect($validee->niveau_propose)->toBe(3)
        ->and($validee->niveau_global)->toBe(2)
        ->and($this->service->publier($validee, $this->jeu->technicien24)->statut)->toBe('publie');
});

it('a clôturé en version 3 le seuil Ca/P ovin des mâles BOUC_MALE ; le seuil caprin reste en vigueur', function () {
    $seuils = collect(app(Referentiels::class)->seuilsActifs())->filter(fn ($s) => $s->parametreCode === 'CA_P');

    expect($seuils->pluck('especeCode')->all())->toBe(['CAP'])
        ->and(DB::table('ref.seuil_interpretation')->where('parametre_code', 'CA_P')->where('espece_code', 'OVI')->value('version_fin'))->toBe('3')
        ->and(app(Referentiels::class)->versionGrilleCourante())->toBe('3');
});
