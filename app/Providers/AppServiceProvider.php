<?php

namespace App\Providers;

use App\Listeners\JournaliserAuthentification;
use Illuminate\Support\Facades\Event;
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
        // ENF-SEC-02 : 12 caractères minimum, vérification contre les mots de passe compromis
        // (Have I Been Pwned, k-anonymat : seuls les 5 premiers caractères de l'empreinte SHA-1 sont transmis).
        Password::defaults(fn () => Password::min(12)->uncompromised());

        Event::subscribe(JournaliserAuthentification::class);
        Event::listen(SocialiteWasCalled::class, [MicrosoftExtendSocialite::class, 'handle']);
    }
}
