<?php

use App\Jobs\EnvoyerNotification;
use App\Livewire\Compte\PreferencesNotification;
use App\Mail\NotificationMail;
use App\Models\App\Notification;
use App\Models\Dossier\InterpretationVisite;
use App\Services\Dossier\EauService;
use App\Services\Dossier\InterpretationVisiteService;
use App\Services\Dossier\RedactionVisite;
use App\Services\Dossier\VisiteService;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use App\Services\Notifications\NotificationService;
use App\Services\Notifications\PasserelleSms;
use App\Services\Notifications\SmsBrevo;
use App\Services\Pdf\RenduPdf;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\AnalyseEssai;
use Tests\Support\JeuCloisonnement;

// Notifications (EF-M8-01, EF-M8-02, EF-M8-05, T-22) : e-mail à la publication d'une fiche ou d'un résultat, copie au
// technicien, SMS au niveau 3, journal app.notification, préférences du compte, envoi par Brevo.
uses(RefreshDatabase::class);

beforeEach(function () {
    Queue::fake();
    Mail::fake();
    Storage::fake('local');
    app()->instance(RenduPdf::class, new class implements RenduPdf
    {
        public function pdf(string $html, string $piedDePage): string
        {
            return '%PDF-1.7';
        }
    });
    $this->jeu = JeuCloisonnement::creer();
    $this->tech = $this->jeu->technicien24;
    $this->tech->update(['telephone' => '05 53 00 00 00']);
    $this->jeu->eleveur24a->update(['telephone' => '06 12 34 56 78']);
    $this->actingAs($this->tech);
    $this->visite = app(VisiteService::class)->creer($this->jeu->elevage24a, $this->tech, '2026-01-15', 'initiale');
    $eau = app(EauService::class)->creer($this->visite, 'Source', '2026-01-15', null, ['EAU_FER' => ['valeur' => 39]]);
    app(EauService::class)->valider($eau);
});

function publierVisiteNiveau(int $niveau): InterpretationVisite
{
    $service = app(InterpretationVisiteService::class);
    $iv = $service->creerBrouillon(test()->visite, test()->tech);
    $service->remplacerMessages($iv, [['gravite' => $niveau, 'texte' => 'Retirer immédiatement le foin moisi de la ration des vaches taries.']]);
    $service->enregistrer($iv, new RedactionVisite(null, 'Synthèse.', null, $niveau), test()->tech);
    $service->valider($iv->refresh(), test()->tech);
    if ($niveau === 3) {
        $service->viser($iv->refresh(), test()->jeu->veterinaireConseil24);
    }

    return $service->publier($iv->refresh(), test()->tech);
}

it('EF-M8-01 : journalise un e-mail à l\'éleveur et une copie au technicien à la publication d\'une visite', function () {
    $iv = publierVisiteNiveau(1);
    $notifications = Notification::query()->where('reference_objet', 'dossier.interpretation_visite:'.$iv->id)->orderBy('id')->get();

    expect($notifications->map(fn ($n) => [$n->user_id, $n->canal, $n->type, $n->statut])->all())->toBe([
        [$this->jeu->eleveur24a->id, 'email', 'fiche_publiee', 'a_envoyer'],
        [$this->tech->id, 'email', 'fiche_publiee', 'a_envoyer'],
    ])->and($notifications[0]->objet)->toBe('Votre fiche de visite du 15/01/2026 est disponible')
        ->and(substr_count((string) $notifications[0]->corps, "\n"))->toBe(2)
        ->and($notifications[0]->lien)->toBe(route('visites.interpretation', $this->visite->id))
        ->and($notifications[1]->objet)->toStartWith('Copie technicien — ');
    Queue::assertPushed(EnvoyerNotification::class, 2);
});

it('T-22 : envoie un SMS à l\'éleveur et au technicien pour une visite publiée de niveau 3, même désinscrits', function () {
    $this->jeu->eleveur24a->update(['pref_sms' => false, 'pref_email' => false]);
    $iv = publierVisiteNiveau(3);
    $sms = Notification::query()->where('reference_objet', 'dossier.interpretation_visite:'.$iv->id)->where('canal', 'sms')->orderBy('id')->get();

    expect($sms->pluck('user_id')->all())->toBe([$this->jeu->eleveur24a->id, $this->tech->id])
        ->and($sms->pluck('statut')->unique()->all())->toBe(['a_envoyer'])
        ->and($sms[0]->type)->toBe('niveau3')
        ->and((string) $sms[0]->corps)->toContain('alerte de niveau 3 pour EARL Essai Dordogne A (visite du 15/01/2026)')
        ->toContain('Retirer immédiatement le foin moisi')
        ->toContain('Contact : '.$this->tech->nomComplet().' 05 53 00 00 00')
        ->and(Notification::query()->where('user_id', $this->jeu->eleveur24a->id)->where('canal', 'email')->value('statut'))->toBe('a_envoyer');
});

it('respecte la désinscription hors niveau 3 et signale un numéro absent', function () {
    $this->jeu->eleveur24a->update(['pref_email' => false]);
    publierVisiteNiveau(1);

    expect(Notification::query()->where('user_id', $this->jeu->eleveur24a->id)->sole()->only(['statut', 'erreur']))
        ->toBe(['statut' => 'echec', 'erreur' => 'Désinscription des e-mails (préférence du compte).']);

    $this->tech->update(['telephone' => null]);
    $n = app(NotificationService::class)->envoyer($this->tech, 'sms', 'niveau3', 'Alerte', ['Texte'], obligatoire: true);
    expect($n->erreur)->toBe('Numéro de mobile absent ou invalide.');
});

