<?php

use App\Models\Dossier\Action;
use App\Models\Dossier\AnalyseEau;
use App\Models\Dossier\Controle;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\Message;
use App\Models\Dossier\MesureTerrain;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Interpretation;
use App\Services\Dossier\BilanSanitaireService;
use App\Services\Dossier\EauService;
use App\Services\Dossier\InterpretationVisiteService;
use App\Services\Dossier\MesuresService;
use App\Services\Dossier\RedactionVisite;
use App\Services\Dossier\SaisieRefusee;
use App\Services\Dossier\VisiteService;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// E21 — interprétation de visite (EF-M2-04 à EF-M2-07, RC-1.4 du niveau retenu, T-06) : brouillon prérempli, circuit,
// publication des objets de la visite, version suivante, plan d'action et suivi.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Notification::fake();
    Storage::fake('local');
    $this->jeu = JeuCloisonnement::creer();
    $this->tech = $this->jeu->technicien24;
    $this->actingAs($this->tech);
    $this->service = app(InterpretationVisiteService::class);
    $this->visite = app(VisiteService::class)->creer($this->jeu->elevage24a, $this->tech, '2026-01-15', 'initiale');

    // Lot T-03 (MAT faible, Cu carence probable) rattaché à la visite, interprété au niveau retenu 2
    $this->analyse = AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 61, 'CU' => 4.2, 'FE' => 166, 'K' => 18]);
    app(VisiteService::class)->rattacherPrelevements($this->visite, [$this->analyse->prelevement_id]);
    $lots = app(InterpretationService::class);
    $this->lot = $lots->creerBrouillon($this->analyse, $this->tech);
    $lots->enregistrer($this->lot, new RedactionInterpretation(synthese: 'Foin pauvre en azote et en cuivre.', niveauRetenu: 2));
    $lots->valider($this->lot->refresh(), $this->tech);

    $this->eau = app(EauService::class)->creer($this->visite, 'Source', '2026-01-15', null, ['EAU_FER' => ['valeur' => 250]]);
});

function redigerEtValider(InterpretationVisite $iv, int $niveau): InterpretationVisite
{
    $service = app(InterpretationVisiteService::class);
    $service->enregistrer($iv, new RedactionVisite('Vos fourrages manquent d\'azote', 'Synthèse de la visite.', null, $niveau), test()->tech);

    return $service->valider($iv->refresh(), test()->tech);
}

it('prépare le brouillon : niveau proposé et messages issus des lots et de l\'eau, dans l\'ordre de gravité', function () {
    $iv = $this->service->creerBrouillon($this->visite, $this->tech);
    $messages = Message::query()->where('interpretation_visite_id', $iv->id)->orderBy('ordre')->get();

    expect($iv->version)->toBe(1)
        ->and($iv->niveau_propose)->toBe(2)
        ->and($iv->version_grille)->toBe('4')
        ->and($messages->pluck('origine')->unique()->values()->all())->toBe(['lot', 'eau'])
        ->and($messages->first()?->gravite)->toBe(2)
        ->and($messages->last()?->pilier)->toBe('hydratation')
        ->and($messages->last()?->texte)->toContain('200 µg/l')
        ->and(fn () => $this->service->creerBrouillon($this->visite, $this->tech))->toThrow(SaisieRefusee::class);
});

it('limite les messages au paramètre visite.messages_max', function () {
    $iv = $this->service->creerBrouillon($this->visite, $this->tech);
    $six = array_fill(0, 6, ['gravite' => 1, 'texte' => 'Un message']);

    expect(fn () => $this->service->remplacerMessages($iv, $six))->toThrow(SaisieRefusee::class);
});

it('exige synthèse, niveau retenu et message pour valider ; la visite passe « interprétée »', function () {
    $iv = $this->service->creerBrouillon($this->visite, $this->tech);

    expect(fn () => $this->service->valider($iv, $this->tech))->toThrow(SaisieRefusee::class);

    redigerEtValider($iv, 2);
    expect($iv->refresh()->statut)->toBe(InterpretationVisite::STATUT_VALIDE)
        ->and($this->visite->refresh()->statut)->toBe(Visite::STATUT_INTERPRETEE);
});

it('refuse la publication tant qu\'un objet de la visite reste en saisie', function () {
    $iv = redigerEtValider($this->service->creerBrouillon($this->visite, $this->tech), 2);
    app(MesuresService::class)->ajouter($this->visite, $this->tech, '2026-01-15', 'GLYCEMIE', 42, 'BOV', 'prepa_velage');

    expect($this->service->obstaclesPublication($this->visite))->toContain('1 analyse(s) d\'eau à valider.', '1 mesure(s) terrain à valider.')
        ->and(fn () => $this->service->publier($iv, $this->tech))->toThrow(SaisieRefusee::class);
});

