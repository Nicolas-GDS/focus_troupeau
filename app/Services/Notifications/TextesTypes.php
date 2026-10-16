<?php

namespace App\Services\Notifications;

use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Textes types en vigueur (ref.texte_type, EF-M10-03, § 8.5), avec remplacement des variables « {nom} ».
 */
class TextesTypes
{
    /**
     * @param  array<string, string>  $variables
     */
    public function texte(string $code, array $variables = []): string
    {
        $texte = DB::table('ref.texte_type')->where('code', $code)->whereNull('version_fin')->value('texte');

        if ($texte === null) {
            throw new RuntimeException("Texte type introuvable : {$code}.");
        }

        return strtr((string) $texte, array_combine(array_map(fn ($cle) => '{'.$cle.'}', array_keys($variables)), array_values($variables)) ?: []);
    }
}
