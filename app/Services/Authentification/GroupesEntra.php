<?php

namespace App\Services\Authentification;

/**
 * Lecture de la correspondance groupes Entra → rôle et GDS (variable ENTRA_GROUPES, EF-M0-04).
 * Format : « id_groupe:role:gds » séparés par des virgules ; gds vaut un identifiant de GDS ou * (tous).
 */
final class GroupesEntra
{
    /**
     * @return array<string, list<array{role: string, gds: int|'*'}>>
     */
    public static function lire(string $valeur): array
    {
        $groupes = [];

        foreach (array_filter(array_map('trim', explode(',', $valeur))) as $entree) {
            [$idGroupe, $role, $gds] = array_pad(array_map('trim', explode(':', $entree)), 3, null);

            if ($idGroupe && $role) {
                $groupes[$idGroupe][] = ['role' => $role, 'gds' => $gds === null || $gds === '*' ? '*' : (int) $gds];
            }
        }

        return $groupes;
    }
}
