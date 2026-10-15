<?php

namespace App\Services\Authentification;

use App\Models\App\User;
use App\Notifications\Authentification\CodeConnexionNotification;
use Illuminate\Support\Facades\DB;

/**
 * Code de double authentification envoyé par e-mail (EF-M0-03, ENF-SEC-03).
 * Un seul code valide à la fois par compte ; le code n'est conservé que sous forme d'empreinte HMAC.
 */
class CodeVerificationService
{
    public function envoyer(User $user): void
    {
        DB::table('app.code_verification')
            ->where('user_id', $user->id)
            ->whereNull('utilise_le')
            ->update(['expire_le' => now()]);

        $code = str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
        $minutes = (int) config('authentification.code_minutes');

        DB::table('app.code_verification')->insert([
            'user_id' => $user->id,
            'code_hash' => $this->empreinte($code),
            'expire_le' => now()->addMinutes($minutes),
        ]);

        $user->notify(new CodeConnexionNotification($code, $minutes));
    }

    /**
     * Vérifie le code saisi ; un code juste n'est utilisable qu'une fois, un code faux consomme un essai.
     */
    public function verifier(User $user, string $saisie): bool
    {
        return DB::transaction(function () use ($user, $saisie): bool {
            $code = DB::table('app.code_verification')
                ->where('user_id', $user->id)
                ->whereNull('utilise_le')
                ->where('expire_le', '>', now())
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            if ($code === null || $code->tentatives >= (int) config('authentification.code_tentatives')) {
                return false;
            }

            if (! hash_equals($code->code_hash, $this->empreinte(trim($saisie)))) {
                DB::table('app.code_verification')->where('id', $code->id)->increment('tentatives');

                return false;
            }

            DB::table('app.code_verification')->where('id', $code->id)->update(['utilise_le' => now()]);

            return true;
        });
    }

    private function empreinte(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
    }
}
