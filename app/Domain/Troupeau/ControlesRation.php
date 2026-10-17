<?php

namespace App\Domain\Troupeau;

/**
 * Ratios et contrôles de la ration (RC-9) : Ca/P (cible ; mâles ovins et caprins sous le bas de la cible, gravité 2,
 * NRC 2007 ; au-delà du maximum en préparation au vêlage, vigilance), K/Mg (au-delà du maximum chez les vaches à vêler,
 * vigilance), BACA de la ration (au-delà du maximum en fin de gestation, gravité 2, Meschy 2007), PDI/UFL (hors de
 * l'intervalle, information). L'encombrement demande une capacité d'ingestion, absente des repères chargés : il n'est
 * pas contrôlé. Un contrôle n'est calculé que si ses éléments sont apportés.
 */
final class ControlesRation
{
    /** Stades de fin de gestation au sens du contrôle de la BACA. */
    public const STADES_FIN_GESTATION = ['fin_gestation', 'prepa_velage'];

    public function __construct(private readonly SeuilsControles $seuils) {}

    /**
     * @param  array<string, float>  $apports  apports par animal et par jour
     * @return list<ControleRation>
     */
    public function controler(array $apports, float $msi, string $stade, string $espece, bool $male): array
    {
        $s = $this->seuils;
        $controles = [];

        if (! empty($apports['P']) && isset($apports['CA'])) {
            $r = $apports['CA'] / $apports['P'];
            [$gravite, $constat] = match (true) {
                $male && $r < $s->caPMin => [2, 'Ca/P bas chez des mâles : risque d\'urolithiase.'],
                $stade === 'prepa_velage' && $r > $s->caPMaxPrepaVelage => [1, 'Ca/P élevé en préparation au vêlage.'],
                $r < $s->caPMin || $r > $s->caPMax => [0, 'Ca/P hors de la cible.'],
                default => [null, 'Ca/P dans la cible.'],
            };
            $controles[] = new ControleRation('CA_P', 'Rapport Ca/P', $r, $gravite, $constat);
        }

        if (! empty($apports['MG']) && isset($apports['K'])) {
            $r = $apports['K'] / $apports['MG'];
            $vigilance = $espece === 'BOV' && $stade === 'prepa_velage' && $r > $s->kMgMax;
            $controles[] = new ControleRation('K_MG', 'Rapport K/Mg', $r, $vigilance ? 1 : null,
                $vigilance ? 'K/Mg élevé pour des vaches à vêler.' : 'K/Mg sans signalement.');
        }

        if (isset($apports['BACA']) && $msi > 0) {
            $baca = $apports['BACA'] / $msi;
            $alerte = in_array($stade, self::STADES_FIN_GESTATION, true) && $baca > $s->bacaMaxFinGestation;
            $controles[] = new ControleRation('BACA', 'BACA de la ration (mEq/kg MS)', $baca, $alerte ? 2 : null,
                $alerte ? 'BACA trop élevée en fin de gestation.' : 'BACA sans signalement.');
        }

        if (! empty($apports['UFL_2018']) && isset($apports['PDI'])) {
            $r = $apports['PDI'] / $apports['UFL_2018'];
            $hors = $r < $s->pdiUflMin || $r > $s->pdiUflMax;
            $controles[] = new ControleRation('PDI_UFL', 'Rapport PDI/UFL', $r, $hors ? 0 : null,
                $hors ? 'PDI/UFL hors de l\'intervalle de référence.' : 'PDI/UFL dans l\'intervalle de référence.');
        }

        return $controles;
    }
}
