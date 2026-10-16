<?php

namespace App\Services\Dossier;

use App\Domain\Dossier\LecteurProfilSanguin;
use App\Domain\Dossier\LectureProfil;
use App\Domain\Referentiels\Referentiels;
use App\Models\App\User;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\AnalyseAnimale;
use Illuminate\Support\Facades\DB;

/**
 * Profils sanguins saisis manuellement (EF-M1-05, EF-M9-02, RC-1.5) : la valeur moyenne du profil est lue par
 * rapport au repère de l'élément, de la matrice et de l'espèce, à condition d'être exprimée dans l'unité du repère
 * (sinon pas de lecture, l'écart est signalé). Validation par le technicien.
 */
class ProfilsService
{
    public function __construct(private readonly Referentiels $referentiels) {}

    /**
     * @param  array{date_prelevement: string, espece_code: string, categorie_code?: string|null, nb_animaux?: int|null, matrice: string, parametre: string, valeur_moyenne: float, valeur_min?: float|null, valeur_max?: float|null, unite: string, laboratoire_id?: int|null, interpretation_labo?: string|null, commentaire?: string|null}  $donnees
     */
    public function ajouter(Visite $visite, User $auteur, array $donnees): AnalyseAnimale
    {
        if (isset($donnees['valeur_min'], $donnees['valeur_max']) && $donnees['valeur_min'] > $donnees['valeur_max']) {
            throw new SaisieRefusee('La valeur minimale dépasse la valeur maximale.');
        }

        $profil = new AnalyseAnimale([...$donnees, 'elevage_id' => $visite->elevage_id, 'visite_id' => $visite->id]);
        $profil->lecture_code = $this->lecture($profil)?->value;
        $profil->save();

        return $profil;
    }

    public function valider(AnalyseAnimale $profil): AnalyseAnimale
    {
        $profil->update(['statut' => AnalyseAnimale::STATUT_VALIDEE]);

        return $profil;
    }

    public function supprimer(AnalyseAnimale $profil): void
    {
        $profil->delete();
    }

    /**
     * Profils de la visite, chacun avec son repère, sa lecture et sa source.
     *
     * @return list<array{profil: AnalyseAnimale, repere: string|null, lecture: LectureProfil|null, unite_differente: bool, source: array{source: string|null, statut: string, a_valider: bool}|null}>
     */
    public function lignes(Visite $visite): array
    {
        return AnalyseAnimale::query()->where('visite_id', $visite->id)->orderBy('date_prelevement')->orderBy('parametre')->get()
            ->map(function (AnalyseAnimale $profil) {
                $repere = $this->lecteur()->repere($profil->parametre, $profil->matrice, $profil->espece_code, $this->referentiels->reperesProfilsSanguins());

                return [
                    'profil' => $profil,
                    'repere' => $repere === null ? null : Affichage::bornes($repere->borneBasse, $repere->borneHaute, $repere->unite),
                    'lecture' => $profil->lecture_code === null ? null : LectureProfil::from($profil->lecture_code),
                    'unite_differente' => $repere !== null && $repere->unite !== $profil->unite,
                    'source' => $repere === null ? null : Affichage::source($repere->source),
                ];
            })->values()->all();
    }

    /**
     * Éléments disposant d'un repère, pour la saisie.
     *
     * @return array<string, string> élément => matrice
     */
    public function elementsReperes(): array
    {
        return DB::table('ref.repere_analyse_animale')->where('actif', true)->whereNull('version_fin')->orderBy('id')->pluck('matrice', 'element')->all();
    }

    private function lecture(AnalyseAnimale $profil): ?LectureProfil
    {
        $repere = $this->lecteur()->repere($profil->parametre, $profil->matrice, $profil->espece_code, $this->referentiels->reperesProfilsSanguins());

        if ($repere === null || $repere->unite !== $profil->unite || $profil->valeur_moyenne === null) {
            return null;
        }

        return $this->lecteur()->lire((float) $profil->valeur_moyenne, $repere);
    }

    private function lecteur(): LecteurProfilSanguin
    {
        return new LecteurProfilSanguin(
            (float) $this->referentiels->parametre('profil.part_carence_severe'),
            (float) $this->referentiels->parametre('profil.part_normal_bas'),
        );
    }
}
