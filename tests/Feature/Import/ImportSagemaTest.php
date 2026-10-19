<?php

use App\Livewire\Import\ImportSagema;
use App\Services\Dossier\SaisieRefusee;
use App\Services\Import\ImportSagemaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// T-27 — import SAGEMA (EF-M9-03, § 6.4) : création et mise à jour par EDE, rapport d'import, aucune écriture externe ;
// aperçu sans écriture, valeur vide sans effet, saisie manuelle du bilan sanitaire conservée, cloisonnement.
uses(RefreshDatabase::class);

beforeEach(function () {
    Http::preventStrayRequests();
    $this->jeu = JeuCloisonnement::creer();
    $this->service = app(ImportSagemaService::class);
    $this->elevages = (string) file_get_contents(base_path('tests/Fixtures/sagema/elevages.csv'));
    $this->indicateurs = (string) file_get_contents(base_path('tests/Fixtures/sagema/indicateurs.csv'));
    $this->identite = fn (string $ede) => DB::table('prive.elevage_identite')->where('num_cheptel_ede', $ede)->first();
});

it('T-27 : crée les élevages par EDE, rejette les lignes invalides avec leur motif, sans écrire à l\'aperçu', function () {
    $apercu = $this->service->elevages($this->elevages, 24, $this->jeu->technicien24, false);
    expect($apercu['totaux'])->toBe(['cree' => 2, 'mis_a_jour' => 0, 'inchange' => 0, 'rejete' => 4])
        ->and(($this->identite)('FR24000001'))->toBeNull();

    $rapport = $this->service->elevages($this->elevages, 24, $this->jeu->technicien24, true);
    $motifs = collect($rapport['lignes'])->where('action', 'rejete')->pluck('motif', 'numero')->all();
    $ruisseau = ($this->identite)('FR24000001');
    $elevage = DB::table('fourrage.elevage')->where('id', $ruisseau->elevage_id)->first();

    expect($rapport['totaux'])->toBe($apercu['totaux'])
        ->and($motifs[4])->toContain('double')->and($motifs[5])->toContain('EDE')->and($motifs[6])->toContain('Raison sociale')->and($motifs[7])->toContain('Courriel')
        ->and([$ruisseau->raison_sociale, $ruisseau->nom_contact, $ruisseau->code_postal, $ruisseau->consentement_donnees])->toBe(['EARL du Ruisseau', 'Paul Lenoir', '24100', false])
        ->and([$elevage->gds_id, $elevage->effectif_bovins, $elevage->especes, $elevage->adherent_gds])->toBe([24, 85, '{BOV}', true])
        ->and($elevage->code_pseudo)->toMatch('/^ELV-\d{4}$/')
        ->and(DB::table('fourrage.elevage')->where('id', ($this->identite)('FR24000002')->elevage_id)->value('especes'))->toBe('{BOV,OVI}')
        ->and(DB::table('app.journal')->where('action', 'import_sagema')->value('detail'))->toContain('"cree": 2');
});

it('T-27 : met à jour sans effacer, laisse inchangé un élevage identique', function () {
    $this->service->elevages($this->elevages, 24, $this->jeu->technicien24, true);
    $maj = "num_ede;siret;raison_sociale;contact;adresse;cp;commune;telephone;courriel;especes;effectif_bovins;effectif_ovins;effectif_caprins;adherent\n"
        ."FR24000001;;EARL du Ruisseau;;;24100;;;;;92;;;oui\n"
        ."FR24000002;;GAEC des Coteaux;;;24200;;;;BOV,OVI;120;40;;oui\n";
    $rapport = $this->service->elevages($maj, 24, $this->jeu->technicien24, true);

    expect($rapport['totaux'])->toMatchArray(['mis_a_jour' => 1, 'inchange' => 1, 'cree' => 0])
        ->and($rapport['lignes'][0]['changements'])->toBe(['effectif_bovins'])
        ->and(($this->identite)('FR24000001')->nom_contact)->toBe('Paul Lenoir')
        ->and(DB::table('fourrage.elevage')->where('id', ($this->identite)('FR24000001')->elevage_id)->value('effectif_bovins'))->toBe(92);
});

it('T-27 : importe les indicateurs du bilan sanitaire, taux calculé, saisie manuelle conservée', function () {
    $this->service->elevages($this->elevages, 24, $this->jeu->technicien24, true);
    $elevageId = ($this->identite)('FR24000001')->elevage_id;
    DB::table('dossier.indicateur_sanitaire')->insert(['elevage_id' => $elevageId, 'campagne_code' => '2025-2026', 'indicateur_code' => 'IVV', 'valeur' => 380, 'source' => 'saisie', 'importe_le' => now(), 'maj_le' => now()]);

    $rapport = $this->service->indicateurs($this->indicateurs, $this->jeu->technicien24, true);
    $motifs = collect($rapport['lignes'])->pluck('motif', 'numero')->all();

    expect($rapport['totaux'])->toBe(['cree' => 1, 'mis_a_jour' => 0, 'inchange' => 1, 'rejete' => 4])
        ->and((float) DB::table('dossier.indicateur_sanitaire')->where('elevage_id', $elevageId)->where('indicateur_code', 'NON_DELIVRANCE')->value('valeur'))->toBe(7.5)
        ->and(DB::table('dossier.indicateur_sanitaire')->where('elevage_id', $elevageId)->where('indicateur_code', 'NON_DELIVRANCE')->value('source'))->toBe('sagema')
        ->and((float) DB::table('dossier.indicateur_sanitaire')->where('elevage_id', $elevageId)->where('indicateur_code', 'IVV')->value('valeur'))->toBe(380.0)
        ->and($motifs[3])->toContain('conservée')->and($motifs[4])->toContain('Indicateur inconnu')->and($motifs[5])->toContain('Campagne inconnue')
        ->and($motifs[6])->toContain('Élevage inconnu')->and($motifs[7])->toContain('supérieur');
});

it('refuse un en-tête incomplet et un EDE connu hors périmètre', function () {
    expect(fn () => $this->service->elevages("num_ede;raison_sociale\nFR24000001;X", 24, $this->jeu->technicien24, false))->toThrow(SaisieRefusee::class)
        ->and(fn () => $this->service->elevages($this->elevages, 47, $this->jeu->technicien24, false))->toThrow(SaisieRefusee::class);

    $this->service->elevages($this->elevages, 24, $this->jeu->technicien24, true);
    $rapport = $this->service->elevages($this->elevages, 47, $this->jeu->technicien47, false);
    expect($rapport['lignes'][0]['motif'])->toBe('Numéro EDE déjà connu hors de votre périmètre.');
});

it('lit un fichier Windows-1252 depuis l\'écran, aperçu puis import (T-27)', function () {
    $this->actingAs($this->jeu->technicien24);
    $fichier = UploadedFile::fake()->createWithContent('sagema.csv', mb_convert_encoding($this->elevages, 'Windows-1252', 'UTF-8'));

    Livewire::test(ImportSagema::class)
        ->set('fichier', $fichier)->call('apercu')->assertHasNoErrors()->assertSee('Aperçu')->assertSee('Créé : 2')
        ->call('importer')->assertSee('Rapport d\'import');
    expect(($this->identite)('FR24000005'))->toBeNull()->and(($this->identite)('FR24000002')->raison_sociale)->toBe('GAEC des Coteaux');

    $this->actingAs($this->jeu->admin24);
    $this->get(route('import.sagema'))->assertForbidden();
});
