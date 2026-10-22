<?php

use App\Domain\Agds\BovinAgds;
use App\Domain\Agds\IdentiteAgds;
use App\Services\Agds\LectureAgdsService;
use App\Services\Agds\SourceAgds;
use App\Services\Agds\SourceAgdsIndisponible;
use App\Services\Agds\VuesSagemaAgds;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Tests\Support\JeuCloisonnement;

// EF-M9-03, § 6.4 — données AGDS lues dans les vues de SAGEMA : identité de l'éleveur et troupeau bovin ;
// T-40 sur GET /elevages/{elevage}/agds (agents seulement, 404 hors périmètre), lecture bornée au département.
uses(RefreshDatabase::class);

beforeEach(function () {
    config(['agds.actif' => true]);
    $this->jeu = JeuCloisonnement::creer();
    DB::table('prive.elevage_identite')->where('elevage_id', $this->jeu->elevage24a->id)->update(['num_cheptel_ede' => 'FR24001007']);

    $this->source = new class implements SourceAgds
    {
        /** @var list<array{string, string}> */
        public array $appels = [];

        public bool $panne = false;

        public function identite(string $departement, string $numEde): ?IdentiteAgds
        {
            $this->appels[] = [$departement, $numEde];
            if ($this->panne) {
                throw new SourceAgdsIndisponible('connexion refusée');
            }

            return $departement === '24' && $numEde === '24001007'
                ? new IdentiteAgds('24001007', 'EARL DU PUY', 'EARL', ['Le Puy'], '24300', 'ABJAT SUR BANDIAT', 'EARL DU PUY', ['BP 3'], '24300', 'AUGIGNAC',
                    '0553000000', null, 'earl.puy@example.test', new DateTimeImmutable('2015-06-01'), null)
                : null;
        }

        public function bovinsPresents(string $departement, string $numEde): array
        {
            if ($departement !== '24' || $numEde !== '24001007') {
                return [];
            }

            return [
                new BovinAgds('FR2405817218', 250, 'F', '34', new DateTimeImmutable('2018-12-18'), new DateTimeImmutable('2018-12-18')),
                new BovinAgds('FR2405817219', 250, 'F', '34', new DateTimeImmutable('2026-03-01'), new DateTimeImmutable('2026-03-01')),
                new BovinAgds('BE864130400', 56, 'M', '46', new DateTimeImmutable('2025-08-01'), new DateTimeImmutable('2025-10-01')),
            ];
        }
    };
    app()->instance(SourceAgds::class, $this->source);
});

it('affiche à un technicien l\'identité de l\'éleveur et la composition du troupeau lues dans AGDS', function () {
    $this->actingAs($this->jeu->technicien24)
        ->get(route('dossier.agds', $this->jeu->elevage24a->id))
        ->assertOk()
        ->assertSee('Éleveur — cheptel FR24001007')
        ->assertSee('EARL DU PUY')
        ->assertSee('earl.puy@example.test')
        ->assertSee('3 bovin(s)')
        ->assertSee('BE864130400')
        ->assertSee('Source : AGDS, lu par SAGEMA');

    expect($this->source->appels)->toBe([['24', '24001007']]);
});

it('T-40 : refuse les données AGDS (403) à un compte éleveur ou vétérinaire d\'élevage', function () {
    $this->actingAs($this->jeu->eleveur24a)->get(route('dossier.agds', $this->jeu->elevage24a->id))->assertForbidden();
    $this->actingAs($this->jeu->veterinaire)->get(route('dossier.agds', $this->jeu->elevage24a->id))->assertForbidden();

    expect($this->source->appels)->toBe([]);
});

it('T-40 : ne montre pas un élevage hors du périmètre du technicien (404)', function () {
    $this->actingAs($this->jeu->technicien47)->get(route('dossier.agds', $this->jeu->elevage24a->id))->assertNotFound();

    expect($this->source->appels)->toBe([]);
});

it('borne la lecture AGDS au département de l\'élevage', function () {
    DB::table('prive.elevage_identite')->where('elevage_id', $this->jeu->elevage24a->id)->update(['num_cheptel_ede' => null]);
    DB::table('prive.elevage_identite')->where('elevage_id', $this->jeu->elevage47->id)->update(['num_cheptel_ede' => 'FR24001007']);

    $this->actingAs($this->jeu->technicien47)
        ->get(route('dossier.agds', $this->jeu->elevage47->id))
        ->assertOk()
        ->assertSee('Aucun cheptel FR24001007 n\'est connu d\'AGDS dans le département 47', false)
        ->assertDontSee('EARL DU PUY');

    expect($this->source->appels)->toBe([['47', '24001007']]);
});

it('n\'ajoute le volet AGDS dans l\'en-tête du dossier que pour les agents', function () {
    $this->actingAs($this->jeu->technicien24)->get(route('dossier.sanitaire', $this->jeu->elevage24a->id))->assertSee('Données AGDS');
    $this->actingAs($this->jeu->eleveur24a)->get(route('dossier.sanitaire', $this->jeu->elevage24a->id))->assertDontSee('Données AGDS');
});

it('signale un EDE absent, une lecture désactivée et une source indisponible (journalisée)', function () {
    $service = app(LectureAgdsService::class);
    DB::table('prive.elevage_identite')->where('elevage_id', $this->jeu->elevage24b->id)->update(['num_cheptel_ede' => null]);
    expect($service->pour($this->jeu->elevage24b)['etat'])->toBe(LectureAgdsService::SANS_EDE);

    config(['agds.actif' => false]);
    expect($service->pour($this->jeu->elevage24a)['etat'])->toBe(LectureAgdsService::INACTIF);

    config(['agds.actif' => true]);
    $this->source->panne = true;
    Log::spy();
    expect($service->pour($this->jeu->elevage24a)['etat'])->toBe(LectureAgdsService::INDISPONIBLE);
    Log::shouldHaveReceived('error')->once();
});

it('normalise le numéro EDE saisi (« FR » facultatif, 8 chiffres)', function () {
    expect(LectureAgdsService::numeroEde('FR24001007'))->toBe('24001007')
        ->and(LectureAgdsService::numeroEde('24 001 007'))->toBe('24001007')
        ->and(LectureAgdsService::numeroEde('FR2400100'))->toBeNull()
        ->and(LectureAgdsService::numeroEde(null))->toBeNull();
});

it('traduit une connexion SAGEMA injoignable en source indisponible', function () {
    config(['database.connections.sagema.host' => '127.0.0.1', 'database.connections.sagema.port' => '1']);

    expect(fn () => (new VuesSagemaAgds)->bovinsPresents('24', '24001007'))->toThrow(SourceAgdsIndisponible::class);
});
