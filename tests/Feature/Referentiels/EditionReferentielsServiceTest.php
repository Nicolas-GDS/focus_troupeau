<?php

use App\Domain\Referentiels\Edition\EditionRefusee;
use App\Domain\Referentiels\Edition\SaisiePlage;
use App\Domain\Referentiels\Edition\SaisieSeuil;
use App\Domain\Referentiels\Referentiels;
use App\Models\App\Journal;
use App\Models\Fourrage\Interpretation;
use App\Services\Referentiels\EditionReferentielsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\JeuCloisonnement;

// E24 — édition versionnée des référentiels (EF-M10-02, EF-M10-03, ENF-EXP-04, ENF-SEC-08) ; RC-1 (cohérence de
// la grille : aucun chevauchement au même niveau de précision), RC-2 (plages de plausibilité).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->versionInitiale = (int) DB::table('ref.version_referentiel')->max(DB::raw('numero::int'));
    $this->service = app(EditionReferentielsService::class);
    $this->referentiels = app(Referentiels::class);
});

/**
 * Numéro de version relatif à la version courante au début du test (0 : courante, 1 : la suivante…) :
 * les tests ne dépendent pas du nombre de versions déjà livrées.
 */
function versionE24(int $decalage): string
{
    return (string) (test()->versionInitiale + $decalage);
}

/**
 * Ligne active d'un seuil de la grille v1 (document 02), par paramètre et classe.
 *
 * @return array<string, mixed>
 */
function seuilActifE24(string $parametre, string $classe): array
{
    return (array) DB::table('ref.seuil_interpretation')
        ->where('parametre_code', $parametre)->where('classe', $classe)->whereNull('version_fin')->first();
}

/**
 * @param  array<string, mixed>  $surcharges
 */
function saisieE24(array $surcharges = []): SaisieSeuil
{
    return SaisieSeuil::depuis($surcharges + [
        'parametre_code' => 'MAT', 'nature' => 'nutritionnel', 'borne_min' => '80', 'borne_max' => '100',
        'classe' => 'moyen', 'gravite' => '0', 'sens' => 'bas', 'statut_epistemique' => 'hypothese_locale',
        'message' => 'Fourrage moyennement pourvu en azote.',
    ]);
}

function derniereVersionE24(): string
{
    return (string) DB::table('ref.version_referentiel')->orderByRaw('numero::int DESC')->value('numero');
}

it('crée un seuil dans une nouvelle version (numéro suivant, auteur, date, décision)', function () {
    $id = $this->service->creerSeuil(saisieE24(), 'Comité technique du 15/10/2026 : classe intermédiaire MAT', $this->jeu->admin24);

    $version = DB::table('ref.version_referentiel')->where('numero', versionE24(1))->first();
    expect($version)->not->toBeNull()
        ->and($version->auteur_id)->toBe($this->jeu->admin24->id)
        ->and($version->commentaire)->toBe('Comité technique du 15/10/2026 : classe intermédiaire MAT')
        ->and((string) $version->date_version)->toBe(today()->toDateString());

    $ligne = DB::table('ref.seuil_interpretation')->find($id);
    expect($ligne->version_creation)->toBe(versionE24(1))->and($ligne->version_fin)->toBeNull()->and($ligne->actif)->toBeTrue()
        ->and($ligne->sens)->toBe('bas')->and((float) $ligne->borne_min)->toBe(80.0);
});

it('modifie un seuil en clôturant l\'ancienne ligne et en insérant la nouvelle, sans rien supprimer', function () {
    $ancien = seuilActifE24('MAT', 'faible');
    $avant = DB::table('ref.seuil_interpretation')->count();

    $nouveau = $this->service->modifierSeuil($ancien['id'], SaisieSeuil::depuis(['borne_max' => '85'] + $ancien), 'Relèvement du seuil MAT faible', $this->jeu->adminGlobal);

    expect(DB::table('ref.seuil_interpretation')->count())->toBe($avant + 1)
        ->and(DB::table('ref.seuil_interpretation')->find($ancien['id'])->version_fin)->toBe(versionE24(1))
        ->and((float) DB::table('ref.seuil_interpretation')->find($ancien['id'])->borne_max)->toBe(80.0);

    $ligne = DB::table('ref.seuil_interpretation')->find($nouveau);
    expect($ligne->version_creation)->toBe(versionE24(1))->and($ligne->version_fin)->toBeNull()
        ->and((float) $ligne->borne_max)->toBe(85.0)->and($ligne->message)->toBe($ancien['message'])
        ->and($ligne->reference_id)->toBe($ancien['reference_id']);
});

