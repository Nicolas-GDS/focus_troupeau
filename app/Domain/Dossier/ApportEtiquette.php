<?php

namespace App\Domain\Dossier;

/**
 * EF-M1-06 — Apport journalier d'un élément par un minéral, un bolus, un seau ou un aliment : teneur par kg de
 * produit × dose distribuée (« on multiplie la teneur par kg par la dose distribuée, 100 g = 0,1 kg », présentation
 * Forgeneuf, diapositive 21). La comparaison au besoin (RC-9) relève du lot L5 (décision du 16/10/2026).
 *
 * Unités de teneur admises : « mg/kg », « g/kg », « µg/kg », « UI/kg » (apport en mg, g, µg, UI par animal et par
 * jour) et « % » (apport en g par animal et par jour, 1 % = 10 g/kg).
 */
final class ApportEtiquette
{
    private const PAR_KG = ['mg/kg' => 'mg', 'g/kg' => 'g', 'µg/kg' => 'µg', 'UI/kg' => 'UI'];

    /** @return list<string> */
    public static function unitesAdmises(): array
    {
        return [...array_keys(self::PAR_KG), '%'];
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
