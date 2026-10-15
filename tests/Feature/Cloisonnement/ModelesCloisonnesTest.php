<?php

use App\Models\App\Gds;
use App\Models\App\Journal;
use App\Models\App\Notification;
use App\Models\App\ParametreDefaut;
use App\Models\Fourrage\Elevage;
use App\Scopes\ElevageScope;
use App\Scopes\GdsScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Chapitre 5.1 et 6.3 : « toutes les tables portant elevage_id sont soumises au cloisonnement ».
// Garde-fou : tout modèle Eloquent dont la table porte elevage_id ou gds_id doit déclarer le scope
// correspondant, sauf exception justifiée ci-dessous. Un nouveau modèle oublié fait échouer ce test.
uses(RefreshDatabase::class);

/** @var array<class-string, string> exceptions justifiées */
const MODELES_HORS_CLOISONNEMENT = [
    Gds::class => 'référentiel des GDS (id, pas de gds_id de rattachement)',
    ParametreDefaut::class => 'référentiel : gds_id NULL = valeur de l\'instance, lu par tous les rôles (D10)',
    Journal::class => 'journal d\'audit : lecture réservée à l\'administrateur par permission (journal.lire)',
    Notification::class => 'notifications : propres à leur destinataire (user_id), accès par policy au lot L3',
];

/**
 * @return list<class-string<Model>>
 */
function modelesEloquent(): array
{
    return collect(File::allFiles(app_path('Models')))
        ->map(fn ($fichier) => 'App\\Models\\'.Str::of($fichier->getRelativePathname())->replace(['/', '.php'], ['\\', ''])->toString())
        ->filter(fn (string $classe) => class_exists($classe) && is_subclass_of($classe, Model::class))
        ->values()
        ->all();
}

it('cloisonne tout modèle dont la table porte elevage_id ou gds_id', function () {
    $manquants = [];

    foreach (modelesEloquent() as $classe) {
        if (array_key_exists($classe, MODELES_HORS_CLOISONNEMENT)) {
            continue;
        }

        $modele = new $classe;
        $colonnes = Schema::getColumnListing($modele->getTable());
        $scopes = array_keys($modele->getGlobalScopes());

        if (in_array('elevage_id', $colonnes, true) && ! in_array(ElevageScope::class, $scopes, true)) {
            $manquants[] = "{$classe} : ElevageScope";
        }

        if (in_array('gds_id', $colonnes, true) && ! in_array(GdsScope::class, $scopes, true)) {
            $manquants[] = "{$classe} : GdsScope";
        }
    }

    expect($manquants)->toBe([]);
});

it('cloisonne l\'élevage lui-même par GDS et par élevage', function () {
    $scopes = array_keys((new Elevage)->getGlobalScopes());

    expect($scopes)->toContain(GdsScope::class, ElevageScope::class);
});

it('ne garde que des exceptions qui correspondent à des modèles existants', function () {
    foreach (array_keys(MODELES_HORS_CLOISONNEMENT) as $classe) {
        expect(class_exists($classe))->toBeTrue();
    }
});
