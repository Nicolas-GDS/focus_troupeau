<?php

namespace App\Services\Referentiels;

use App\Domain\Referentiels\Referentiels;
use App\Domain\Referentiels\StatutEpistemique;
use App\Models\App\User;
use App\Services\Dossier\SaisieRefusee;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;

/**
 * Aliments de table (EF-M10-02, ref.aliment_reference et ref.aliment_reference_valeur) : saisis par l'administrateur
 * avec leur source et leur statut, tant que les tables INRAE-CIRAD-AFZ ne sont pas chargées (point ouvert 5, licence).
 * Non versionnés au schéma : un aliment se désactive au lieu d'être supprimé ; chaque modification est journalisée.
 */
class AlimentsReferenceService
{
    public const CATEGORIES = ['fourrage', 'cereale', 'correcteur_azote', 'tourteau', 'coproduit', 'mineral', 'autre'];

    /** Valeurs saisies par aliment (codes de ref.parametre, unités des bulletins). */
    public const PARAMETRES = ['UFL_2018', 'UFV_2018', 'PDI', 'BPR', 'MAT', 'NDF', 'AMI', 'CA', 'P', 'MG', 'K', 'NA', 'S', 'CU', 'ZN', 'MN', 'SE', 'I', 'CO'];

    public function __construct(
        private readonly JournalService $journal,
        private readonly Referentiels $referentiels,
    ) {}

    /**
     * @param  array{code: string, libelle: string, categorie: string, famille_code?: string|null, ms_pct: float|string, reference_id?: int|string|null, statut_epistemique: string, actif?: bool}  $d
     * @param  array<string, float|string|null>  $valeurs  code paramètre → valeur (vide : retirée)
     */
    public function enregistrer(?int $id, array $d, array $valeurs, User $auteur): int
    {
        $code = strtoupper(trim($d['code']));
        $ms = (float) str_replace(',', '.', (string) $d['ms_pct']);
        $erreurs = array_filter([
            'code' => preg_match('/^[A-Z0-9_]{2,30}$/', $code) === 1 ? null : 'Code : lettres majuscules, chiffres et _ (2 à 30).',
            'libelle' => trim($d['libelle']) === '' ? 'Libellé obligatoire.' : null,
            'categorie' => in_array($d['categorie'], self::CATEGORIES, true) ? null : 'Catégorie non admise.',
            'ms_pct' => $ms > 0 && $ms <= 100 ? null : 'MS entre 0 et 100 %.',
            'statut_epistemique' => StatutEpistemique::tryFrom($d['statut_epistemique']) === null ? 'Statut non admis.' : null,
            'doublon' => DB::table('ref.aliment_reference')->where('code', $code)->when($id, fn ($q) => $q->where('id', '!=', $id))->exists() ? 'Ce code existe déjà.' : null,
        ]);
        if ($erreurs !== []) {
            throw new SaisieRefusee(implode(' ', $erreurs));
        }

        return DB::transaction(function () use ($id, $d, $code, $ms, $valeurs, $auteur) {
            $ligne = ['code' => $code, 'libelle' => trim($d['libelle']), 'categorie' => $d['categorie'], 'famille_code' => ($d['famille_code'] ?? null) ?: null,
                'ms_pct' => $ms, 'reference_id' => ($d['reference_id'] ?? null) ? (int) $d['reference_id'] : null, 'statut_epistemique' => $d['statut_epistemique'],
                'actif' => $d['actif'] ?? true];
            if ($id === null) {
                $id = (int) DB::table('ref.aliment_reference')->insertGetId($ligne);
            } else {
                DB::table('ref.aliment_reference')->where('id', $id)->update($ligne);
            }
            $unites = DB::table('ref.parametre')->whereIn('code', self::PARAMETRES)->pluck('unite', 'code');
            foreach (self::PARAMETRES as $p) {
                $brut = $valeurs[$p] ?? null;
                if ($brut === null || trim((string) $brut) === '') {
                    DB::table('ref.aliment_reference_valeur')->where('aliment_reference_id', $id)->where('parametre_code', $p)->delete();

                    continue;
                }
                $v = str_replace(',', '.', trim((string) $brut));
                if (! is_numeric($v) || (float) $v < 0) {
                    throw new SaisieRefusee('Valeur non admise pour '.$p.'.');
                }
                DB::table('ref.aliment_reference_valeur')->updateOrInsert(['aliment_reference_id' => $id, 'parametre_code' => $p], ['valeur' => (float) $v, 'unite' => (string) $unites[$p]]);
            }
            $this->journal->ecrire('modification_referentiel', $auteur->id, 'ref.aliment_reference#'.$id, ['table' => 'ref.aliment_reference', 'code' => $code]);
            $this->referentiels->oublier();

            return $id;
        });
    }
}
