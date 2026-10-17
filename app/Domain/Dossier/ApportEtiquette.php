<?php

namespace App\Domain\Dossier;

/**
 * EF-M1-06 — Apport journalier d'un élément par un minéral, un bolus, un seau ou un aliment : teneur par kg de
 * produit × dose distribuée (« on multiplie la teneur par kg par la dose distribuée, 100 g = 0,1 kg », présentation
 * Forgeneuf, diapositive 21). La comparaison au besoin (RC-9) relève du lot L5 (décision du 16/10/2026).
 *
 * Unités de teneur admises : « mg/kg », « g/kg », « µg/kg », « UI/kg », « UFL/kg » (apport en mg, g, µg, UI, UFL par
 * animal et par jour ; UFL/kg ajouté au lot L5 pour les concentrés) et « % » (apport en g par animal et par jour,
 * 1 % = 10 g/kg).
 */
final class ApportEtiquette
{
    private const PAR_KG = ['mg/kg' => 'mg', 'g/kg' => 'g', 'µg/kg' => 'µg', 'UI/kg' => 'UI', 'UFL/kg' => 'UFL'];

    /** Facteur vers l'unité de base de chaque famille d'unités (masse : g/kg ; énergie : UFL/kg). */
    private const VERS_BASE = ['g/kg' => 1.0, 'mg/kg' => 0.001, 'µg/kg' => 0.000001, '%' => 10.0, 'UFL/kg' => 1.0];

    /** @return list<string> */
    public static function unitesAdmises(): array
    {
        return [...array_keys(self::PAR_KG), '%'];
    }

    /**
     * Teneur par kg MS dans l'unité du paramètre (RC-9, composant de ration décrit par étiquette, décision du
     * 17/10/2026) : la teneur saisie par kg de produit brut est convertie dans l'unité du référentiel (g/kg MS,
     * mg/kg MS, UFL/kg MS) puis rapportée à la matière sèche. Null si les unités ne se convertissent pas l'une dans
     * l'autre (UI, unité inconnue).
     */
    public static function teneurParKgMs(float $teneur, string $unite, string $uniteParametre, float $msPct): ?float
    {
        $cible = str_replace(' MS', '', $uniteParametre);
        $energie = fn (string $u) => $u === 'UFL/kg';

        if (! isset(self::VERS_BASE[$unite], self::VERS_BASE[$cible]) || $energie($unite) !== $energie($cible) || $msPct <= 0) {
            return null;
        }

        return $teneur * self::VERS_BASE[$unite] / self::VERS_BASE[$cible] / ($msPct / 100);
    }

    /**
     * @return array{valeur: float, unite: string}|null apport par animal et par jour ; null si l'unité est inconnue
     */
    public static function apport(float $teneur, string $unite, float $doseGrammes): ?array
    {
        $doseKg = $doseGrammes / 1000;

        if ($unite === '%') {
            return ['valeur' => $teneur * 10 * $doseKg, 'unite' => 'g'];
        }

        if (! isset(self::PAR_KG[$unite])) {
            return null;
        }

        return ['valeur' => $teneur * $doseKg, 'unite' => self::PAR_KG[$unite]];
    }
}
