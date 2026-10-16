<?php

namespace App\Livewire\Compte;

use App\Models\App\User;
use App\Services\Notifications\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use RuntimeException;

/**
 * E17 — préférences de notification du compte (EF-M8-05) : désinscription des e-mails et des SMS, numéro de mobile ;
 * les alertes de niveau 3 restent toujours envoyées. Le compte ne modifie que ses propres préférences.
 */
class PreferencesNotification extends Component
{
    public bool $email = true;

    public bool $sms = true;

    public string $telephone = '';

    public function mount(): void
    {
        $user = $this->utilisateur();
        $this->email = $user->pref_email;
        $this->sms = $user->pref_sms;
        $this->telephone = (string) $user->telephone;
    }

    public function enregistrer(): void
    {
        $this->validate([
            'email' => ['boolean'],
            'sms' => ['boolean'],
            'telephone' => ['nullable', 'string', 'max:20', function (string $attribut, mixed $valeur, \Closure $echec): void {
                if (is_string($valeur) && trim($valeur) !== '' && NotificationService::numeroInternational($valeur) === null) {
                    $echec('Saisissez un numéro de mobile valide (06 12 34 56 78).');
                }
            }],
        ], [], ['telephone' => 'numéro de mobile']);

        $this->utilisateur()->update([
            'pref_email' => $this->email,
            'pref_sms' => $this->sms,
            'telephone' => trim($this->telephone) === '' ? null : trim($this->telephone),
        ]);

        session()->flash('preferences', 'Préférences enregistrées.');
    }

    public function render(): View
    {
        return view('livewire.compte.preferences-notification');
    }

    private function utilisateur(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('Utilisateur non connecté.');
        }

        return $user;
    }
}
