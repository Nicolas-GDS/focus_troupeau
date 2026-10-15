<?php

namespace App\Domain\Referentiels\Edition;

use App\Domain\Interpretation\PlagePlausibilite;

/**
 * Plage de plausibilité (RC-2) telle que saisie dans E24, avant contrôle.
 */
final readonly class SaisiePlage
{
    public function __construct(
        public string $parametreCode,
        public ?string $familleFourrageCode,
        public ?float $borneMin,
        public ?float $borneMax,
        public string $statutEpistemique,
        public ?string $source = null,
        public ?string $commentaire = null,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees  colonnes de ref.plage_plausibilite (formulaire ou ligne existante)
     */
    public static function depuis(array $donnees): self
    {
        return new self(
            parametreCode: (string) Valeurs::texte($donnees['parametre_code'] ?? null),
            familleFourrageCode: Valeurs::texte($donnees['famille_fourrage_code'] ?? null),
            borneMin: Valeurs::nombre($donnees['borne_min'] ?? null),
            borneMax: Valeurs::nombre($donnees['borne_max'] ?? null),
            statutEpistemique: (string) Valeurs::texte($donnees['statut_epistemique'] ?? null),
            source: Valeurs::texte($donnees['source'] ?? null),
            commentaire: Valeurs::texte($donnees['commentaire'] ?? null),
        );
    }

    public function versPlage(): PlagePlausibilite
    {
        return new PlagePlausibilite($this->parametreCode, $this->borneMin, $this->borneMax, $this->familleFourrageCode);
    }

    /**
     * Colonnes de ref.plage_plausibilite (hors version et activité).
     *
     * @return array<string, string|float|null>
     */
    public function colonnes(): array
    {
        return [
            'parametre_code' => $this->parametreCode,
            'famille_fourrage_code' => $this->familleFourrageCode,
            'borne_min' => $this->borneMin,
            'borne_max' => $this->borneMax,
            'statut_epistemique' => $this->statutEpistemique,
            'source' => $this->source,
            'commentaire' => $this->commentaire,
        ];
    }
}