it('retire un seuil en le clôturant', function () {
    $ancien = seuilActifE24('MAT', 'faible');

    $version = $this->service->retirerSeuil($ancien['id'], 'Seuil remplacé par les repères du lot L5', $this->jeu->admin24);

    expect($version)->toBe(versionE24(1))
        ->and(DB::table('ref.seuil_interpretation')->find($ancien['id'])->version_fin)->toBe(versionE24(1))
        ->and(collect($this->referentiels->seuilsActifs())->pluck('id'))->not->toContain($ancien['id']);
});

it('numérote les versions successives', function () {
    $this->service->creerSeuil(saisieE24(), 'Décision 1', $this->jeu->admin24);
    $this->service->creerSeuil(saisieE24(['borne_min' => '100', 'borne_max' => '120', 'classe' => 'bon']), 'Décision 2', $this->jeu->admin24);

    expect(derniereVersionE24())->toBe(versionE24(2))
        ->and($this->service->versionCourante()?->numero)->toBe(versionE24(2))
        ->and($this->referentiels->versionGrilleCourante())->toBe(versionE24(2));
});

it('vide le cache : les seuils actifs lus par Referentiels reflètent la modification', function () {
    $ancien = seuilActifE24('MAT', 'faible');
    expect(collect($this->referentiels->seuilsActifs())->firstWhere('id', $ancien['id']))->not->toBeNull();

    $nouveau = $this->service->modifierSeuil($ancien['id'], SaisieSeuil::depuis(['borne_max' => '85'] + $ancien), 'Relèvement', $this->jeu->admin24);

    $actifs = collect($this->referentiels->seuilsActifs());
    expect($actifs->firstWhere('id', $ancien['id']))->toBeNull()
        ->and($actifs->firstWhere('id', $nouveau)?->borneMax)->toBe(85.0);
});

it('refuse un chevauchement avec un seuil actif au même niveau de précision', function () {
    // MAT tous fourrages : [−∞ ; 80[ « faible » existe ; [70 ; 90[ le chevauche.
    expect(fn () => $this->service->creerSeuil(saisieE24(['borne_min' => '70', 'borne_max' => '90']), 'Essai', $this->jeu->admin24))
        ->toThrow(EditionRefusee::class, 'Chevauchement');

    expect(derniereVersionE24())->toBe(versionE24(0))
        ->and(Journal::query()->where('action', 'modification_referentiel')->count())->toBe(0);
});

it('accepte le même intervalle à un autre niveau de précision', function () {
    $id = $this->service->creerSeuil(saisieE24(['borne_min' => '70', 'borne_max' => '90', 'famille_fourrage_code' => 'ENRUB']), 'MAT enrubannage', $this->jeu->admin24);

    expect(DB::table('ref.seuil_interpretation')->find($id)->famille_fourrage_code)->toBe('ENRUB');
});

it('n\'oppose pas la ligne modifiée à elle-même', function () {
    $ancien = seuilActifE24('MAT', 'faible');

    $nouveau = $this->service->modifierSeuil($ancien['id'], SaisieSeuil::depuis(['borne_max' => '75'] + $ancien), 'Abaissement', $this->jeu->admin24);

    expect($nouveau)->toBeGreaterThan($ancien['id']);
});

