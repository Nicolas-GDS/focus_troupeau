<?php

namespace App\Providers;

use App\Domain\Referentiels\Referentiels;
use App\Listeners\JournaliserAuthentification;
use App\Models\App\Consentement;
use App\Models\App\Convention;
use App\Models\App\ImportBulletin;
use App\Models\Dossier\Action;
use App\Models\Dossier\AnalyseEau;
use App\Models\Dossier\Controle;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Dossier\IndicateurSanitaire;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\MesureTerrain;
use App\Models\Dossier\NoteLiaison;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\AnalyseAnimale;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Interpretation;
use App\Models\Plan\LotAnimaux;
use App\Models\Plan\Phase;
use App\Models\Plan\Ration;
use App\Models\Plan\RationComposant;
use App\Models\Ref\PlagePlausibilite;
use App\Models\Ref\SeuilInterpretation;
use App\Models\Ref\VersionReferentiel;
use App\Models\Stock\Inventaire;
use App\Models\Stock\LotStock;
use App\Models\Stock\Mouvement;
use App\Models\Stock\PeriodePhase;
use App\Policies\ActionPolicy;
use App\Policies\AnalyseAnimalePolicy;
use App\Policies\AnalyseEauPolicy;
use App\Policies\AnalysePolicy;
use App\Policies\ConsentementPolicy;
use App\Policies\ConventionPolicy;
use App\Policies\ElevagePolicy;
use App\Policies\EtiquetteAlimentPolicy;
use App\Policies\ImportBulletinPolicy;
use App\Policies\IndicateurSanitairePolicy;
use App\Policies\InterpretationPolicy;
use App\Policies\InterpretationVisitePolicy;
use App\Policies\MesureTerrainPolicy;
use App\Policies\NoteLiaisonPolicy;
use App\Policies\PlanPolicy;
use App\Policies\ReferentielPolicy;
use App\Policies\StockPolicy;
use App\Policies\VisitePolicy;
use App\Services\Import\ExtracteurBulletin;
use App\Services\Import\ExtracteurLano;
use App\Services\Notifications\PasserelleSms;
use App\Services\Notifications\SmsBrevo;
use App\Services\Notifications\SmsJournal;
use App\Services\Pdf\RenduBrowsershot;
use App\Services\Pdf\RenduPdf;
use App\Services\Referentiels\ReferentielsBdd;
use App\View\Composers\EspaceComposer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Microsoft\MicrosoftExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Référentiels (chapitre 6.2) : interface du domaine, implémentation en base avec cache.
        $this->app->singleton(Referentiels::class, ReferentielsBdd::class);

        // Extraction des bulletins (EF-M9-01) : script LANO existant ; un export structuré la remplacera.
        $this->app->bind(ExtracteurBulletin::class, ExtracteurLano::class);
        $this->app->bind(RenduPdf::class, RenduBrowsershot::class);
        $this->app->bind(PasserelleSms::class, fn () => config('services.brevo.pilote_sms') === 'brevo' ? new SmsBrevo : new SmsJournal);
    }

    public function boot(): void
    {
        // Hors production : une affectation ignorée (attribut non modifiable) ou un chargement paresseux
        // de relation lève une erreur au lieu de passer inaperçu.
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        Model::preventLazyLoading(! $this->app->isProduction());

        // ENF-SEC-02 : 12 caractères minimum, vérification contre les mots de passe compromis
        // (Have I Been Pwned, k-anonymat : seuls les 5 premiers caractères de l'empreinte SHA-1 sont transmis).
        Password::defaults(fn () => Password::min(12)->uncompromised());

        // Policies (chapitre 6.3, ENF-SEC-04) : déclarées explicitement, les modèles étant rangés par schéma.
        Gate::policy(Elevage::class, ElevagePolicy::class);
        Gate::policy(Convention::class, ConventionPolicy::class);
        Gate::policy(Consentement::class, ConsentementPolicy::class);
        Gate::policy(ImportBulletin::class, ImportBulletinPolicy::class);
        Gate::policy(Analyse::class, AnalysePolicy::class);
        Gate::policy(Interpretation::class, InterpretationPolicy::class);
        // Lot L3 — dossier (§ 2.3)
        Gate::policy(Visite::class, VisitePolicy::class);
        Gate::policy(AnalyseEau::class, AnalyseEauPolicy::class);
        Gate::policy(MesureTerrain::class, MesureTerrainPolicy::class);
        Gate::policy(AnalyseAnimale::class, AnalyseAnimalePolicy::class);
        Gate::policy(EtiquetteAliment::class, EtiquetteAlimentPolicy::class);
        Gate::policy(IndicateurSanitaire::class, IndicateurSanitairePolicy::class);
        Gate::policy(InterpretationVisite::class, InterpretationVisitePolicy::class);
        Gate::policy(Action::class, ActionPolicy::class);
        Gate::policy(Controle::class, ActionPolicy::class);
        Gate::policy(NoteLiaison::class, NoteLiaisonPolicy::class);
        // Lot L5 — stocks et troupeau (§ 2.3)
        foreach ([Inventaire::class, LotStock::class, Mouvement::class, PeriodePhase::class] as $modele) {
            Gate::policy($modele, StockPolicy::class);
        }
        foreach ([LotAnimaux::class, Phase::class, Ration::class, RationComposant::class] as $modele) {
            Gate::policy($modele, PlanPolicy::class);
        }
        // E24 — Référentiels versionnés
        Gate::policy(VersionReferentiel::class, ReferentielPolicy::class);
        Gate::policy(SeuilInterpretation::class, ReferentielPolicy::class);
        Gate::policy(PlagePlausibilite::class, ReferentielPolicy::class);

        View::composer(['components.layouts.espace', 'espace.*', 'rgpd.*'], EspaceComposer::class);

        Event::subscribe(JournaliserAuthentification::class);
        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);
    }
}
