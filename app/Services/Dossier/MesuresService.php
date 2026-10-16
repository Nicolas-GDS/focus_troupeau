<?php

namespace App\Services\Dossier;

use App\Domain\Dossier\LecteurMesureTerrain;
use App\Domain\Dossier\LectureMesure;
use App\Domain\Dossier\LecturesCombinees;
use App\Domain\Dossier\RegleLecture;
use App\Domain\Referentiels\Referentiels;
use App\Models\App\User;
use App\Models\Dossier\MesureTerrain;
use App\Models\Dossier\Visite;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Mesures terrain (EF-M1-05, RC-1.5) : saisie une ligne à la fois (EF-GEN-07), lecture de chaque mesure par
 * rapport au repère de son espèce et de son stade, lectures combinées par groupe (visite × espèce × stade) sur la
 * médiane, validation par le technicien.
 */
class MesuresService
{
    public const STADES = ['tarie' => 'Taries', 'prepa_velage' => 'Préparation au vêlage', 'lactation' => 'Lactation', 'veau' => 'Veaux'];

    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly LecteurMesureTerrain $lecteur = new LecteurMesureTerrain,
    ) {}

    public function ajouter(
        Visite $visite,
        User $auteur,
        string $dateMesure,
        string $mesureCode,
        float $valeur,
        ?string $especeCode,
        ?string $stade,
        ?string $identifiantAnimal = null,
    ): MesureTerrain {
        $unite = DB::table('ref.mesure_terrain')->where('code', $mesureCode)->value('unite');

        if ($unite === null) {
            throw new SaisieRefusee("Mesure inconnue : {$mesureCode}.");
        }

        if ($stade !== null && ! array_key_exists($stade, self::STADES)) {
            throw new SaisieRefusee("Stade inconnu : {$stade}.");
        }

        $repere = $this->lecteur->repere($mesureCode, $especeCode, $stade, $this->referentiels->reperesMesuresTerrain());

        return MesureTerrain::query()->create([
            'elevage_id' => $visite->elevage_id,
            'visite_id' => $visite->id,
            'date_mesure' => $dateMesure,
            'identifiant_animal' => $identifiantAnimal === null || trim($identifiantAnimal) === '' ? null : trim($identifiantAnimal),
            'stade' => $stade,
            'espece_code' => $especeCode,
            'mesure_code' => $mesureCode,
            'valeur' => $valeur,
            'unite' => (string) $unite,
            'lecture_code' => $repere === null ? null : $this->lecteur->lire($valeur, $repere)->value,
            'saisie_par' => $auteur->id,
            'maj_par' => $auteur->id,
        ]);
    }

    /** Valide toutes les mesures saisies de la visite ; renvoie leur nombre. */
    public function validerVisite(Visite $visite, User $auteur): int
    {
        return MesureTerrain::query()->where('visite_id', $visite->id)->where('statut', MesureTerrain::STATUT_SAISIE)
            ->update(['statut' => MesureTerrain::STATUT_VALIDEE, 'maj_par' => $auteur->id]);
    }

    public function supprimer(MesureTerrain $mesure): void
    {
        $mesure->delete();
    }

    /**
     * Groupes de mesures de la visite (espèce × stade) : par mesure, valeurs, médiane, repère, lecture de la médiane,
     * source ; lectures combinées proposées.
     *
     * @return list<array{espece: string|null, stade: string|null, libelle: string, mesures: list<array<string, mixed>>, lectures: list<array{libelle: string, source: array{source: string|null, statut: string, a_valider: bool}}>}>
     */
    public function groupes(Visite $visite): array
    {
        return $this->groupesDe(MesureTerrain::query()->where('visite_id', $visite->id)->orderBy('date_mesure')->orderBy('id')->get());
    }

    /**
     * Groupes d'un ensemble de mesures déjà choisi (volet Animaux E07 : mesures lisibles de l'élevage, par date).
     *
     * @param  Collection<int, MesureTerrain>  $lignes
     * @return list<array{espece: string|null, stade: string|null, libelle: string, mesures: list<array<string, mixed>>, lectures: list<array{libelle: string, source: array{source: string|null, statut: string, a_valider: bool}}>}>
     */
    public function groupesDe(Collection $lignes): array
    {
        $reperes = $this->referentiels->reperesMesuresTerrain();
        $regles = $this->referentiels->reglesLectureMesures();
        $refs = DB::table('ref.mesure_terrain')->orderBy('ordre')->get()->keyBy('code');
        $groupes = [];

        foreach ($lignes->groupBy(fn (MesureTerrain $m) => ($m->espece_code ?? '').'|'.($m->stade ?? '')) as $cle => $mesuresGroupe) {
            [$espece, $stade] = array_map(fn ($v) => $v === '' ? null : $v, explode('|', (string) $cle));
            $medianes = [];
            $lectures = [];
            $parMesure = [];

            foreach ($refs as $code => $ref) {
                $valeurs = $mesuresGroupe->where('mesure_code', $code)->map(fn (MesureTerrain $m) => (float) $m->valeur)->values()->all();

                if ($valeurs === []) {
                    continue;
                }

                $mediane = (float) LecturesCombinees::mediane($valeurs);
                $repere = $this->lecteur->repere((string) $code, $espece, $stade, $reperes);
                $lecture = $repere === null ? null : $this->lecteur->lire($mediane, $repere);
                $medianes[(string) $code] = $mediane;
                if ($lecture !== null) {
                    $lectures[(string) $code] = $lecture;
                }

                $parMesure[] = [
                    'code' => (string) $code,
                    'libelle' => (string) $ref->libelle,
                    'unite' => (string) $ref->unite,
                    'decimales' => (int) $ref->decimales,
                    'mesures' => $mesuresGroupe->where('mesure_code', $code)->values()->all(),
                    'mediane' => $mediane,
                    'repere' => $repere === null ? null : Affichage::bornes($repere->borneBasse, $repere->borneHaute, (string) $ref->unite),
                    'lecture' => $lecture,
                    'source' => $repere === null ? null : Affichage::source($repere->source),
                ];
            }

            $groupes[] = [
                'espece' => $espece,
                'stade' => $stade,
                'libelle' => $stade === null ? 'Stade non précisé' : self::STADES[$stade],
                'mesures' => $parMesure,
                'lectures' => array_map(fn (RegleLecture $r) => ['libelle' => $r->libellePropose, 'source' => Affichage::source($r->source)],
                    (new LecturesCombinees)->proposer($medianes, $lectures, $regles, $espece, $stade)),
            ];
        }

        return $groupes;
    }

    public static function libelleLecture(?string $code): string
    {
        return $code === null ? 'repère non disponible' : LectureMesure::from($code)->libelle();
    }
}
