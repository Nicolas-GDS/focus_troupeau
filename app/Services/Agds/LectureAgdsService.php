<?php

namespace App\Services\Agds;

use App\Domain\Agds\BovinAgds;
use App\Domain\Agds\CompositionTroupeau;
use App\Domain\Agds\IdentiteAgds;
use App\Models\Fourrage\Elevage;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Données AGDS d'un élevage (EF-M9-03, § 6.4) : identité de l'éleveur et composition du troupeau bovin, lues par
 * le numéro EDE de prive.elevage_identite dans le département de l'élevage. L'élevage arrive déjà cloisonné (scopes
 * et policy) ; le département borne en plus la lecture distante. Aucune écriture, ni locale ni vers SAGEMA.
 */
class LectureAgdsService
{
    public const INACTIF = 'inactif';

    public const SANS_EDE = 'sans_ede';

    public const INTROUVABLE = 'introuvable';

    public const INDISPONIBLE = 'indisponible';

    public const DISPONIBLE = 'disponible';

    public function __construct(private readonly SourceAgds $source) {}

    /**
     * @return array{etat: string, ede: string|null, identite: IdentiteAgds|null, composition: array<string, mixed>|null, bovins: list<BovinAgds>, date: DateTimeImmutable}
     */
    public function pour(Elevage $elevage, ?DateTimeImmutable $date = null): array
    {
        $date ??= new DateTimeImmutable('today');
        $resultat = ['etat' => self::INACTIF, 'ede' => null, 'identite' => null, 'composition' => null, 'bovins' => [], 'date' => $date];

        if (! config('agds.actif')) {
            return $resultat;
        }

        $ede = self::numeroEde(DB::table('prive.elevage_identite')->where('elevage_id', $elevage->id)->value('num_cheptel_ede'));
        if ($ede === null) {
            return [...$resultat, 'etat' => self::SANS_EDE];
        }
        $resultat['ede'] = $ede;

        try {
            $identite = $this->source->identite($elevage->departement, $ede);
            $bovins = $this->source->bovinsPresents($elevage->departement, $ede);
        } catch (SourceAgdsIndisponible $e) {
            Log::error('Lecture AGDS indisponible', ['elevage' => $elevage->id, 'departement' => $elevage->departement, 'erreur' => $e->getMessage()]);

            return [...$resultat, 'etat' => self::INDISPONIBLE];
        }

        if ($identite === null && $bovins === []) {
            return [...$resultat, 'etat' => self::INTROUVABLE];
        }

        $composition = (new CompositionTroupeau(array_map('intval', (array) config('agds.tranches_age_mois'))))->calculer($bovins, $date);

        return [...$resultat, 'etat' => self::DISPONIBLE, 'identite' => $identite, 'composition' => $composition, 'bovins' => $bovins];
    }

    /**
     * Numéro EDE de cheptel sur 8 chiffres (« FR24001007 » ou « 24001007 »), null s'il est absent ou mal formé.
     */
    public static function numeroEde(?string $saisie): ?string
    {
        $chiffres = preg_replace('/\D/', '', (string) $saisie);

        return strlen((string) $chiffres) === 8 ? $chiffres : null;
    }
}
