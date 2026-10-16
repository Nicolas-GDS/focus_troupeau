<?php

use App\Livewire\Dossier\Interpretation\NotesLiaison;
use App\Livewire\Dossier\NoteLiaisonVeterinaire;
use App\Models\App\Notification;
use App\Models\App\User;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\NoteLiaison;
use App\Services\Dossier\BilanSanitaireService;
use App\Services\Dossier\InterpretationVisiteService;
use App\Services\Dossier\NoteLiaisonService;
use App\Services\Dossier\RedactionVisite;
use App\Services\Dossier\SaisieRefusee;
use App\Services\Dossier\VisiteService;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use App\Services\Pdf\RenduPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// Note de liaison vétérinaire (EF-M2-09 ; document 04, § 4.1) : niveaux 2 et 3, vétérinaire désigné par consentement
// (§ 2.5), PDF archivé, e-mail avec lien, retour du vétérinaire ; T-40 sur les routes de la note.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Mail::fake();
    Storage::fake('local');
    app()->instance(RenduPdf::class, new class implements RenduPdf
    {
        public static ?string $dernier = null;

        public function pdf(string $html, string $piedDePage): string
        {
            self::$dernier = $html;

            return '%PDF-1.7 note';
        }
    });
    $this->jeu = JeuCloisonnement::creer();
    $this->tech = $this->jeu->technicien24;
    $this->actingAs($this->tech);
    $this->visite = app(VisiteService::class)->creer($this->jeu->elevage24a, $this->tech, '2026-01-15', 'initiale');
    $analyse = AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 61, 'CU' => 4.2]);
    app(VisiteService::class)->rattacherPrelevements($this->visite, [$analyse->prelevement_id]);
    $lots = app(InterpretationService::class);
    $lot = $lots->creerBrouillon($analyse, $this->tech);
    $lots->enregistrer($lot, new RedactionInterpretation(synthese: 'Foin pauvre.', niveauRetenu: 2));
    $lots->valider($lot->refresh(), $this->tech);
    DB::table('prive.elevage_identite')->where('elevage_id', $this->jeu->elevage24a->id)->update(['num_cheptel_ede' => 'FR24123456']);
    app(BilanSanitaireService::class)->declarerEvenement($this->jeu->elevage24a,
        ['espece_code' => 'BOV', 'date_evenement' => '2025-12-01', 'type_evenement' => 'retention_placentaire', 'nb_animaux' => 4]);
});

function interpretationNiveau(int $niveau): InterpretationVisite
{
    $service = app(InterpretationVisiteService::class);
    $iv = $service->creerBrouillon(test()->visite, test()->tech);
    $service->enregistrer($iv, new RedactionVisite(null, 'Carence probable en cuivre.', null, $niveau), test()->tech);

    return $service->valider($iv->refresh(), test()->tech);
}

it('n\'ouvre la note qu\'aux interprétations validées de niveau 2 ou 3, et au seul technicien', function () {
    $iv1 = interpretationNiveau(1);

    expect(NoteLiaisonService::eligible($iv1))->toBeFalse()
        ->and(fn () => app(NoteLiaisonService::class)->envoyer($iv1, $this->jeu->veterinaire, '', 'Une question précise ?', $this->tech))->toThrow(SaisieRefusee::class);

    $iv1->update(['niveau_global' => 2]);
    expect(Gate::forUser($this->tech)->allows('create', [NoteLiaison::class, $iv1->refresh()]))->toBeTrue()
        ->and(Gate::forUser($this->jeu->veterinaireConseil24)->allows('create', [NoteLiaison::class, $iv1]))->toBeFalse();
});

it('n\'adresse la note qu\'à un vétérinaire désigné par un consentement actif', function () {
    $iv = interpretationNiveau(2);
    $autre = User::factory()->create();

    expect(app(NoteLiaisonService::class)->veterinairesConsentis($this->jeu->elevage24a->id)->pluck('id')->all())->toBe([$this->jeu->veterinaire->id])
        ->and(fn () => app(NoteLiaisonService::class)->envoyer($iv, $autre, '', 'Une question précise ?', $this->tech))->toThrow(SaisieRefusee::class);
});

