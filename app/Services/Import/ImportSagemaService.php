<?php

namespace App\Services\Import;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Administration\ElevagesService;
use App\Services\Cloisonnement\Perimetre;
use App\Services\Dossier\SaisieRefusee;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;

/**
 * Import SAGEMA (EF-M9-03, T-27, § 6.4) : fichier des élevages adhérents et fichier des indicateurs du bilan sanitaire,
 * CSV séparés par « ; » (UTF-8 ou Windows-1252). Rapprochement par numéro EDE dans le périmètre de l'importateur ;
 * un élevage absent est créé (code ELV-nnnn, GDS de l'importateur) ; une valeur vide ne remplace pas une valeur
 * existante ; une saisie manuelle du bilan sanitaire n'est pas écrasée. Chaque ligne reçoit une action (créé, mis à
 * jour, inchangé, rejeté avec motif) : l'aperçu calcule le rapport sans rien écrire, l'import l'applique et le
 * journalise. Aucune écriture vers SAGEMA.
 */
class ImportSagemaService
{
    public const COLONNES_ELEVAGES = ['num_ede', 'siret', 'raison_sociale', 'contact', 'adresse', 'cp', 'commune', 'telephone', 'courriel', 'especes',
        'effectif_bovins', 'effectif_ovins', 'effectif_caprins', 'adherent'];

    public const COLONNES_INDICATEURS = ['num_ede', 'campagne', 'indicateur', 'nb_cas', 'denominateur', 'valeur'];

    public const CREE = 'cree';

    public const MIS_A_JOUR = 'mis_a_jour';

    public const INCHANGE = 'inchange';

    public const REJETE = 'rejete';

    /** Correspondance colonne SAGEMA → champ d'identité (prive.elevage_identite). */
    private const IDENTITE = ['siret' => 'siret', 'raison_sociale' => 'raison_sociale', 'contact' => 'nom_contact', 'adresse' => 'adresse',
        'cp' => 'code_postal', 'telephone' => 'telephone', 'courriel' => 'courriel'];

