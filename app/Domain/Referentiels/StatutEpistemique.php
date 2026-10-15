<?php

namespace App\Domain\Referentiels;

/**
 * Statut épistémique d'un repère (chapitre 4, EF-GEN-02). Les valeurs sont celles du socle (document 02) ;
 * la correspondance avec la convention des spécifications ([T], [V], [M], [C], [CP], [NV]) est une proposition
 * du lot L2, à valider.
 */
enum StatutEpistemique: string
{
    case Publie = 'publie';
    case PublieSourceSecondaire = 'publie_source_secondaire';
    case ConsensusProfessionnel = 'consensus_professionnel';
    case OrdreDeGrandeur = 'ordre_de_grandeur';
    case HypotheseLocale = 'hypothese_locale';

    public function libelle(): string
    {
        return match ($this) {
            self::Publie => 'Source publiée',
            self::PublieSourceSecondaire => 'Source publiée, citée par une source secondaire',
            self::ConsensusProfessionnel => 'Consensus professionnel',
            self::OrdreDeGrandeur => 'Ordre de grandeur',
            self::HypotheseLocale => 'Proposition du GDS, à valider par le comité technique',
        };
    }

    /** Code de la convention des spécifications (chapitre 4). */
    public function codeSpecifications(): string
    {
        return match ($this) {
            self::Publie => 'V',
            self::PublieSourceSecondaire => 'NV',
            self::ConsensusProfessionnel, self::OrdreDeGrandeur => 'C',
            self::HypotheseLocale => 'CP',
        };
    }

    /** Repère à faire valider avant publication à l'éleveur (§ 9.5). */
    public function aValider(): bool
    {
        return $this === self::HypotheseLocale;
    }
}
