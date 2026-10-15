<?php

namespace App\Services\Import;

/**
 * Traduction des clés de parse_lano.py vers les codes de ref.parametre (annexe 10.3), avec la censure (« <0,1 ») et
 * l'unité. Une clé inconnue ou ambiguë n'est pas importée : elle est signalée dans le rapport d'import.
 *
 * La clé « MG » du parseur (distincte de « Mg », magnésium) n'est pas importée : MG désigne le magnésium dans
 * ref.parametre et le sens de cette clé sur les bulletins (matières grasses ?) n'est pas établi.
 */
final class CorrespondanceLano
{
    /** @var array<string, string> clé du parseur => code ref.parametre */
    private const CODES = [
        'MS' => 'MS', 'MM' => 'MM', 'MO' => 'MO', 'MAT' => 'MAT', 'CB' => 'CB', 'DCS' => 'DCS', 'SSR' => 'SSR',
        'AMI' => 'AMI', 'MGT' => 'MGT', 'LIG' => 'ADL', 'pH' => 'PH', 'NDF' => 'NDF', 'ADF' => 'ADF', 'ADL' => 'ADL',
        'P' => 'P', 'Ca' => 'CA', 'Mg' => 'MG', 'K' => 'K', 'Na' => 'NA', 'Cu' => 'CU', 'Zn' => 'ZN', 'Mn' => 'MN',
        'Fe' => 'FE', 'S' => 'S', 'Al' => 'AL',
        'dMO' => 'DMO', 'UFL' => 'UFL', 'UFV' => 'UFV', 'PDIN' => 'PDIN', 'PDIE' => 'PDIE', 'PDIA' => 'PDIA',
        'UEL' => 'UEL', 'UEB' => 'UEB', 'UEM' => 'UEM', 'AMIDR' => 'AMIDR', 'dMOna' => 'DMONA', 'dNDF' => 'DNDF',
        'P_abs' => 'P_ABS', 'Ca_abs' => 'CA_ABS', 'BACA' => 'BACA', 'BE' => 'BE',
        'UFL_2018' => 'UFL_2018', 'UFV_2018' => 'UFV_2018', 'PDI' => 'PDI', 'PDIA_2018' => 'PDIA_2018', 'BPR' => 'BPR',
        'DT6_N' => 'DT6_N', 'DT6_AMI' => 'DT6_AMI', 'AMIDR_2018' => 'AMIDR_2018', 'AMI_BYPASS' => 'AMI_BYPASS',
        'NI' => 'NI', 'EB' => 'EB', 'DON' => 'DON', 'ZEA' => 'ZEA', 'FUM' => 'FUM',
    ];

    /** @var array<string, string> unité du bulletin => unité du dictionnaire */
    private const UNITES = ['Kcal/kg MS' => 'kcal/kg MS'];

    /**
     * @param  array<string, mixed>  $resultats  sortie « resultats » du parseur (clé, clé__unit)
     * @return array{resultats: list<ResultatExtrait>, avertissements: list<string>}
     */
    public function traduire(array $resultats): array
    {
        $traduits = [];
        $avertissements = [];

        foreach ($resultats as $cle => $valeur) {
            if (str_ends_with((string) $cle, '__unit')) {
                continue;
            }

            $code = self::CODES[$cle] ?? null;
            if ($code === null) {
                $avertissements[] = "Paramètre « {$cle} » du bulletin non importé (absent du dictionnaire ou ambigu).";

                continue;
            }

            if (isset($traduits[$code])) {
                $avertissements[] = "Paramètre {$code} présent deux fois sur le bulletin : première valeur conservée.";

                continue;
            }

            [$nombre, $censure] = self::nombre($valeur);
            if ($nombre === null) {
                $avertissements[] = "Valeur illisible pour {$code} : « {$valeur} ».";

                continue;
            }

            $unite = (string) ($resultats[$cle.'__unit'] ?? '');
            $traduits[$code] = new ResultatExtrait($code, $nombre, self::UNITES[$unite] ?? $unite, $censure);
        }

        return ['resultats' => array_values($traduits), 'avertissements' => $avertissements];
    }

    /**
     * @return array{0: float|null, 1: string|null}
     */
    private static function nombre(mixed $valeur): array
    {
        if (is_int($valeur) || is_float($valeur)) {
            return [(float) $valeur, null];
        }

        $texte = str_replace([' ', ','], ['', '.'], (string) $valeur);
        $censure = null;

        if (str_starts_with($texte, '<') || str_starts_with($texte, '>')) {
            $censure = $texte[0];
            $texte = substr($texte, 1);
        }

        return is_numeric($texte) ? [(float) $texte, $censure] : [null, null];
    }
}