    private const ESPECES = ['BOV' => 'BOV', 'BOVIN' => 'BOV', 'BOVINS' => 'BOV', 'OVI' => 'OVI', 'OVIN' => 'OVI', 'OVINS' => 'OVI', 'CAP' => 'CAP', 'CAPRIN' => 'CAP', 'CAPRINS' => 'CAP'];

    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly ElevagesService $elevages,
        private readonly JournalService $journal,
    ) {}

    /**
     * @return array{lignes: list<array{numero: int, ede: string, action: string, motif: string|null, elevage: string|null, changements: list<string>}>, totaux: array<string, int>}
     */
    public function elevages(string $contenu, int $gdsId, User $auteur, bool $appliquer): array
    {
        if (! in_array($gdsId, $this->elevages->gdsPossibles($auteur), true)) {
            throw new SaisieRefusee('GDS hors de votre périmètre.');
        }
        $lignes = [];
        $vus = [];
        $traiter = function () use ($contenu, $gdsId, $auteur, $appliquer, &$lignes, &$vus) {
            foreach ($this->lire($contenu, self::COLONNES_ELEVAGES) as $numero => $l) {
                $ede = $this->ede($l['num_ede']);
                $resultat = match (true) {
                    $ede === null => [self::REJETE, 'Numéro EDE absent ou invalide (FR suivi de 8 chiffres).', null, []],
                    isset($vus[$ede]) => [self::REJETE, 'Numéro EDE en double dans le fichier (ligne '.$vus[$ede].').', null, []],
                    default => $this->ligneElevage($l, $ede, $gdsId, $auteur, $appliquer),
                };
                if ($ede !== null) {
                    $vus[$ede] ??= $numero;
                }
                $lignes[] = ['numero' => $numero, 'ede' => $ede ?? $l['num_ede'], 'action' => $resultat[0], 'motif' => $resultat[1], 'elevage' => $resultat[2], 'changements' => $resultat[3]];
            }
        };
        $appliquer ? DB::transaction($traiter) : $traiter();

        return $this->rapport($lignes, 'elevages', $auteur, $appliquer);
    }

    /**
     * @return array{lignes: list<array{numero: int, ede: string, action: string, motif: string|null, elevage: string|null, changements: list<string>}>, totaux: array<string, int>}
     */
    public function indicateurs(string $contenu, User $auteur, bool $appliquer): array
    {
        $referentiel = DB::table('ref.indicateur_sanitaire')->pluck('mode', 'code');
        $campagnes = DB::table('fourrage.campagne')->pluck('code')->all();
        $lignes = [];
        $traiter = function () use ($contenu, $auteur, $appliquer, $referentiel, $campagnes, &$lignes) {
            foreach ($this->lire($contenu, self::COLONNES_INDICATEURS) as $numero => $l) {
                $ede = $this->ede($l['num_ede']);
                $elevage = $ede === null ? null : $this->elevageParEde($ede, $auteur);
                $code = strtoupper(trim($l['indicateur']));
                $mode = $referentiel[$code] ?? null;
                [$nb, $den, $valeur] = [$this->entier($l['nb_cas']), $this->entier($l['denominateur']), $this->decimal($l['valeur'])];
                if ($mode === 'taux' && $nb !== null && $den !== null && $den > 0) {
                    $valeur = round(100 * $nb / $den, 2);
                }
                $resultat = match (true) {
                    $ede === null => [self::REJETE, 'Numéro EDE absent ou invalide.'],
                    $elevage === false => [self::REJETE, 'Élevage hors de votre périmètre.'],
                    $elevage === null => [self::REJETE, 'Élevage inconnu : importer d\'abord le fichier des élevages.'],
                    ! in_array(trim($l['campagne']), $campagnes, true) => [self::REJETE, 'Campagne inconnue : '.trim($l['campagne']).'.'],
                    $mode === null => [self::REJETE, 'Indicateur inconnu du référentiel : '.$code.'.'],
                    $mode === 'taux' && $nb !== null && $den !== null && $nb > $den => [self::REJETE, 'Nombre de cas supérieur au dénominateur.'],
                    $valeur === null => [self::REJETE, 'Valeur absente.'],
                    default => $this->ecrireIndicateur($elevage, trim($l['campagne']), $code, $mode, $nb, $den, $valeur, $appliquer),
                };
                $lignes[] = ['numero' => $numero, 'ede' => $ede ?? $l['num_ede'], 'action' => $resultat[0], 'motif' => $resultat[1],
                    'elevage' => $elevage instanceof Elevage ? $elevage->code_pseudo : null, 'changements' => [$code.' '.trim($l['campagne'])]];
            }
        };
        $appliquer ? DB::transaction($traiter) : $traiter();

        return $this->rapport($lignes, 'indicateurs', $auteur, $appliquer);
    }

    /**
     * @param  array<string, string>  $l
     * @return array{0: string, 1: string|null, 2: string|null, 3: list<string>}
     */
    private function ligneElevage(array $l, string $ede, int $gdsId, User $auteur, bool $appliquer): array
    {
        $existant = $this->elevageParEde($ede, $auteur);
        if ($existant === false) {
            return [self::REJETE, 'Numéro EDE déjà connu hors de votre périmètre.', null, []];
        }
        if ($l['courriel'] !== '' && filter_var($l['courriel'], FILTER_VALIDATE_EMAIL) === false) {
            return [self::REJETE, 'Courriel invalide.', null, []];
        }
        if ($l['cp'] !== '' && preg_match('/^\d{5}$/', $l['cp']) !== 1) {
            return [self::REJETE, 'Code postal invalide.', null, []];
        }
        foreach (['effectif_bovins', 'effectif_ovins', 'effectif_caprins'] as $c) {
            if ($l[$c] !== '' && $this->entier($l[$c]) === null) {
                return [self::REJETE, 'Effectif non entier : '.$c.'.', null, []];
            }
        }

        $nouveau = $this->valeursElevage($l);
        if ($existant === null) {
            if ($l['raison_sociale'] === '') {
                return [self::REJETE, 'Raison sociale absente pour un nouvel élevage.', null, []];
            }
            $code = null;
            if ($appliquer) {
                $elevage = $this->elevages->creer([...$nouveau['identite'], ...$nouveau['elevage'], 'num_cheptel_ede' => $ede, 'gds_id' => $gdsId,
                    'especes' => $nouveau['especes'] ?? [], 'consentement_donnees' => false], $auteur);
                $code = $elevage->code_pseudo;
            }

            return [self::CREE, null, $code, []];
        }

        $identite = (array) (DB::table('prive.elevage_identite')->where('elevage_id', $existant->id)->first() ?? []);
        $changements = [];
        $majIdentite = [];
        foreach ($nouveau['identite'] as $champ => $valeur) {
            if ($valeur !== null && (string) ($identite[$champ] ?? '') !== (string) $valeur) {
                $majIdentite[$champ] = $valeur;
                $changements[] = $champ;
            }
        }
        $majElevage = [];
        foreach ($nouveau['elevage'] as $champ => $valeur) {
            if ($valeur !== null && $existant->getAttribute($champ) !== $valeur) {
                $majElevage[$champ] = $valeur;
                $changements[] = $champ;
            }
        }
        if (($nouveau['especes'] ?? null) !== null && $nouveau['especes'] !== $existant->listeEspeces()) {
            $majElevage['especes'] = '{'.implode(',', $nouveau['especes']).'}';
            $changements[] = 'especes';
        }
        if ($changements === []) {
            return [self::INCHANGE, null, $existant->code_pseudo, []];
        }
        if ($appliquer) {
            if ($majElevage !== []) {
                $existant->update($majElevage);
            }
            if ($majIdentite !== []) {
                DB::table('prive.elevage_identite')->where('elevage_id', $existant->id)->update([...$majIdentite, 'maj_le' => now()]);
            }
        }

        return [self::MIS_A_JOUR, null, $existant->code_pseudo, $changements];
    }

    /**
     * @param  array<string, string>  $l
     * @return array{identite: array<string, string|null>, elevage: array<string, mixed>, especes: list<string>|null}
     */
    private function valeursElevage(array $l): array
    {
        $identite = [];
        foreach (self::IDENTITE as $colonne => $champ) {
            $identite[$champ] = $l[$colonne] === '' ? null : $l[$colonne];
        }
        $especes = null;
        if ($l['especes'] !== '') {
            $especes = array_values(array_unique(array_filter(array_map(fn ($e) => self::ESPECES[strtoupper(trim($e))] ?? null, preg_split('/[,|\/ ]+/', $l['especes']) ?: []))));
            sort($especes);
        }
        $insee = null;
        if ($l['commune'] !== '') {
            $insee = DB::table('sig.commune')->whereRaw('unaccent(lower(nom)) = unaccent(lower(?))', [$l['commune']])
                ->when($l['cp'] !== '', fn ($q) => $q->where('departement', substr($l['cp'], 0, 2)))->value('code_insee');
        }

        return [
            'identite' => $identite,
            'elevage' => array_filter([
                'effectif_bovins' => $this->entier($l['effectif_bovins']), 'effectif_ovins' => $this->entier($l['effectif_ovins']),
                'effectif_caprins' => $this->entier($l['effectif_caprins']), 'adherent_gds' => $this->booleen($l['adherent']),
                'code_insee_commune' => $insee === null ? null : (string) $insee,
            ], fn ($v) => $v !== null),
            'especes' => $especes,
        ];
    }

    /** @return array{0: string, 1: string|null} */
    private function ecrireIndicateur(Elevage $elevage, string $campagne, string $code, string $mode, ?int $nb, ?int $den, float $valeur, bool $appliquer): array
    {
        $existant = DB::table('dossier.indicateur_sanitaire')->where('elevage_id', $elevage->id)->where('campagne_code', $campagne)->where('indicateur_code', $code)->first();
        if ($existant !== null && $existant->source === 'saisie') {
            return [self::INCHANGE, 'Valeur saisie par le technicien conservée.'];
        }
        $valeurs = ['valeur' => $valeur, 'nb_cas' => $mode === 'taux' ? $nb : null, 'denominateur' => $mode === 'taux' ? $den : null];
        if ($existant !== null && (float) $existant->valeur === $valeur && $existant->nb_cas === $valeurs['nb_cas'] && $existant->denominateur === $valeurs['denominateur']) {
            return [self::INCHANGE, null];
        }
        if ($appliquer) {
            DB::table('dossier.indicateur_sanitaire')->updateOrInsert(['elevage_id' => $elevage->id, 'campagne_code' => $campagne, 'indicateur_code' => $code],
                [...$valeurs, 'source' => 'sagema', 'importe_le' => now(), 'maj_le' => now()]);
        }

        return [$existant === null ? self::CREE : self::MIS_A_JOUR, null];
    }

    /** Élevage du périmètre portant cet EDE ; false s'il existe hors périmètre ; null s'il est inconnu. */
    private function elevageParEde(string $ede, User $auteur): Elevage|false|null
    {
        $id = DB::table('prive.elevage_identite')->where('num_cheptel_ede', $ede)->value('elevage_id');
        if ($id === null) {
            return null;
        }

        return $this->perimetre->couvreElevage($auteur, (string) $id) ? Elevage::query()->withoutGlobalScopes()->find($id) : false;
    }

    /**
     * Lignes du CSV indexées par numéro de ligne du fichier, colonnes nommées par l'en-tête.
     *
     * @param  list<string>  $colonnes
     * @return array<int, array<string, string>>
     */
    public function lire(string $contenu, array $colonnes): array
    {
        $contenu = preg_replace('/^\xEF\xBB\xBF/', '', $contenu) ?? $contenu;
        if (! mb_check_encoding($contenu, 'UTF-8')) {
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }
        $lignes = preg_split('/\r\n|\r|\n/', trim($contenu)) ?: [];
        if ($lignes === [] || trim($lignes[0]) === '') {
            throw new SaisieRefusee('Fichier vide.');
        }
        $entete = array_map(fn ($c) => strtolower(trim($c)), str_getcsv(array_shift($lignes), ';', '"', ''));
        $manquantes = array_diff($colonnes, $entete);
        if ($manquantes !== []) {
            throw new SaisieRefusee('Colonnes absentes de l\'en-tête : '.implode(', ', $manquantes).'. Attendu : '.implode(';', $colonnes).'.');
        }
        $resultat = [];
        foreach ($lignes as $i => $brute) {
            if (trim($brute) === '') {
                continue;
            }
            $valeurs = str_getcsv($brute, ';', '"', '');
            $ligne = [];
            foreach ($colonnes as $c) {
                $ligne[$c] = trim((string) ($valeurs[array_search($c, $entete, true)] ?? ''));
            }
            $resultat[$i + 2] = $ligne;
        }

        return $resultat;
    }

    /**
     * @param  list<array{numero: int, ede: string, action: string, motif: string|null, elevage: string|null, changements: list<string>}>  $lignes
     * @return array{lignes: list<array{numero: int, ede: string, action: string, motif: string|null, elevage: string|null, changements: list<string>}>, totaux: array<string, int>}
     */
    private function rapport(array $lignes, string $fichier, User $auteur, bool $appliquer): array
    {
        $totaux = array_fill_keys([self::CREE, self::MIS_A_JOUR, self::INCHANGE, self::REJETE], 0);
        foreach ($lignes as $l) {
            $totaux[$l['action']]++;
        }
        if ($appliquer) {
            $this->journal->ecrire('import_sagema', $auteur->id, 'sagema:'.$fichier, $totaux);
        }

        return ['lignes' => $lignes, 'totaux' => $totaux];
    }

    private function ede(string $brut): ?string
    {
        $ede = strtoupper(str_replace([' ', '.'], '', $brut));

        return preg_match('/^FR\d{8}$/', $ede) === 1 ? $ede : null;
    }

    private function entier(string $brut): ?int
    {
        $brut = str_replace(' ', '', $brut);

        return preg_match('/^\d+$/', $brut) === 1 ? (int) $brut : null;
    }

    private function decimal(string $brut): ?float
    {
        $brut = str_replace([' ', ','], ['', '.'], $brut);

        return is_numeric($brut) ? (float) $brut : null;
    }

    private function booleen(string $brut): ?bool
    {
        return match (mb_strtolower(trim($brut))) {
            'oui', 'o', '1', 'true', 'vrai', 'x' => true,
            'non', 'n', '0', 'false', 'faux' => false,
            default => null,
        };
    }
}
