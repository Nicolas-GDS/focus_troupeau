<?php

namespace App\Domain\Interpretation;

/**
 * Lien paramètre d'analyse × trouble sanitaire (ref.compatibilite_parametre_trouble, chapitre 11) :
 * sens (carence, exces, ratio_bas, ratio_eleve), espèces et catégories concernées (null : toutes), force 1 à 3.
 */
final readonly class LienCompatibilite
{
    /**
     * @param  list<string>|null  $especes
     * @param  list<string>|null  $categories
     */
    public function __construct(
        public string $parametreCode,
        public string $sens,
        public string $troubleCode,
        public int $force,
        public ?array $especes = null,
        public ?array $categories = null,
    ) {}

    /** Un seuil « bas » appelle les liens de carence ou de ratio bas, un seuil « haut » les liens d'excès ou de ratio élevé. */
    public function correspondAuSens(?string $sensSeuil): bool
    {
        return match ($sensSeuil) {
            Seuil::SENS_BAS => in_array($this->sens, ['carence', 'ratio_bas'], true),
            Seuil::SENS_HAUT => in_array($this->sens, ['exces', 'ratio_eleve'], true),
            default => false,
        };
    }

    /** L'espèce de l'événement doit figurer parmi celles du lien ; sa catégorie aussi, quand les deux sont renseignées. */
    public function concerne(EvenementSanitaire $evenement): bool
    {
        return ($this->especes === null || in_array($evenement->especeCode, $this->especes, true))
            && ($this->categories === null || $evenement->categorieCode === null
                || in_array($evenement->categorieCode, $this->categories, true));
    }
}