it('refuse les incohérences', function (array $surcharges, string $champ) {
    try {
        $this->service->creerSeuil(saisieE24($surcharges), 'Essai', $this->jeu->admin24);
        $this->fail('Enregistrement accepté à tort');
    } catch (EditionRefusee $refus) {
        expect($refus->erreurs)->toHaveKey($champ);
    }

    expect(derniereVersionE24())->toBe(versionE24(0));
})->with([
    'type hors famille' => [['famille_fourrage_code' => 'ENRUB', 'type_fourrage_code' => 'FOIN_LUZ'], 'type_fourrage_code'],
    'bornes inversées' => [['borne_min' => '120', 'borne_max' => '100'], 'borne_max'],
    'espèce incohérente avec la catégorie' => [['espece_code' => 'OVI', 'categorie_code' => 'VL'], 'categorie_code'],
    'sens manquant' => [['sens' => ''], 'sens'],
    'gravité hors 0-3' => [['gravite' => '5'], 'gravite'],
    'paramètre inconnu' => [['parametre_code' => 'INCONNU'], 'parametre_code'],
]);

it('refuse un enregistrement sans décision du comité technique', function () {
    expect(fn () => $this->service->creerSeuil(saisieE24(), '  ', $this->jeu->admin24))->toThrow(EditionRefusee::class);
    expect(derniereVersionE24())->toBe(versionE24(0));
});

it('refuse de modifier ou retirer une ligne déjà clôturée', function () {
    $ancien = seuilActifE24('MAT', 'faible');
    $this->service->retirerSeuil($ancien['id'], 'Retrait', $this->jeu->admin24);

    expect(fn () => $this->service->retirerSeuil($ancien['id'], 'Second retrait', $this->jeu->admin24))->toThrow(EditionRefusee::class)
        ->and(fn () => $this->service->modifierSeuil($ancien['id'], SaisieSeuil::depuis($ancien), 'Modif', $this->jeu->admin24))->toThrow(EditionRefusee::class);
});

it('journalise chaque modification avec le détail avant / après (ENF-SEC-08)', function () {
    $ancien = seuilActifE24('MAT', 'faible');
    $nouveau = $this->service->modifierSeuil($ancien['id'], SaisieSeuil::depuis(['borne_max' => '85'] + $ancien), 'Relèvement du seuil', $this->jeu->admin24);

    $journal = Journal::query()->where('action', 'modification_referentiel')->sole();
    expect($journal->user_id)->toBe($this->jeu->admin24->id)
        ->and($journal->objet)->toBe('ref.seuil_interpretation#'.$ancien['id'])
        ->and($journal->detail['operation'])->toBe('modification')
        ->and($journal->detail['version'])->toBe(versionE24(1))
        ->and($journal->detail['decision'])->toBe('Relèvement du seuil')
        ->and((float) $journal->detail['avant']['borne_max'])->toBe(80.0)
        ->and((float) $journal->detail['apres']['borne_max'])->toBe(85.0)
        ->and($journal->detail['apres']['id'])->toBe($nouveau);
});

it('gère les plages de plausibilité : création, modification, retrait, une seule plage active par paramètre et famille', function () {
    $foin = (array) DB::table('ref.plage_plausibilite')->where('parametre_code', 'MS')->where('famille_fourrage_code', 'FOIN')->whereNull('version_fin')->first();

    expect(fn () => $this->service->creerPlage(SaisiePlage::depuis(['parametre_code' => 'MS', 'famille_fourrage_code' => 'FOIN', 'borne_min' => '70', 'borne_max' => '96', 'statut_epistemique' => 'hypothese_locale']), 'Doublon', $this->jeu->admin24))
        ->toThrow(EditionRefusee::class);

    $nouvelle = $this->service->modifierPlage($foin['id'], SaisiePlage::depuis(['borne_min' => '70'] + $foin), 'Foin : borne basse à 70 % MS', $this->jeu->admin24);
    expect(DB::table('ref.plage_plausibilite')->find($foin['id'])->version_fin)->toBe(versionE24(1))
        ->and(collect($this->referentiels->plagesPlausibiliteActives())->first(fn ($p) => $p->parametreCode === 'MS' && $p->familleFourrageCode === 'FOIN')?->borneMin)->toBe(70.0);

    expect(fn () => $this->service->modifierPlage($nouvelle, SaisiePlage::depuis(['borne_min' => '99'] + $foin), 'Inversée', $this->jeu->admin24))
        ->toThrow(EditionRefusee::class);

    $creee = $this->service->creerPlage(SaisiePlage::depuis(['parametre_code' => 'MAT', 'famille_fourrage_code' => 'FOIN', 'borne_min' => '30', 'borne_max' => '250', 'statut_epistemique' => 'hypothese_locale']), 'MAT propre au foin', $this->jeu->admin24);
    expect($creee)->toBeGreaterThan(0);

    $this->service->retirerPlage($nouvelle, 'Retrait de la plage foin', $this->jeu->admin24);
    expect(collect($this->referentiels->plagesPlausibiliteActives())->first(fn ($p) => $p->parametreCode === 'MS' && $p->familleFourrageCode === 'FOIN'))->toBeNull();
});

