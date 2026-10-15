<?php

namespace App\Providers;

use App\Listeners\JournaliserAuthentification;
use App\Models\App\Consentement;
use App\Models\App\Convention;
use App\Models\Fourrage\Elevage;
use App\Policies\ConsentementPolicy;
use App\Policies\ConventionPolicy;
use App\Policies\ElevagePolicy;
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
        //
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

        View::composer(['components.layouts.espace', 'espace.*', 'rgpd.*'], EspaceComposer::class);

        Event::subscribe(JournaliserAuthentification::class);
        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);
    }
}
