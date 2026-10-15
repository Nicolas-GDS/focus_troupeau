<?php

namespace App\Services;

use App\Models\App\Journal;
use Illuminate\Http\Request;

/**
 * Écriture du journal d'audit app.journal (ENF-SEC-08) : connexions, publications, visas,
 * révocations de consentement, modifications de référentiels, exports, changements de rôle.
 */
class JournalService
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $detail
     */
    public function ecrire(string $action, ?int $userId = null, ?string $objet = null, array $detail = [], ?string $elevageId = null): Journal
    {
        return Journal::query()->create([
            'user_id' => $userId,
            'elevage_id' => $elevageId,
            'action' => $action,
            'objet' => $objet,
            'detail' => $detail === [] ? null : $detail,
            'ip' => $this->request->ip(),
        ]);
    }
}
