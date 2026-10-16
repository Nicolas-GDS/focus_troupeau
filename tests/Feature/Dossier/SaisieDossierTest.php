<?php

use App\Domain\Dossier\ClasseBilan;
use App\Domain\Dossier\LectureProfil;
use App\Models\Dossier\AnalyseEau;
use App\Models\Dossier\MesureTerrain;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\AnalyseAnimale;
use App\Models\Fourrage\Prelevement;
use App\Services\Dossier\BilanSanitaireService;
use App\Services\Dossier\EauService;
use App\Services\Dossier\EtiquettesService;
use App\Services\Dossier\MesuresService;
use App\Services\Dossier\ProfilsService;
use App\Services\Dossier\SaisieRefusee;
use App\Services\Dossier\VisiteService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// Lot L3, étape 3 — saisie du dossier par le technicien (EF-M1-04 à EF-M1-07, EF-M1-09, EF-M9-02, RC-1.5),
// avec les référentiels chargés par la migration (présentation Forgeneuf).
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    DB::table('fourrage.elevage')->where('id', $this->jeu->elevage24a->id)->update(['systeme_bovin' => 'viande', 'especes' => '{BOV}']);
    $this->actingAs($this->jeu->technicien24);
    $this->visite = app(VisiteService::class)->creer($this->jeu->elevage24a->fresh(), $this->jeu->technicien24, '2026-01-15', 'initiale');
});

it('crée la visite dans sa campagne et rattache les seuls prélèvements de l\'élevage (EF-M1-09)', function () {
    $lot = Prelevement::query()->findOrFail(AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 61])->prelevement_id);
    $autre = Prelevement::query()->withoutGlobalScopes()->findOrFail(AnalyseEssai::creer($this->jeu->elevage24b, ['MAT' => 70])->prelevement_id);
    $service = app(VisiteService::class);

    expect($this->visite->campagne_code)->toBe('2025-2026')
        ->and($this->visite->statut)->toBe(Visite::STATUT_EN_COURS)
        ->and($this->visite->cree_par)->toBe($this->jeu->technicien24->id);

    // Hors de toute campagne définie (2025-2026 se termine le 31/08/2026) : pas de campagne
    expect(VisiteService::campagneDe('2026-09-03'))->toBeNull();

    $service->rattacherPrelevements($this->visite, [$lot->id]);
    expect($lot->fresh()?->visite_id)->toBe($this->visite->id);

    expect(fn () => $service->rattacherPrelevements($this->visite, [$lot->id, $autre->id]))->toThrow(SaisieRefusee::class);

    $service->rattacherPrelevements($this->visite, []);
    expect($lot->fresh()?->visite_id)->toBeNull();
});

it('saisit une analyse d\'eau et la lit par la grille eau, fer conforme, mention « repère à valider » (EF-M1-04)', function () {
    $service = app(EauService::class);
    $analyse = $service->creer($this->visite, 'Source', '2026-09-03', null, ['EAU_FER' => ['valeur' => 39], 'EAU_NITRATES' => ['valeur' => 12]]);

    $lignes = collect($service->lignes($analyse))->keyBy('code');

    expect($analyse->statut)->toBe(AnalyseEau::STATUT_CONTROLE)
        ->and($lignes['EAU_FER']['classe'])->toBe('conforme')
        ->and($lignes['EAU_FER']['repere'])->toBe('de 0 à moins de 200 µg/l')
        ->and($lignes['EAU_FER']['a_valider'])->toBeTrue()
        ->and($lignes['EAU_FER']['source'])->toContain('exemple EARL Forgeneuf')
        ->and($lignes['EAU_NITRATES']['classe'])->toBeNull();

    expect(fn () => $service->creer($this->visite, 'Forage', '2026-09-03', null, ['FE' => ['valeur' => 1]]))->toThrow(SaisieRefusee::class)
        ->and($service->valider($analyse)->statut)->toBe(AnalyseEau::STATUT_VALIDE);
});

it('lit chaque mesure terrain à la saisie et propose la lecture combinée du groupe (RC-1.5)', function () {
    $service = app(MesuresService::class);
    foreach (['GLYCEMIE' => [36, 42, 49], 'UREE' => [92, 100, 109], 'PH_BOUSE' => [5.69, 5.8, 5.94]] as $code => $valeurs) {
        foreach ($valeurs as $i => $valeur) {
            $service->ajouter($this->visite, $this->jeu->technicien24, '2026-09-03', $code, $valeur, 'BOV', 'prepa_velage', "79{$i}");
        }
    }
    $sansRepere = $service->ajouter($this->visite, $this->jeu->technicien24, '2026-09-03', 'GLYCEMIE', 50, 'OVI', null);

    $groupes = collect($service->groupes($this->visite))->keyBy('stade');
    $prepa = $groupes['prepa_velage'];
    $glycemie = collect($prepa['mesures'])->firstWhere('code', 'GLYCEMIE');

    expect(MesureTerrain::query()->where('mesure_code', 'GLYCEMIE')->where('valeur', 36)->value('lecture_code'))->toBe('bas')
        ->and(MesureTerrain::query()->where('mesure_code', 'UREE')->value('unite'))->toBe('mg/l')
        ->and($sansRepere->lecture_code)->toBeNull()
        ->and($glycemie['mediane'])->toBe(42.0)
        ->and($glycemie['repere'])->toBe('de 60 à 70 mg/dl')
        ->and($glycemie['source']['a_valider'])->toBeTrue()
        ->and(array_column($prepa['lectures'], 'libelle'))->toBe(['Manque d\'azote avec excès relatif de sucres probable']);

    expect($service->validerVisite($this->visite, $this->jeu->technicien24))->toBe(10)
        ->and(MesureTerrain::query()->where('statut', MesureTerrain::STATUT_SAISIE)->count())->toBe(0);
});

