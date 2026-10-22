<?php

namespace App\Services\Agds;

use App\Domain\Agds\BovinAgds;
use App\Domain\Agds\IdentiteAgds;
use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Lecture des vues AGDS exposées par SAGEMA (schéma « focus », compte en lecture seule) : focus.agds_elevage et
 * focus.agds_bovin_present. Chaque requête filtre sur le numéro EDE (8 chiffres) et le département ; SAGEMA
 * pousse ce filtre jusqu'au serveur AGDS (tables distantes), aucune agrégation n'est faite côté SQL.
 */
class VuesSagemaAgds implements SourceAgds
{
    public function identite(string $departement, string $numEde): ?IdentiteAgds
    {
        $ligne = $this->lire(fn (ConnectionInterface $db) => $db->table('agds_elevage')
            ->where('num_ede', $numEde)->where('departement', $departement)->first());

        if ($ligne === null) {
            return null;
        }

        return new IdentiteAgds(
            numEde: $ligne->num_ede,
            nom: $ligne->nom,
            civilite: $ligne->civilite,
            adresse: $this->lignes($ligne->adresse_1, $ligne->adresse_2, $ligne->adresse_3),
            codePostal: $ligne->code_postal,
            commune: $ligne->commune,
            nomCorrespondance: $ligne->correspondance_nom,
            adresseCorrespondance: $this->lignes($ligne->correspondance_adresse_1, $ligne->correspondance_adresse_2, $ligne->correspondance_adresse_3),
            codePostalCorrespondance: $ligne->correspondance_code_postal,
            communeCorrespondance: $ligne->correspondance_commune,
            telephone: $ligne->telephone,
            mobile: $ligne->mobile,
            courriel: $ligne->courriel,
            dateDebutActivite: $this->date($ligne->date_debut_activite),
            dateCessation: $this->date($ligne->date_cessation),
        );
    }

    public function bovinsPresents(string $departement, string $numEde): array
    {
        $lignes = $this->lire(fn (ConnectionInterface $db) => $db->table('agds_bovin_present')
            ->where('num_ede', $numEde)->where('departement', $departement)
            ->orderBy('date_naissance')->get());

        return $lignes->map(fn (object $l) => new BovinAgds(
            numeroNational: $l->numero_national,
            paysIso: $l->pays_iso === null ? null : (int) $l->pays_iso,
            sexe: $l->sexe,
            raceCode: $l->race_code,
            dateNaissance: $this->date($l->date_naissance),
            dateEntree: $this->date($l->date_entree),
        ))->values()->all();
    }

    /**
     * @template T
     *
     * @param  callable(ConnectionInterface): T  $requete
     * @return T
     */
    private function lire(callable $requete): mixed
    {
        try {
            return $requete(DB::connection((string) config('agds.connexion')));
        } catch (Throwable $e) {
            throw new SourceAgdsIndisponible('Lecture AGDS par SAGEMA impossible : '.$e->getMessage(), previous: $e);
        }
    }

    /** @return list<string> */
    private function lignes(?string ...$lignes): array
    {
        return array_values(array_filter(array_map(fn (?string $l) => trim((string) $l), $lignes), fn (string $l) => $l !== ''));
    }

    private function date(?string $valeur): ?DateTimeImmutable
    {
        return $valeur === null ? null : new DateTimeImmutable(substr($valeur, 0, 10));
    }
}
