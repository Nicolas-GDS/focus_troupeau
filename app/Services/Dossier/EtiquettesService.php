<?php

namespace App\Services\Dossier;

use App\Domain\Dossier\ApportEtiquette;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Dossier\EtiquetteComposition;
use App\Models\Fourrage\Elevage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Étiquettes des minéraux, bolus, seaux et aliments distribués (EF-M1-06) : composition par kg de produit, dose
 * déclarée, apport journalier par élément (ApportEtiquette) ; la comparaison au besoin relève du lot L5.
 * Le libellé saisi par l'éleveur est affiché tel quel et n'entre dans aucun texte généré (EF-GEN-04).
 */
class EtiquettesService
{
    /**
     * @param  array{nature: string, libelle_eleveur: string, dose_g_par_animal_jour?: float|null, date_limite?: string|null, mode_distribution?: string|null}  $donnees
     * @param  array<string, array{valeur: float, unite: string}>  $composition  par code de paramètre
     */
    public function creer(Elevage $elevage, ?int $visiteId, array $donnees, array $composition): EtiquetteAliment
    {
        foreach ($composition as $code => $ligne) {
            if (! in_array($ligne['unite'], ApportEtiquette::unitesAdmises(), true)) {
                throw new SaisieRefusee("Unité non prise en charge pour {$code} : {$ligne['unite']}.");
            }
        }

        $inconnus = array_diff(array_keys($composition), DB::table('ref.parametre')->whereIn('code', array_keys($composition))->pluck('code')->all());
        if ($inconnus !== []) {
            throw new SaisieRefusee('Élément inconnu : '.implode(', ', $inconnus).'.');
        }

        return DB::transaction(function () use ($elevage, $visiteId, $donnees, $composition) {
            $etiquette = EtiquetteAliment::query()->create([...$donnees, 'elevage_id' => $elevage->id, 'visite_id' => $visiteId]);

            foreach ($composition as $code => $ligne) {
                EtiquetteComposition::query()->create([
                    'etiquette_id' => $etiquette->id, 'parametre_code' => $code, 'valeur' => $ligne['valeur'], 'unite' => $ligne['unite'],
                ]);
            }

            return $etiquette;
        });
    }

    public function valider(EtiquetteAliment $etiquette): EtiquetteAliment
    {
        $etiquette->update(['statut' => EtiquetteAliment::STATUT_VALIDEE]);

        return $etiquette;
    }

    public function supprimer(EtiquetteAliment $etiquette): void
    {
        $etiquette->delete();
    }

    /**
     * Composition et apport journalier par élément, avec le signalement d'une date limite dépassée à la date donnée.
     *
     * @return array{perimee: bool, composition: list<array{code: string, libelle: string, teneur: float, unite: string, apport: array{valeur: float, unite: string}|null}>}
     */
    public function lecture(EtiquetteAliment $etiquette, ?Carbon $aLaDate = null): array
    {
        $dose = $etiquette->dose_g_par_animal_jour === null ? null : (float) $etiquette->dose_g_par_animal_jour;
        $lignes = DB::table('dossier.etiquette_composition as c')->join('ref.parametre as p', 'p.code', '=', 'c.parametre_code')
            ->where('c.etiquette_id', $etiquette->id)->orderBy('p.ordre')->get(['c.parametre_code', 'c.valeur', 'c.unite', 'p.libelle']);

        return [
            'perimee' => $etiquette->date_limite !== null && $etiquette->date_limite->lt(($aLaDate ?? today())->startOfDay()),
            'composition' => $lignes->map(fn ($l) => [
                'code' => (string) $l->parametre_code,
                'libelle' => (string) $l->libelle,
                'teneur' => (float) $l->valeur,
                'unite' => (string) $l->unite,
                'apport' => $dose === null ? null : ApportEtiquette::apport((float) $l->valeur, (string) $l->unite, $dose),
            ])->values()->all(),
        ];
    }
}
