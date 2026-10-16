<?php

use App\Models\Dossier\Action;
use App\Models\Dossier\AnalyseEau;
use App\Models\Dossier\CauseCommune;
use App\Models\Dossier\Controle;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Dossier\EtiquetteComposition;
use App\Models\Dossier\IndicateurSanitaire;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\Message;
use App\Models\Dossier\MesureTerrain;
use App\Models\Dossier\NoteLiaison;
use App\Models\Dossier\PlanAction;
use App\Models\Dossier\ResultatEau;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\AnalyseAnimale;
use App\Models\Fourrage\Elevage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\JeuCloisonnement;

// Chapitre 6.3 : cloisonnement du dossier (lot L3), objets rattachés directement à l'élevage ou par la chaîne
// visite → interprétation de visite → plan d'action → action → contrôle.
uses(RefreshDatabase::class);

/** Référentiels minimaux pour les clés étrangères des mesures terrain et du bilan sanitaire (structures du lot L3). */
function referentielsDossierEssai(): void
{
    DB::table('ref.mesure_terrain')->insertOrIgnore(['code' => 'PH_BOUSE', 'libelle' => 'pH des bouses', 'unite' => '-']);
    DB::table('ref.indicateur_sanitaire')->insertOrIgnore(['code' => 'IVV', 'libelle' => 'Intervalle vêlage-vêlage', 'unite' => 'jours', 'mode' => 'valeur']);
}

/** Un dossier complet (visite, eau, mesure, profil, étiquette, bilan, interprétation de visite, plan, note). */
function dossierPour(Elevage $elevage, string $motifDetail): void
{
    referentielsDossierEssai();

    $visite = DB::table('dossier.visite')->insertGetId([
        'elevage_id' => $elevage->id, 'date_visite' => '2026-01-15', 'motif' => 'initiale', 'motif_detail' => $motifDetail,
    ]);
    $eau = DB::table('dossier.analyse_eau')->insertGetId([
        'elevage_id' => $elevage->id, 'visite_id' => $visite, 'point_prelevement' => 'forage', 'date_prelevement' => '2026-01-15',
    ]);
    DB::table('ref.parametre')->insertOrIgnore([
        'code' => 'EAU_ESSAI', 'libelle' => 'Paramètre d\'essai', 'famille_code' => 'EAU', 'unite' => 'mg/L', 'base' => 'sans_objet',
    ]);
    DB::table('dossier.resultat_eau')->insert(['analyse_eau_id' => $eau, 'parametre_code' => 'EAU_ESSAI', 'valeur' => 1, 'unite' => 'mg/L']);
    DB::table('dossier.mesure_terrain')->insert([
        'elevage_id' => $elevage->id, 'visite_id' => $visite, 'date_mesure' => '2026-01-15', 'mesure_code' => 'PH_BOUSE',
        'valeur' => 6.4, 'unite' => '-',
    ]);
    DB::table('fourrage.analyse_animale')->insert([
        'elevage_id' => $elevage->id, 'visite_id' => $visite, 'date_prelevement' => '2026-01-15', 'espece_code' => 'BOV',
        'matrice' => 'serum', 'parametre' => 'cupremie', 'valeur_moyenne' => 10, 'unite' => 'µmol/L',
    ]);
    $etiquette = DB::table('dossier.etiquette_aliment')->insertGetId([
        'elevage_id' => $elevage->id, 'nature' => 'mineral', 'libelle_eleveur' => 'Minéral du commerce',
    ]);
    DB::table('dossier.etiquette_composition')->insert(['etiquette_id' => $etiquette, 'parametre_code' => 'CU', 'valeur' => 1000, 'unite' => 'mg/kg']);
    DB::table('dossier.indicateur_sanitaire')->insert([
        'elevage_id' => $elevage->id, 'campagne_code' => '2025-2026', 'indicateur_code' => 'IVV', 'valeur' => 380, 'source' => 'saisie',
    ]);
    $interpretation = DB::table('dossier.interpretation_visite')->insertGetId(['visite_id' => $visite, 'version_grille' => '3']);
    DB::table('dossier.message')->insert(['interpretation_visite_id' => $interpretation, 'ordre' => 1, 'gravite' => 1, 'texte' => 'Message']);
    DB::table('dossier.cause_commune')->insert([
        'interpretation_visite_id' => $interpretation, 'famille_cause' => 'immunite_oligo', 'titre' => 'Cause',
    ]);
    $plan = DB::table('dossier.plan_action')->insertGetId(['interpretation_visite_id' => $interpretation]);
    $action = DB::table('dossier.action')->insertGetId([
        'plan_action_id' => $plan, 'priorite' => 1, 'ordre' => 1, 'responsable' => 'eleveur', 'titre' => 'Action',
    ]);
    DB::table('dossier.controle')->insert(['action_id' => $action, 'cible' => 'Cible', 'echeance' => '2026-03-01']);
    DB::table('dossier.note_liaison')->insert(['elevage_id' => $elevage->id, 'interpretation_visite_id' => $interpretation]);
}

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    dossierPour($this->jeu->elevage24a, 'V-24A');
    dossierPour($this->jeu->elevage24b, 'V-24B');
    dossierPour($this->jeu->elevage47, 'V-47');
});

it('limite le dossier et tout ce qui en dépend au périmètre de chaque profil J4', function (string $profil, array $attendus) {
    $this->actingAs($this->jeu->{$profil});

    $visites = Visite::query()->orderBy('motif_detail')->pluck('motif_detail')->all();
    $n = count($attendus);

    expect($visites)->toBe($attendus);

    foreach ([
        AnalyseEau::class, ResultatEau::class, MesureTerrain::class, AnalyseAnimale::class, EtiquetteAliment::class,
        EtiquetteComposition::class, IndicateurSanitaire::class, InterpretationVisite::class, Message::class,
        CauseCommune::class, PlanAction::class, Action::class, Controle::class, NoteLiaison::class,
    ] as $modele) {
        expect($modele::query()->count())->toBe($n, $modele);
    }
})->with([
    'technicien du GDS 24' => ['technicien24', ['V-24A', 'V-24B']],
    'technicien du GDS 47' => ['technicien47', ['V-47']],
    'chargé de mission bi-GDS' => ['chargeDeMission', ['V-24A', 'V-24B', 'V-47']],
    'vétérinaire conseil du GDS 24' => ['veterinaireConseil24', ['V-24A', 'V-24B']],
    'super-administrateur' => ['adminGlobal', ['V-24A', 'V-24B', 'V-47']],
    'éleveur de 24A' => ['eleveur24a', ['V-24A']],
    'éleveur du 47' => ['eleveur47', ['V-47']],
    'vétérinaire consenti sur 24A' => ['veterinaire', ['V-24A']],
]);

it('retire au vétérinaire le dossier de l\'élevage dès la révocation du consentement', function () {
    $this->actingAs($this->jeu->veterinaire);
    expect(Controle::query()->count())->toBe(1);

    $this->jeu->consentementVeterinaire->update(['revoque_le' => now()]);

    expect(Visite::query()->count())->toBe(0)
        ->and(Controle::query()->count())->toBe(0)
        ->and(AnalyseAnimale::query()->count())->toBe(0);
});

it('rend une visite hors périmètre introuvable par son identifiant', function () {
    $visite47 = DB::table('dossier.visite')->where('motif_detail', 'V-47')->value('id');

    $this->actingAs($this->jeu->technicien24);

    expect(Visite::query()->find($visite47))->toBeNull();
});
