<?php

namespace App\Domain\Referentiels\Edition;

use App\Domain\Interpretation\Seuil;
use App\Domain\Referentiels\StatutEpistemique;

/**
 * Seuil de la grille tel que saisi dans E24 (EF-M10-02, EF-M10-03), avant contrôle : valeurs brutes, codes vides
 * ramenés à null, nombres à la virgule française acceptés.
 */
final readonly class SaisieSeuil
{
    public function __construct(
        public string $parametreCode,
        public ?string $familleFourrageCode,
        public ?string $typeFourrageCode,
        public ?string $especeCode,
        public ?string $categorieCode,
        public string $nature,
        public ?float $borneMin,
        public ?float $borneMax,
        public string $classe,
        public ?int $gravite,
        public ?string $sens,
        public ?string $message,
        public ?int $referenceId,
        public string $statutEpistemique,
        public ?string $commentaire = null,
    ) {}

    /**
     * @param  array<string, mixed>  $donnees  colonnes de ref.seuil_interpretation (formulaire ou ligne existante)
     */
    public static function depuis(array $donnees): self
    {
        return new self(
            parametreCode: (string) Valeurs::texte($donnees['parametre_code'] ?? null),
            familleFourrageCode: Valeurs::texte($donnees['famille_fourrage_code'] ?? null),
            typeFourrageCode: Valeurs::texte($donnees['type_fourrage_code'] ?? null),
            especeCode: Valeurs::texte($donnees['espece_code'] ?? null),
            categorieCode: Valeurs::texte($donnees['categorie_code'] ?? null),
            nature: (string) Valeurs::texte($donnees['nature'] ?? null),
            borneMin: Valeurs::nombre($donnees['borne_min'] ?? null),
            borneMax: Valeurs::nombre($donnees['borne_max'] ?? null),
            classe: (string) Valeurs::texte($donnees['classe'] ?? null),
            gravite: Valeurs::entier($donnees['gravite'] ?? null),
            sens: Valeurs::texte($donnees['sens'] ?? null),
            message: Valeurs::texte($donnees['message'] ?? null),
            referenceId: Valeurs::entier($donnees['reference_id'] ?? null),
            statutEpistemique: (string) Valeurs::texte($donnees['statut_epistemique'] ?? null),
            commentaire: Valeurs::texte($donnees['commentaire'] ?? null),
        );
    }

    /**
     * Complète la famille d'après le type et l'espèce d'après la catégorie (déductions sans perte : un type
     * n'appartient qu'à une famille, une catégorie qu'à une espèce). Le contrôle des chevauchements compare
     * ainsi des niveaux de précision comparables (RC-1, « Départage et cohérence de la grille »).
     */
    public function normalisee(Nomenclatures $nomenclatures): self
    {
        $famille = $this->familleFourrageCode
            ?? ($this->typeFourrageCode === null ? null : ($nomenclatures->familleParType[$this->typeFourrageCode] ?? null));
        $espece = $this->especeCode
            ?? ($this->categorieCode === null ? null : ($nomenclatures->especeParCategorie[$this->categorieCode] ?? null));

        return new self(
            parametreCode: $this->parametreCode,
            familleFourrageCode: $famille,
            typeFourrageCode: $this->typeFourrageCode,
            especeCode: $espece,
            categorieCode: $this->categorieCode,
            nature: $this->nature,
            borneMin: $this->borneMin,
            borneMax: $this->borneMax,
            classe: $this->classe,
            gravite: $this->gravite,
            sens: $this->sens,
            message: $this->message,
            referenceId: $this->referenceId,
            statutEpistemique: $this->statutEpistemique,
            commentaire: $this->commentaire,
        );
    }

    /** Seuil du domaine correspondant (à n'utiliser qu'après contrôle : statut et gravité valides). */
    public function versSeuil(int $id = 0): Seuil
    {
        return new Seuil(
            id: $id,
            parametreCode: $this->parametreCode,
            classe: $this->classe,
            gravite: $this->gravite ?? 0,
            borneMin: $this->borneMin,
            borneMax: $this->borneMax,
            familleFourrageCode: $this->familleFourrageCode,
            typeFourrageCode: $this->typeFourrageCode,
            especeCode: $this->especeCode,
            categorieCode: $this->categorieCode,
            nature: $this->nature,
            message: $this->message,
            referenceId: $this->referenceId,
            statutEpistemique: StatutEpistemique::tryFrom($this->statutEpistemique) ?? StatutEpistemique::HypotheseLocale,
            sens: $this->sens,
        );
    }

    /**
     * Colonnes de ref.seuil_interpretation (hors version et activité).
     *
     * @return array<string, string|int|float|null>
     */
    public function colonnes(): array
    {
        return [
            'parametre_code' => $this->parametreCode,
            'famille_fourrage_code' => $this->familleFourrageCode,
            'type_fourrage_code' => $this->typeFourrageCode,
            'espece_code' => $this->especeCode,
            'categorie_code' => $this->categorieCode,
            'nature' => $this->nature,
            'borne_min' => $this->borneMin,
            'borne_max' => $this->borneMax,
            'classe' => $this->classe,
            'gravite' => $this->gravite,
            'sens' => $this->sens,
            'message' => $this->message,
            'reference_id' => $this->referenceId,
            'statut_epistemique' => $this->statutEpistemique,
            'commentaire' => $this->commentaire,
        ];
    }
}