it('lit un profil sanguin dans l\'unité du repère seulement (RC-1.5)', function () {
    $service = app(ProfilsService::class);
    $base = ['date_prelevement' => '2026-09-03', 'espece_code' => 'BOV', 'matrice' => 'serum', 'nb_animaux' => 5];
    $selenium = $service->ajouter($this->visite, $this->jeu->technicien24, [...$base, 'parametre' => 'selenium', 'valeur_moyenne' => 0.03, 'unite' => 'mg/l']);
    $autreUnite = $service->ajouter($this->visite, $this->jeu->technicien24, [...$base, 'parametre' => 'selenium', 'valeur_moyenne' => 30, 'unite' => 'µg/l']);

    $lignes = collect($service->lignes($this->visite))->keyBy(fn ($l) => $l['profil']->id);

    expect($selenium->lecture_code)->toBe(LectureProfil::CarenceSevere->value)
        ->and($autreUnite->lecture_code)->toBeNull()
        ->and($lignes[$autreUnite->id]['unite_differente'])->toBeTrue()
        ->and($lignes[$selenium->id]['repere'])->toBe('de 0,06 à 0,36 mg/l')
        ->and($service->valider($selenium)->statut)->toBe(AnalyseAnimale::STATUT_VALIDEE);

    expect(fn () => $service->ajouter($this->visite, $this->jeu->technicien24, [...$base, 'parametre' => 'iode', 'valeur_moyenne' => 0.05, 'valeur_min' => 0.07, 'valeur_max' => 0.04, 'unite' => 'mg/l']))
        ->toThrow(SaisieRefusee::class);
});

it('calcule l\'apport journalier d\'une étiquette et signale la date limite dépassée (EF-M1-06)', function () {
    $service = app(EtiquettesService::class);
    $etiquette = $service->creer($this->jeu->elevage24a, $this->visite->id,
        ['nature' => 'mineral', 'libelle_eleveur' => 'Minéral du sac', 'dose_g_par_animal_jour' => 100, 'date_limite' => '2025-04-13'],
        ['SE' => ['valeur' => 40, 'unite' => 'mg/kg'], 'CA' => ['valeur' => 3, 'unite' => '%']]);

    $lecture = $service->lecture($etiquette, Carbon::parse('2026-09-03'));
    $composition = collect($lecture['composition'])->keyBy('code');

    expect($lecture['perimee'])->toBeTrue()
        ->and($composition['SE']['apport'])->toBe(['valeur' => 4.0, 'unite' => 'mg'])
        ->and($composition['CA']['apport'])->toBe(['valeur' => 3.0, 'unite' => 'g'])
        ->and($etiquette->cree_par)->toBe($this->jeu->technicien24->id);

    expect(fn () => $service->creer($this->jeu->elevage24a, null, ['nature' => 'mineral', 'libelle_eleveur' => 'X'], ['SE' => ['valeur' => 1, 'unite' => 'ppm']]))
        ->toThrow(SaisieRefusee::class);
});

it('situe les indicateurs du bilan sanitaire par rapport à S1 / S2, avec la campagne précédente (EF-M1-07)', function () {
    DB::table('fourrage.campagne')->insert(['code' => '2024-2025', 'libelle' => 'Campagne 2024-2025', 'date_debut' => '2024-09-01', 'date_fin' => '2025-08-31']);
    $service = app(BilanSanitaireService::class);
    $elevage = $this->jeu->elevage24a->fresh();
    $service->enregistrer($elevage, '2025-2026', 'METRITE', 5, 81, null);
    $service->enregistrer($elevage, '2025-2026', 'IVV', null, null, 406);
    $service->enregistrer($elevage, '2024-2025', 'IVV', null, null, 399);

    $lignes = collect($service->lignes($elevage, '2025-2026'))->keyBy('code');

    expect($lignes['METRITE']['valeur'])->toEqualWithDelta(6.17, 0.01)
        ->and($lignes['METRITE']['classe'])->toBe(ClasseBilan::Degradee)
        ->and($lignes['METRITE']['seuils'])->toBe('S1 5 / S2 10 %')
        ->and($lignes['METRITE']['source']['a_valider'])->toBeTrue()
        ->and($lignes['IVV']['classe'])->toBeNull()
        ->and($lignes['IVV']['precedente'])->toBe(399.0)
        ->and($lignes['AVORTEMENT']['valeur'])->toBeNull();

    expect(fn () => $service->enregistrer($elevage, '2025-2026', 'METRITE', 90, 81, null))->toThrow(SaisieRefusee::class);

    $service->declarerEvenement($elevage, ['espece_code' => 'BOV', 'date_evenement' => '2026-04-15', 'type_evenement' => 'boiteries']);
    expect($service->evenements($elevage))->toHaveCount(1);
});