it('génère la note selon le gabarit du document 04, l\'archive et prévient le vétérinaire par e-mail', function () {
    $iv = interpretationNiveau(2);
    $note = app(NoteLiaisonService::class)->envoyer($iv, $this->jeu->veterinaire, '5 vaches en préparation au vêlage',
        'Un profil métabolique Se, cuprémie et zinc sur 5 vaches est-il indiqué ?', $this->tech);
    $html = (string) (app(RenduPdf::class))::$dernier;

    expect($note->statut)->toBe(NoteLiaison::STATUT_ENVOYEE)
        ->and(Storage::disk('local')->exists((string) $note->pdf))->toBeTrue()
        ->and($html)->toContain('Note de liaison au vétérinaire')
        ->toContain('EDE FR24123456')
        ->toContain('5 vaches en préparation au vêlage')
        ->toContain('Rétention placentaire')
        ->toContain('Ce que montrent les fourrages')
        ->toContain('Un profil métabolique Se, cuprémie et zinc')
        ->toContain('Résultats des analyses animales (saisis dans la base), prescription éventuelle, date de point.')
        ->toContain('Non dosés dans les fourrages');

    $mail = Notification::query()->where('reference_objet', 'dossier.note_liaison:'.$note->id)->sole();
    expect($mail->only(['user_id', 'canal', 'type']))->toBe(['user_id' => $this->jeu->veterinaire->id, 'canal' => 'email', 'type' => 'note_liaison'])
        ->and($mail->lien)->toBe(route('notes-liaison.voir', $note->id))
        ->and(DB::table('app.journal')->where('action', 'envoi_note_liaison')->exists())->toBeTrue();
});

it('laisse le vétérinaire destinataire lire la note et répondre ; le technicien est prévenu', function () {
    $note = app(NoteLiaisonService::class)->envoyer(interpretationNiveau(3), $this->jeu->veterinaire, '', 'Bolus de sélénium conseillé ?', $this->tech);

    $this->actingAs($this->jeu->veterinaire);
    Livewire::test(NoteLiaisonVeterinaire::class, ['note' => $note])
        ->assertSee('Bolus de sélénium conseillé ?')
        ->set('retour', 'Profil prescrit, point le 15/03.')
        ->call('repondre')
        ->assertHasNoErrors()
        ->assertSee('Profil prescrit, point le 15/03.');

    expect($note->refresh()->statut)->toBe(NoteLiaison::STATUT_RETOUR_RECU)
        ->and(Notification::query()->where('type', 'note_liaison_retour')->value('user_id'))->toBe($this->tech->id);

    Livewire::test(NoteLiaisonVeterinaire::class, ['note' => $note->refresh()])->call('repondre')->assertForbidden();
});

it('T-40 : ouvre la note au destinataire et aux agents du périmètre ; la retire au vétérinaire dont le consentement est révoqué', function () {
    $note = app(NoteLiaisonService::class)->envoyer(interpretationNiveau(2), $this->jeu->veterinaire, '', 'Une question précise ?', $this->tech);
    $url = route('notes-liaison.voir', $note->id);
    $pdf = URL::temporarySignedRoute('notes-liaison.pdf', now()->addMinutes(5), ['note' => $note->id]);

    foreach (['veterinaire' => 200, 'technicien24' => 200, 'veterinaireConseil24' => 200, 'eleveur24a' => 403, 'technicien47' => 404, 'eleveur47' => 404] as $profil => $statut) {
        $this->actingAs($this->jeu->{$profil})->get($url)->assertStatus($statut);
        $this->actingAs($this->jeu->{$profil})->get($pdf)->assertStatus($statut);
    }
    $this->actingAs($this->jeu->veterinaire)->get(route('notes-liaison.pdf', $note->id))->assertForbidden();

    $this->jeu->consentementVeterinaire->update(['revoque_le' => now()]);
    $this->actingAs($this->jeu->veterinaire)->get($url)->assertNotFound();
});

it('envoie la note depuis E21', function () {
    interpretationNiveau(2);

    Livewire::test(NotesLiaison::class, ['visiteId' => $this->visite->id])
        ->assertSee('Générer et envoyer la note')
        ->set('veterinaire', (string) $this->jeu->veterinaire->id)
        ->set('lotAnimaux', 'Génisses')
        ->set('question', 'Faut-il une cuprémie sur les génisses ?')
        ->call('envoyer')
        ->assertHasNoErrors()
        ->assertSee('Faut-il une cuprémie sur les génisses ?');

    $this->actingAs($this->jeu->veterinaireConseil24);
    Livewire::test(NotesLiaison::class, ['visiteId' => $this->visite->id])
        ->set('veterinaire', (string) $this->jeu->veterinaire->id)->set('question', 'Question du vétérinaire conseil ?')
        ->call('envoyer')->assertForbidden();
});