it('notifie la publication d\'un bulletin seul, mais pas celle des lots publiés avec la visite', function () {
    $analyse = AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 61, 'CU' => 4.2]);
    $lots = app(InterpretationService::class);
    $lot = $lots->creerBrouillon($analyse, $this->tech);
    $lots->enregistrer($lot, new RedactionInterpretation(synthese: 'Foin pauvre.', niveauRetenu: 1));
    $lots->valider($lot->refresh(), $this->tech);
    $lots->publier($lot->refresh(), $this->tech);

    expect(Notification::query()->where('type', 'resultat_publie')->where('reference_objet', 'fourrage.interpretation:'.$lot->id)->count())->toBe(2);

    $analyse2 = AnalyseEssai::creer($this->jeu->elevage24a, ['MAT' => 70]);
    app(VisiteService::class)->rattacherPrelevements($this->visite, [$analyse2->prelevement_id]);
    $lot2 = $lots->creerBrouillon($analyse2, $this->tech);
    $lots->enregistrer($lot2, new RedactionInterpretation(synthese: 'Foin correct.', niveauRetenu: 1));
    $lots->valider($lot2->refresh(), $this->tech);
    publierVisiteNiveau(1);

    expect(Notification::query()->where('reference_objet', 'fourrage.interpretation:'.$lot2->id)->count())->toBe(0);
});

it('envoie l\'e-mail journalisé et reporte le statut d\'envoi', function () {
    $n = app(NotificationService::class)->envoyer($this->jeu->eleveur24a, 'email', 'fiche_publiee', 'Objet', ['Ligne 1', 'Ligne 2', 'Ligne 3'], 'https://exemple.test/visite');

    (new EnvoyerNotification($n->id))->handle(app(PasserelleSms::class));

    Mail::assertSent(NotificationMail::class, fn (NotificationMail $m) => $m->hasTo($this->jeu->eleveur24a->email)
        && $m->objet === 'Objet' && $m->lignes === ['Ligne 1', 'Ligne 2', 'Ligne 3'] && $m->lien === 'https://exemple.test/visite');
    expect($n->refresh()->statut)->toBe('envoye')->and($n->envoye_le)->not->toBeNull()->and($n->tentatives)->toBe(1);

    (new EnvoyerNotification($n->id))->handle(app(PasserelleSms::class));
    expect($n->refresh()->tentatives)->toBe(1);   // déjà envoyée : pas de second envoi
});

it('envoie le SMS par l\'API Brevo au format international et journalise l\'identifiant', function () {
    config(['services.brevo.cle_api' => 'cle-essai', 'services.brevo.expediteur_sms' => 'GDS24']);
    Http::fake(['api.brevo.com/*' => Http::response(['messageId' => 1234567, 'reference' => 'abc'], 201)]);
    $n = app(NotificationService::class)->envoyer($this->jeu->eleveur24a, 'sms', 'niveau3', 'Alerte', ['Texte du SMS'], obligatoire: true);

    (new EnvoyerNotification($n->id))->handle(new SmsBrevo);

    Http::assertSent(fn (Request $r) => $r->url() === 'https://api.brevo.com/v3/transactionalSMS/sms'
        && $r->hasHeader('api-key', 'cle-essai')
        && $r['recipient'] === '33612345678' && $r['sender'] === 'GDS24' && $r['content'] === 'Texte du SMS' && $r['type'] === 'transactional');
    expect($n->refresh()->only(['statut', 'id_brevo']))->toBe(['statut' => 'envoye', 'id_brevo' => '1234567']);
});

it('journalise un refus de Brevo sans relancer l\'envoi', function () {
    config(['services.brevo.cle_api' => 'cle-essai']);
    Http::fake(['api.brevo.com/*' => Http::response(['code' => 'not_enough_credits'], 402)]);
    $n = app(NotificationService::class)->envoyer($this->jeu->eleveur24a, 'sms', 'niveau3', 'Alerte', ['Texte'], obligatoire: true);

    (new EnvoyerNotification($n->id))->handle(new SmsBrevo);

    expect($n->refresh()->statut)->toBe('echec')->and($n->erreur)->toContain('402');
});

it('met les numéros au format international', function (?string $saisi, ?string $attendu) {
    expect(NotificationService::numeroInternational($saisi))->toBe($attendu);
})->with([
    ['06 12 34 56 78', '33612345678'], ['+33 6 12 34 56 78', '33612345678'], ['0612345678', '33612345678'],
    ['12 34', null], [null, null], ['', null],
]);

it('E17 : l\'éleveur règle ses préférences de notification et son numéro', function () {
    $this->actingAs($this->jeu->eleveur24a);

    Livewire::test(PreferencesNotification::class)
        ->assertSet('telephone', '06 12 34 56 78')
        ->set('email', false)->set('telephone', '07 00 00 00 01')
        ->call('enregistrer')
        ->assertHasNoErrors()
        ->set('telephone', '12')->call('enregistrer')->assertHasErrors('telephone');

    expect($this->jeu->eleveur24a->refresh()->only(['pref_email', 'pref_sms', 'telephone']))
        ->toBe(['pref_email' => false, 'pref_sms' => true, 'telephone' => '07 00 00 00 01']);

    $this->get(route('compte'))->assertOk()->assertSee('Préférences de notification')->assertSee('Mot de passe');
});
