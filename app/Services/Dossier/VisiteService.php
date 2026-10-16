<?php

namespace App\Services\Dossier;

use App\Models\App\User;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Prelevement;
use Illuminate\Support\Facades\DB;

/**
 * Visites d'approche globale (EF-M1-09, étape « collecter ») : création, compte rendu, rattachement des prélèvements
 * de l'élevage. La campagne est déduite de la date de visite (fourrage.campagne).
 */
class VisiteService
{
    public function creer(Elevage $elevage, User $technicien, string $dateVisite, string $motif, ?string $motifDetail = null): Visite
    {
        return Visite::query()->create([
            'elevage_id' => $elevage->id,
            'campagne_code' => self::campagneDe($dateVisite),
            'date_visite' => $dateVisite,
            'technicien_id' => $technicien->id,
            'motif' => $motif,
            'motif_detail' => self::texte($motifDetail),
        ]);
    }

    public function mettreAJour(Visite $visite, string $dateVisite, string $motif, ?string $motifDetail, ?string $compteRendu): Visite
    {
        $visite->update([
            'date_visite' => $dateVisite,
            'campagne_code' => self::campagneDe($dateVisite),
            'motif' => $motif,
            'motif_detail' => self::texte($motifDetail),
            'compte_rendu' => self::texte($compteRendu),
        ]);

        return $visite;
    }

    /**
     * Rattache à la visite les prélèvements choisis (ceux de l'élevage seulement) et détache les autres.
     *
     * @param  list<int>  $prelevementIds
     */
    public function rattacherPrelevements(Visite $visite, array $prelevementIds): void
    {
        DB::transaction(function () use ($visite, $prelevementIds): void {
            $valides = Prelevement::query()->where('elevage_id', $visite->elevage_id)->whereIn('id', $prelevementIds)->pluck('id')->all();

            if (count($valides) !== count(array_unique($prelevementIds))) {
                throw new SaisieRefusee('Un prélèvement choisi n\'appartient pas à l\'élevage de la visite.');
            }

            Prelevement::query()->where('visite_id', $visite->id)->whereNotIn('id', $valides)->update(['visite_id' => null]);
            Prelevement::query()->whereIn('id', $valides)->update(['visite_id' => $visite->id]);
        });
    }

    public static function campagneDe(string $date): ?string
    {
        $code = DB::table('fourrage.campagne')->whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->value('code');

        return $code === null ? null : (string) $code;
    }

    private static function texte(?string $texte): ?string
    {
        $texte = $texte === null ? null : trim($texte);

        return $texte === '' ? null : $texte;
    }
}