it('fournit l\'historique : lignes créées et clôturées par version', function () {
    $ancien = seuilActifE24('MAT', 'faible');
    $nouveau = $this->service->modifierSeuil($ancien['id'], SaisieSeuil::depuis(['borne_max' => '85'] + $ancien), 'Relèvement', $this->jeu->admin24);

    $historique = $this->service->historique();
    expect(array_map(fn ($e) => $e['version']->numero, $historique))->toBe(array_map('strval', range((int) versionE24(1), 1, -1)));

    $v3 = $historique[0];
    expect($v3['auteur'])->toBe($this->jeu->admin24->nomComplet())
        ->and(array_column($v3['seuils_crees'], 'id'))->toBe([$nouveau])
        ->and(array_column($v3['seuils_clotures'], 'id'))->toBe([$ancien['id']])
        ->and($historique[array_key_last($historique)]['seuils_crees'])->not->toBeEmpty();
});

it('laisse lisible une interprétation enregistrée avec une version de grille antérieure', function () {
    $ancien = seuilActifE24('MAT', 'faible');
    $prelevement = DB::table('fourrage.prelevement')->insertGetId([
        'elevage_id' => $this->jeu->elevage24a->id, 'date_prelevement' => '2026-09-01', 'type_fourrage_code' => 'FOIN_PP',
    ]);
    $analyse = DB::table('fourrage.analyse')->insertGetId([
        'prelevement_id' => $prelevement, 'laboratoire_id' => DB::table('ref.laboratoire')->value('id'), 'num_echantillon_labo' => 'E24-0001',
    ]);
    $interpretation = DB::table('fourrage.interpretation')->insertGetId(['analyse_id' => $analyse, 'version_grille' => '2', 'statut' => 'publie']);
    DB::table('fourrage.alerte')->insert([
        'interpretation_id' => $interpretation, 'parametre_code' => 'MAT', 'seuil_id' => $ancien['id'], 'classe' => 'faible',
        'gravite' => 1, 'message' => (string) $ancien['message'],
    ]);

    $this->service->modifierSeuil($ancien['id'], SaisieSeuil::depuis(['borne_max' => '85'] + $ancien), 'Relèvement', $this->jeu->admin24);
    $this->service->retirerSeuil(seuilActifE24('MAT', 'faible')['id'], 'Retrait', $this->jeu->admin24);

    $lue = Interpretation::query()->withoutGlobalScopes()->findOrFail($interpretation);
    $seuilDeLAlerte = DB::table('fourrage.alerte')->join('ref.seuil_interpretation as s', 's.id', '=', 'fourrage.alerte.seuil_id')
        ->where('interpretation_id', $interpretation)->first(['s.borne_max', 's.version_creation', 's.version_fin']);

    expect($lue->version_grille)->toBe('2')
        ->and(DB::table('ref.version_referentiel')->where('numero', '2')->exists())->toBeTrue()
        ->and((float) $seuilDeLAlerte->borne_max)->toBe(80.0)
        ->and($seuilDeLAlerte->version_creation)->toBe('1')
        ->and($seuilDeLAlerte->version_fin)->toBe(versionE24(1));
});
