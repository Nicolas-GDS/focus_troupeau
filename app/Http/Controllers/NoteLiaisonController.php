<?php

namespace App\Http\Controllers;

use App\Models\Dossier\NoteLiaison;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF archivé d'une note de liaison (EF-M2-09) : lien signé et policy de lecture (ENF-SEC-05).
 */
class NoteLiaisonController extends Controller
{
    public function pdf(NoteLiaison $note): StreamedResponse
    {
        Gate::authorize('view', $note);
        abort_if($note->pdf === null, 404);

        return Storage::disk((string) config('fiches.disque'))->download($note->pdf, 'note-liaison-'.$note->id.'.pdf', ['Content-Type' => 'application/pdf']);
    }
}
