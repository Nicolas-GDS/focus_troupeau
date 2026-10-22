<?php

namespace App\Domain\Rapprochement;

use Normalizer;

/**
 * Pré-rapprochement d'un bulletin LANO resté « à rapprocher » (EF-M9-01) : compare l'en-tête extrait (éleveur, tiers,
 * adresse, code postal, commune) à l'identité des élevages sous convention active et propose les plus proches.
 *
 * Score sur 100 : nom 60 (raison sociale ou contact, formes juridiques et articles ignorés, une faute de frappe
 * tolérée par mot de 5 lettres et plus), code postal 20, commune 10, adresse 10. Ces poids règlent un classement
 * de candidats, pas une règle de calcul métier : rien n'est rapproché sans la décision du technicien.
 */
final class PreRapprochement
{
    private const POIDS_NOM = 60;

    private const POIDS_CODE_POSTAL = 20;

    private const POIDS_COMMUNE = 10;

    private const POIDS_ADRESSE = 10;

    /** Score en dessous duquel un élevage n'est pas proposé. */
    private const SEUIL_PROPOSITION = 30;

    private const SEUIL_FORTE = 70;

    private const SEUIL_MOYENNE = 45;

    /** Similarité de nom (0 à 1) à partir de laquelle le nom figure parmi les motifs. */
    private const SEUIL_MOTIF_NOM = 0.5;

    private const LONGUEUR_MIN_FAUTE_TOLEREE = 5;

    /** Mots sans valeur d'identification : formes juridiques, civilités, articles. */
    private const MOTS_IGNORES = ['EARL', 'GAEC', 'SCEA', 'SARL', 'SAS', 'SA', 'SCL', 'EI', 'EURL', 'GFA', 'SCI', 'SNC',
        'M', 'MR', 'MME', 'MLLE', 'MONSIEUR', 'MADAME', 'DE', 'DU', 'DES', 'LA', 'LE', 'LES', 'L', 'D', 'ET', 'AU', 'AUX', 'EN'];

    private const ABREVIATIONS = ['ST' => 'SAINT', 'STE' => 'SAINTE'];

    /**
     * @param  array<string, mixed>  $entete  en-tête extrait du bulletin (parse_lano.py)
     * @param  iterable<ElevageCandidat>  $candidats
     * @return list<PropositionRapprochement> du score le plus élevé au plus faible
     */
    public function proposer(array $entete, iterable $candidats, int $limite = 3): array
    {
        $noms = array_values(array_filter([self::mots($entete['eleveur'] ?? null), self::mots($entete['tiers'] ?? null)]));
        $adresse = self::mots($entete['adresse'] ?? null);
        $codePostal = self::texte($entete['code_postal'] ?? null);
        $commune = implode(' ', self::mots($entete['commune'] ?? null));

        $propositions = [];
        foreach ($candidats as $candidat) {
            $proposition = $this->evaluer($candidat, $noms, $adresse, $codePostal, $commune);
            if ($proposition !== null) {
                $propositions[] = $proposition;
            }
        }

        usort($propositions, fn (PropositionRapprochement $a, PropositionRapprochement $b) => [$b->score, $a->elevage->libelle()] <=> [$a->score, $b->elevage->libelle()]);

        return array_slice($propositions, 0, $limite);
    }

    /**
     * La proposition à mettre en avant : une seule proposition forte, sinon aucune (deux élevages aussi proches
     * laissent le choix entier au technicien).
     *
     * @param  list<PropositionRapprochement>  $propositions
     */
    public static function evidente(array $propositions): ?PropositionRapprochement
    {
        $fortes = array_values(array_filter($propositions, fn (PropositionRapprochement $p) => $p->niveau === PropositionRapprochement::FORTE));

        return count($fortes) === 1 ? $fortes[0] : null;
    }

    /**
     * @param  list<list<string>>  $noms
     * @param  list<string>  $adresse
     */
    private function evaluer(ElevageCandidat $candidat, array $noms, array $adresse, string $codePostal, string $commune): ?PropositionRapprochement
    {
        $motifs = [];

        $similariteNom = 0.0;
        foreach ([$candidat->raisonSociale, $candidat->nomContact] as $nomCandidat) {
            foreach ($noms as $nom) {
                $similariteNom = max($similariteNom, self::similarite($nom, self::mots($nomCandidat)));
            }
        }
        if ($similariteNom >= 1.0) {
            $motifs[] = 'Nom identique';
        } elseif ($similariteNom >= self::SEUIL_MOTIF_NOM) {
            $motifs[] = 'Nom proche';
        }

        $memeCodePostal = $codePostal !== '' && $codePostal === self::texte($candidat->codePostal);
        if ($memeCodePostal) {
            $motifs[] = 'Code postal identique';
        }

        $memeCommune = $commune !== '' && $commune === implode(' ', self::mots($candidat->commune));
        if ($memeCommune) {
            $motifs[] = 'Commune identique';
        }

        $similariteAdresse = self::similarite($adresse, self::mots($candidat->adresse));
        if ($similariteAdresse >= self::SEUIL_MOTIF_NOM) {
            $motifs[] = 'Adresse proche';
        }

        $score = (int) round(self::POIDS_NOM * $similariteNom + self::POIDS_CODE_POSTAL * (int) $memeCodePostal
            + self::POIDS_COMMUNE * (int) $memeCommune + self::POIDS_ADRESSE * $similariteAdresse);

        if ($score < self::SEUIL_PROPOSITION) {
            return null;
        }

        $niveau = match (true) {
            $score >= self::SEUIL_FORTE => PropositionRapprochement::FORTE,
            $score >= self::SEUIL_MOYENNE => PropositionRapprochement::MOYENNE,
            default => PropositionRapprochement::FAIBLE,
        };

        return new PropositionRapprochement($candidat, $score, $niveau, $motifs);
    }

    /**
     * Coefficient de Dice sur les mots : deux mots se valent s'ils sont égaux, ou à une lettre près à partir de
     * cinq lettres.
     *
     * @param  list<string>  $a
     * @param  list<string>  $b
     */
    private static function similarite(array $a, array $b): float
    {
        if ($a === [] || $b === []) {
            return 0.0;
        }

        $restants = $b;
        $communs = 0;
        foreach ($a as $mot) {
            foreach ($restants as $i => $autre) {
                if (self::motsEquivalents($mot, $autre)) {
                    $communs++;
                    unset($restants[$i]);
                    break;
                }
            }
        }

        return 2 * $communs / (count($a) + count($b));
    }

    private static function motsEquivalents(string $a, string $b): bool
    {
        return $a === $b
            || (min(strlen($a), strlen($b)) >= self::LONGUEUR_MIN_FAUTE_TOLEREE && levenshtein($a, $b) <= 1);
    }

    /**
     * Mots significatifs, en majuscules et sans accents.
     *
     * @return list<string>
     */
    private static function mots(mixed $valeur): array
    {
        $texte = self::texte($valeur);
        $decompose = Normalizer::normalize($texte, Normalizer::FORM_D);
        $sansAccents = (string) preg_replace('/\p{Mn}+/u', '', $decompose === false ? $texte : $decompose);
        $mots = preg_split('/[^A-Z0-9]+/', mb_strtoupper($sansAccents), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return array_values(array_filter(
            array_map(fn (string $mot) => self::ABREVIATIONS[$mot] ?? $mot, $mots),
            fn (string $mot) => ! in_array($mot, self::MOTS_IGNORES, true),
        ));
    }

    private static function texte(mixed $valeur): string
    {
        return is_string($valeur) ? trim($valeur) : '';
    }
}