it('publie la visite, ses lots et ses objets validés, fige la grille et journalise (EF-M2-07)', function () {
    $iv = redigerEtValider($this->service->creerBrouillon($this->visite, $this->tech), 2);
    app(EauService::class)->valider($this->eau);

    $this->service->publier($iv, $this->tech);

    expect($iv->refresh()->statut)->toBe(InterpretationVisite::STATUT_PUBLIE)
        ->and($iv->publie_par)->toBe($this->tech->id)
        ->and($this->visite->refresh()->statut)->toBe(Visite::STATUT_PUBLIEE)
        ->and($this->lot->refresh()->statut)->toBe(Interpretation::STATUT_PUBLIE)
        ->and($this->eau->refresh()->statut)->toBe(AnalyseEau::STATUT_PUBLIE)
        ->and(DB::table('app.journal')->where('action', 'publication_interpretation_visite')->where('objet', 'dossier.interpretation_visite:'.$iv->id)->exists())->toBeTrue();

    // L'éleveur lit la fiche publiée, le bulletin du lot et l'eau ; pas un éleveur d'un autre élevage
    expect(Gate::forUser($this->jeu->eleveur24a)->allows('view', $iv))->toBeTrue()
        ->and(Gate::forUser($this->jeu->eleveur24a)->allows('view', Analyse::query()->find($this->analyse->id)))->toBeTrue()
        ->and(Gate::forUser($this->jeu->eleveur24a)->allows('view', $this->eau->refresh()))->toBeTrue()
        ->and(Gate::forUser($this->jeu->eleveur47)->allows('view', $iv))->toBeFalse();
});

it('exige le visa du vétérinaire conseil pour un niveau retenu 3 ; le refus renvoie en brouillon avec motif (EF-M2-04)', function () {
    app(EauService::class)->valider($this->eau);
    $iv = redigerEtValider($this->service->creerBrouillon($this->visite, $this->tech), 3);

    expect(Gate::forUser($this->tech)->allows('publier', $iv))->toBeFalse()
        ->and(Gate::forUser($this->tech)->allows('viser', $iv))->toBeFalse()
        ->and(Gate::forUser($this->jeu->veterinaireConseil24)->allows('viser', $iv))->toBeTrue();

    $this->service->refuserVisa($iv, 'Préciser la cause sélénium.', $this->jeu->veterinaireConseil24);
    expect($iv->refresh()->statut)->toBe(InterpretationVisite::STATUT_BROUILLON)->and($iv->refus_visa_motif)->toBe('Préciser la cause sélénium.');

    $this->service->valider($iv, $this->tech);
    $this->service->viser($iv->refresh(), $this->jeu->veterinaireConseil24);
    expect(Gate::forUser($this->tech)->allows('publier', $iv->refresh()))->toBeTrue();
    $this->service->publier($iv, $this->tech);
    expect($iv->refresh()->vise_par)->toBe($this->jeu->veterinaireConseil24->id);
});

it('fige le plan d\'action dès la validation de l\'interprétation de visite', function () {
    $iv = redigerEtValider($this->service->creerBrouillon($this->visite, $this->tech), 2);

    $this->service->ajouterAction($iv->refresh(), ['priorite' => 1, 'responsable' => 'eleveur', 'titre' => 'Action tardive'], $this->tech);
})->throws(SaisieRefusee::class);

it('T-06 : version 2 copiée de la version 1, publication qui archive la version 1, consultable par le technicien', function () {
    app(EauService::class)->valider($this->eau);
    $v1 = $this->service->creerBrouillon($this->visite, $this->tech);
    $action = $this->service->ajouterAction($v1, ['priorite' => 1, 'responsable' => 'eleveur', 'titre' => 'Retirer le minéral périmé'], $this->tech);
    $this->service->ajouterControle($action, 'Prise de sang Se', '0,06 mg/l et plus', '2026-04-15', $this->tech);
    $this->service->ajouterCause($v1, 'immunite_oligo', 'Carence en sélénium et en iode', 'Prise de sang, minéral périmé', null);
    redigerEtValider($v1, 2);
    $this->service->publier($v1->refresh(), $this->tech);

    $v2 = $this->service->nouvelleVersion($v1->refresh(), $this->tech);

    expect($v2->version)->toBe(2)
        ->and($v2->statut)->toBe(InterpretationVisite::STATUT_BROUILLON)
        ->and($v2->synthese)->toBe('Synthèse de la visite.')
        ->and(Message::query()->where('interpretation_visite_id', $v2->id)->count())->toBe(Message::query()->where('interpretation_visite_id', $v1->id)->count())
        ->and($this->service->publiee($this->visite)?->id)->toBe($v1->id)
        ->and(Gate::forUser($this->jeu->eleveur24a)->allows('view', $v2))->toBeFalse()
        ->and(fn () => $this->service->nouvelleVersion($v1->refresh(), $this->tech))->toThrow(SaisieRefusee::class);

    $copie = Action::query()->whereIn('plan_action_id', DB::table('dossier.plan_action')->where('interpretation_visite_id', $v2->id)->select('id'))->sole();
    expect($copie->titre)->toBe('Retirer le minéral périmé')
        ->and(Controle::query()->where('action_id', $copie->id)->count())->toBe(1);

    $this->service->enregistrer($v2, new RedactionVisite('Titre corrigé', 'Synthèse corrigée.', null, 2), $this->tech);
    $this->service->valider($v2->refresh(), $this->tech);
    $this->service->publier($v2->refresh(), $this->tech);

    expect($v1->refresh()->statut)->toBe(InterpretationVisite::STATUT_ARCHIVE)
        ->and($v2->refresh()->statut)->toBe(InterpretationVisite::STATUT_PUBLIE)
        ->and($v1->version_grille)->toBe('4')
        ->and(Gate::forUser($this->tech)->allows('view', $v1))->toBeTrue()
        ->and(Gate::forUser($this->jeu->eleveur24a)->allows('view', $v1))->toBeFalse()
        ->and(Gate::forUser($this->tech)->allows('update', $this->visite->refresh()))->toBeFalse();
});

