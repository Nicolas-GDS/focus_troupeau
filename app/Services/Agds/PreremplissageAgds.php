<?php

namespace App\Services\Agds;

use App\Models\App\User;
use App\Services\Administration\ElevagesService;
use App\Services\Dossier\SaisieRefusee;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pré-remplissage du formulaire de création d'un élevage depuis AGDS (EF-M0-02, EF-M9-03, § 6.4) : à partir du numéro
 * EDE et du GDS choisi, l'identité lue dans les vues de SAGEMA propose la raison sociale, le code postal, le téléphone
 * et l'e-mail. Le cheptel est cherché dans le département du GDS ; rien n'est écrit, le technicien relit et crée.
 */
class PreremplissageAgds
{
    public function __construct(
        private readonly SourceAgds $source,
        private readonly ElevagesService $elevages,
    ) {}

    /**
     * @return array{champs: array<string, string>, message: string}
     *
     * @throws SaisieRefusee
     */
    public function proposer(string $saisieEde, int $gdsId, User $auteur): array
    {
        if (! config('agds.actif')) {
            throw new SaisieRefusee('La lecture des données AGDS n\'est pas configurée sur cette instance.');
        }
        if (! in_array($gdsId, $this->elevages->gdsPossibles($auteur), true)) {
            throw new SaisieRefusee('GDS hors de votre périmètre.');
        }
        $ede = LectureAgdsService::numeroEde($saisieEde);
        if ($ede === null) {
            throw new SaisieRefusee('Numéro EDE attendu : FR suivi de 8 chiffres.');
        }
        if (DB::table('prive.elevage_identite')->where('num_cheptel_ede', 'FR'.$ede)->exists()) {
            throw new SaisieRefusee('Ce numéro EDE est déjà celui d\'un élevage suivi.');
        }

        $departement = ElevagesService::departementDuGds($gdsId);
        try {
            $identite = $this->source->identite($departement, $ede);
        } catch (SourceAgdsIndisponible $e) {
            Log::error('Pré-remplissage AGDS indisponible', ['ede' => $ede, 'departement' => $departement, 'erreur' => $e->getMessage()]);

            throw new SaisieRefusee('Les données AGDS sont momentanément indisponibles (SAGEMA ne répond pas). Saisissez l\'identité à la main ou réessayez plus tard.');
        }
        if ($identite === null) {
            throw new SaisieRefusee("Aucun cheptel FR{$ede} n'est connu d'AGDS dans le département {$departement}.");
        }

        $champs = array_filter([
            'num_cheptel_ede' => 'FR'.$ede,
            'raison_sociale' => trim((string) $identite->nom),
            'code_postal' => trim((string) $identite->codePostal),
            'telephone' => trim((string) ($identite->telephone ?: $identite->mobile)),
            'courriel' => trim((string) $identite->courriel),
        ], fn (string $valeur) => $valeur !== '');

        $message = 'Identité pré-remplie depuis AGDS : vérifiez-la avant de créer l\'élevage.';
        if ($identite->cesse(new DateTimeImmutable('today'))) {
            $message .= ' Attention : activité cessée le '.$identite->dateCessation?->format('d/m/Y').' selon AGDS.';
        }

        return ['champs' => $champs, 'message' => $message];
    }
}
