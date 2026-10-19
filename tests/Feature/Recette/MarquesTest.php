<?php

use App\Domain\Autonomie\Levier;
use App\Jobs\EnvoyerNotification;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Fourrage\Elevage;
use App\Models\Stock\Inventaire;
use App\Services\Autonomie\ApprovisionnementService;
use App\Services\Autonomie\PdfAutonomie;
use App\Services\Pdf\RenduPdf;
use Database\Seeders\JeuJ1Seeder;
use Database\Seeders\JeuJ2Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

// T-25 (EF-GEN-04, EF-M7-06) : aucun nom de produit ni de marque dans les libellés générés — référentiels (toutes
// les colonnes textuelles du schéma ref), paramètres par défaut, propositions et leviers, PDF du bilan, du scénario et
// de la fiche de visite. Liste de marques courantes : tests/Fixtures/marques.txt.
uses(RefreshDatabase::class);

/** @return list<string> */
function marques(): array
{
    return array_values(array_filter(array_map('trim', file(base_path('tests/Fixtures/marques.txt')) ?: []), fn ($l) => $l !== '' && ! str_starts_with($l, '#')));
}

/** @return list<string> marques trouvées dans le texte (mot entier, sans casse ni accents) */
function marquesTrouvees(string $texte): array
{
    $sans = fn (string $s) => mb_strtolower((string) iconv('UTF-8', 'ASCII//TRANSLIT', $s));
    $texte = $sans(strip_tags($texte));

    return array_values(array_filter(marques(), fn (string $m) => preg_match('/\b'.preg_quote($sans($m), '/').'\b/u', $texte) === 1));
}

beforeEach(function () {
    Queue::fake([EnvoyerNotification::class]);
    Mail::fake();
    Storage::fake('local');
    app()->instance(RenduPdf::class, new class implements RenduPdf
    {
        public function pdf(string $html, string $piedDePage): string
        {
            return '%PDF-1.7';
        }
    });
});

it('trouve une marque dans un texte qui en contient (contrôle du détecteur)', function () {
    expect(marquesTrouvees('Distribuer le seau Néolait aux brebis'))->toBe(['Néolait'])
        ->and(marquesTrouvees('Seau à lécher, correcteur azoté'))->toBe([]);
});

/**
 * Citations de source, et non libellés générés : la bibliographie nomme ses éditeurs ; le repère quantitatif de la
 * compatibilité Zn × parakératose cite « INRA 2018 via Virbac » (référentiel du chapitre 11, champ non affiché).
 */
const CITATIONS = ['reference_biblio.*', 'compatibilite_parametre_trouble.repere_quantitatif'];

it('T-25 : aucun nom de marque dans les référentiels ni dans les paramètres par défaut', function () {
    $colonnes = DB::table('information_schema.columns')->where('table_schema', 'ref')->whereIn('data_type', ['text', 'character varying'])
        ->get(['table_name', 'column_name'])
        ->reject(fn ($c) => in_array($c->table_name.'.*', CITATIONS, true) || in_array($c->table_name.'.'.$c->column_name, CITATIONS, true));
    $trouvees = [];
    foreach ($colonnes as $c) {
        foreach (DB::table('ref.'.$c->table_name)->whereNotNull($c->column_name)->pluck($c->column_name) as $valeur) {
            foreach (marquesTrouvees((string) $valeur) as $m) {
                $trouvees[] = "ref.{$c->table_name}.{$c->column_name} : {$m}";
            }
        }
    }
    foreach (DB::table('app.parametre_defaut')->get(['cle', 'libelle', 'source']) as $p) {
        foreach (marquesTrouvees($p->libelle.' '.$p->source) as $m) {
            $trouvees[] = "app.parametre_defaut {$p->cle} : {$m}";
        }
    }

    expect($trouvees)->toBe([]);
});

it('T-25 : aucun nom de marque dans les propositions, les leviers et les PDF générés', function () {
    (new JeuJ1Seeder)->run();
    (new JeuJ2Seeder)->run();
    $j1 = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ1Seeder::CODE)->sole();
    $inventaire = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $j1->id)->where('statut', 'courant')->sole();
    $appro = app(ApprovisionnementService::class);
    $scenario = $appro->creerScenario($inventaire, 'Proposition', true);
    foreach ([Levier::CONCENTRE, Levier::DECAPITALISATION, Levier::GENISSES, Levier::PATURAGE] as $code) {
        $appro->enregistrerLigne($scenario, ['levier_code' => $code, 'quantite' => 5, 'famille_code' => 'FOIN', 'parametre' => 100]);
    }
    $pdf = app(PdfAutonomie::class);
    $j2 = Elevage::query()->withoutGlobalScopes()->where('code_pseudo', JeuJ2Seeder::CODE)->sole();
    $fiche = InterpretationVisite::query()->withoutGlobalScopes()->whereIn('visite_id', DB::table('dossier.visite')->where('elevage_id', $j2->id)->select('id'))->sole();

    $textes = [
        'proposition' => json_encode($appro->proposition($inventaire), JSON_UNESCAPED_UNICODE),
        'leviers' => $appro->lignes($scenario)->pluck('commentaire')->implode(' ').' '.implode(' ', ApprovisionnementService::LIBELLES),
        'vigilance' => implode(' ', $appro->evaluer($scenario)['vigilance']),
        'pdf bilan' => $pdf->bilanHtml($inventaire),
        'pdf scénario' => $pdf->scenarioHtml($scenario->refresh()),
        'fiche J2' => (string) Storage::disk('local')->get((string) $fiche->fiche_html),
        'messages' => DB::table('dossier.message')->pluck('texte')->implode(' '),
        'notifications' => DB::table('app.notification')->get(['objet', 'corps'])->map(fn ($n) => $n->objet.' '.$n->corps)->implode(' '),
    ];
    $trouvees = [];
    foreach ($textes as $origine => $texte) {
        foreach (marquesTrouvees((string) $texte) as $m) {
            $trouvees[] = "{$origine} : {$m}";
        }
    }

    expect($trouvees)->toBe([])->and(strlen($textes['fiche J2']))->toBeGreaterThan(1000);
});