it('laisse l\'éleveur marquer faites ses actions et le technicien saisir les contrôles, sur le plan publié (EF-M2-06)', function () {
    app(EauService::class)->valider($this->eau);
    $iv = $this->service->creerBrouillon($this->visite, $this->tech);
    $eleveur = $this->service->ajouterAction($iv, ['priorite' => 1, 'responsable' => 'eleveur', 'titre' => 'Blocs de sel iodé', 'quand' => 'Toute l\'année'], $this->tech);
    $veto = $this->service->ajouterAction($iv, ['priorite' => 1, 'responsable' => 'veterinaire', 'titre' => 'Sélénium au tarissement', 'jalon' => 'tarissement'], $this->tech);
    $controle = $this->service->ajouterControle($eleveur, null, 'Consommation vérifiée', '2026-02-01', $this->tech);

    expect(Gate::forUser($this->jeu->eleveur24a)->allows('marquerFaite', $eleveur))->toBeFalse()
        ->and(Gate::forUser($this->jeu->veterinaireConseil24)->allows('validerVeterinaire', $veto))->toBeTrue()
        ->and(Gate::forUser($this->jeu->veterinaireConseil24)->allows('validerVeterinaire', $eleveur))->toBeFalse();
    $this->service->validerActionVeterinaire($veto, $this->jeu->veterinaireConseil24);

    redigerEtValider($iv, 2);
    $this->service->publier($iv->refresh(), $this->tech);

    expect(Gate::forUser($this->jeu->eleveur24a)->allows('marquerFaite', $eleveur->refresh()))->toBeTrue()
        ->and(Gate::forUser($this->jeu->eleveur24a)->allows('marquerFaite', $veto->refresh()))->toBeFalse()
        ->and(Gate::forUser($this->jeu->eleveur47)->allows('marquerFaite', $eleveur))->toBeFalse()
        ->and(Gate::forUser($this->tech)->allows('update', $eleveur))->toBeFalse()
        ->and(Gate::forUser($this->tech)->allows('saisirResultat', $controle))->toBeTrue();

    $this->service->marquerFaite($eleveur, $this->jeu->eleveur24a, 'Bloc posé');
    $this->service->saisirResultatControle($controle, 'Bloc consommé', $this->tech);

    expect($eleveur->refresh()->statut)->toBe(Action::STATUT_FAITE)
        ->and($eleveur->commentaire_eleveur)->toBe('Bloc posé')
        ->and($controle->refresh()->statut)->toBe(Controle::STATUT_REALISE)
        ->and($veto->refresh()->valide_par)->toBe($this->jeu->veterinaireConseil24->id);
});

it('propose les familles de cause des troubles présents (étape « croiser »)', function () {
    DB::table('fourrage.elevage')->where('id', $this->jeu->elevage24a->id)->update(['systeme_bovin' => 'viande']);
    app(BilanSanitaireService::class)->enregistrer($this->jeu->elevage24a->fresh(), '2025-2026', 'NON_DELIVRANCE', 10, 81, null);

    $elements = $this->service->elementsACroiser($this->visite->refresh());

    expect($elements['troubles'])->toBe(['non_delivrance'])
        ->and(array_map(fn ($f) => $f->familleCause, $elements['familles']))->toBe(['immunite_oligo', 'metabolisme_mineral', 'metabolisme_energetique'])
        ->and($elements['lots'][0]['interpretation']?->id)->toBe($this->lot->id);
});

it('n\'ouvre pas les mesures publiées à l\'éleveur avant publication de la visite', function () {
    $mesure = app(MesuresService::class)->ajouter($this->visite, $this->tech, '2026-01-15', 'GLYCEMIE', 42, 'BOV', 'prepa_velage');
    $mesure->update(['statut' => MesureTerrain::STATUT_VALIDEE]);

    expect(Gate::forUser($this->jeu->eleveur24a)->allows('view', $mesure))->toBeFalse();
});
