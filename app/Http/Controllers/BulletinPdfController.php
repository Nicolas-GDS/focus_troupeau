<?php

namespace App\Http\Controllers;

use App\Models\App\ImportBulletin;
use App\Models\Fourrage\Analyse;
use App\Services\Import\ImportBulletinService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PDF des bulletins (EF-M1-02, ENF-SEC-05) : fichiers hors racine web, servis par une route signée à durée limitée,
 * après contrôle de la policy de l'objet. Les modèles sont résolus à travers les scopes de cloisonnement (404 sinon).
 */
class BulletinPdfController extends Controller
{
    public function analyse(Analyse $analyse): StreamedResponse
    {
        Gate::authorize('view', $analyse);

        return $this->servir((string) $analyse->fichier_pdf, $analyse->num_echantillon_labo.'.pdf');
    }

    public function import(ImportBulletin $import): StreamedResponse
    {
        Gate::authorize('view', $import);

        return $this->servir((string) $import->chemin, $import->nom_fichier);
    }

    private function servir(string $chemin, string $nom): StreamedResponse
    {
        abort_if($chemin === '' || ! Storage::disk(ImportBulletinService::DISQUE)->exists($chemin), 404);

        return Storage::disk(ImportBulletinService::DISQUE)->response($chemin, $nom, [
            'Content-Type' => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ], 'inline');
    }
}
