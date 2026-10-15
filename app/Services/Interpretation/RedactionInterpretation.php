<?php

namespace App\Services\Interpretation;

/**
 * Saisie du technicien sur une interprétation en brouillon (EF-M2-03), dans l'ordre imposé des trois lectures :
 * propreté et conservation ; minéraux ; valeur nutritionnelle. Le niveau retenu est distinct du niveau proposé (RC-1.4).
 */
final readonly class RedactionInterpretation
{
    /**
     * @param  list<string>  $utilisationDeconseillee  codes de ref.categorie_animale
     */
    public function __construct(
        public ?string $lectureConservation = null,
        public ?string $lectureMineraux = null,
        public ?string $lectureNutritionnelle = null,
        public ?string $synthese = null,
        public ?string $recommandations = null,
        public ?string $utilisationConseillee = null,
        public array $utilisationDeconseillee = [],
        public ?int $niveauRetenu = null,
        public bool $orientationVeterinaire = false,
    ) {}
}
