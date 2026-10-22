<?php

namespace App\Services\Agds;

use App\Domain\Agds\BovinAgds;
use App\Domain\Agds\IdentiteAgds;

/**
 * Source des données AGDS d'un cheptel (EF-M9-03, § 6.4) : identité de l'éleveur et bovins présents.
 * Le département borne toujours la lecture : un cheptel n'est lu que dans le département de l'élevage.
 * Implémentation : VuesSagemaAgds (vues du schéma « focus » de SAGEMA) ; un double la remplace dans les tests.
 */
interface SourceAgds
{
    /** @throws SourceAgdsIndisponible */
    public function identite(string $departement, string $numEde): ?IdentiteAgds;

    /**
     * @return list<BovinAgds>
     *
     * @throws SourceAgdsIndisponible
     */
    public function bovinsPresents(string $departement, string $numEde): array;
}
