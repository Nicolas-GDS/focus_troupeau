<?php

namespace App\Services\Dossier;

use App\Domain\Dossier\ClasseBilan;
use App\Domain\Dossier\PositionBilanSanitaire;
use App\Domain\Interpretation\EvenementSanitaire as EvenementDomaine;
use App\Domain\Referentiels\Referentiels;
use App\Models\Dossier\IndicateurSanitaire;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\EvenementSanitaire;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Bilan sanitaire (EF-M1-07, étape « collecter ») : saisie des indicateurs d'une campagne (en attendant l'import
 * SAGEMA du lot L7) et des événements sanitaires ; position de chaque indicateur par rapport aux seuils S1 / S2 de
 * l'espèce et du système de l'élevage (bovins : systeme_bovin).
 */
class BilanSanitaireService
{
    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly PositionBilanSanitaire $position = new PositionBilanSanitaire,
    ) {}

    /**
     * Enregistre un indicateur : en mode « taux », nombre de cas et dénominateur (vêlages), le taux est calculé ;
     * en mode « valeur », la valeur saisie (IVV en jours).
     */
    public function enregistrer(Elevage $elevage, string $campagne, string $indicateur, ?int $nbCas, ?int $denominateur, ?float $valeur): IndicateurSanitaire
    {
        $mode = DB::table('ref.indicateur_sanitaire')->where('code', $indicateur)->value('mode');

        if ($mode === null) {
            throw new SaisieRefusee("Indicateur inconnu : {$indicateur}.");
        }

        if ($mode === 'taux') {
            $valeur = PositionBilanSanitaire::taux($nbCas, $denominateur);

            if ($valeur === null || $nbCas > $denominateur) {
                throw new SaisieRefusee('Saisissez un nombre de cas et un nombre de vêlages cohérents.');
            }
        } elseif ($valeur === null) {
            throw new SaisieRefusee('Saisissez la valeur de l\'indicateur.');
        }

        return IndicateurSanitaire::query()->updateOrCreate(
            ['elevage_id' => $elevage->id, 'campagne_code' => $campagne, 'indicateur_code' => $indicateur],
            ['valeur' => $valeur, 'nb_cas' => $mode === 'taux' ? $nbCas : null, 'denominateur' => $mode === 'taux' ? $denominateur : null, 'source' => 'saisie'],
        );
    }

    /**
     * @param  array{espece_code: string, categorie_code?: string|null, date_evenement: string, type_evenement: string, nb_animaux?: int|null, gravite?: int|null, source?: string|null, description?: string|null}  $donnees
     */
    public function declarerEvenement(Elevage $elevage, array $donnees): EvenementSanitaire
    {
        if (! DB::table('ref.trouble_sanitaire')->where('code', $donnees['type_evenement'])->exists()) {
            throw new SaisieRefusee('Trouble inconnu.');
        }

        return EvenementSanitaire::query()->create([...$donnees, 'elevage_id' => $elevage->id]);
    }

    /**
     * Indicateurs de la campagne, avec la valeur de la campagne précédente, la classe, les seuils et leur source.
     *
     * @return list<array{code: string, libelle: string, unite: string, mode: string, indicateur: IndicateurSanitaire|null, valeur: float|null, precedente: float|null, classe: ClasseBilan|null, seuils: string|null, source: array{source: string|null, statut: string, a_valider: bool}|null}>
     */
    public function lignes(Elevage $elevage, string $campagne): array
    {
        $saisis = IndicateurSanitaire::query()->where('elevage_id', $elevage->id)->where('campagne_code', $campagne)->get()->keyBy('indicateur_code');
        $precedente = DB::table('fourrage.campagne')->where('date_fin', '<', DB::table('fourrage.campagne')->where('code', $campagne)->select('date_debut'))
            ->orderByDesc('date_fin')->value('code');
        $anciens = $precedente === null ? collect() : IndicateurSanitaire::query()->where('elevage_id', $elevage->id)
            ->where('campagne_code', $precedente)->pluck('valeur', 'indicateur_code');
        $seuils = $this->referentiels->seuilsBilanSanitaire();

        return DB::table('ref.indicateur_sanitaire')->orderBy('ordre')->get()->map(function ($ref) use ($saisis, $anciens, $seuils, $elevage) {
            $indicateur = $saisis[$ref->code] ?? null;
            $valeur = $indicateur?->valeur === null ? null : (float) $indicateur->valeur;
            $seuil = $this->position->seuil((string) $ref->code, 'BOV', $elevage->systeme_bovin, $seuils);

            return [
                'code' => (string) $ref->code,
                'libelle' => (string) $ref->libelle,
                'unite' => (string) $ref->unite,
                'mode' => (string) $ref->mode,
                'indicateur' => $indicateur,
                'valeur' => $valeur,
                'precedente' => isset($anciens[$ref->code]) ? (float) $anciens[$ref->code] : null,
                'classe' => $valeur === null || $seuil === null ? null : $this->position->classer($valeur, $seuil),
                'seuils' => $seuil === null ? null : 'S1 '.Affichage::nombre($seuil->s1).' / S2 '.Affichage::nombre($seuil->s2).' '.$ref->unite,
                'source' => $seuil === null ? null : Affichage::source($seuil->source),
            ];
        })->values()->all();
    }

    /**
     * Événements sanitaires de l'élevage, pour le croisement (EF-M2-05).
     *
     * @return list<EvenementDomaine>
     */
    public function evenements(Elevage $elevage): array
    {
        return EvenementSanitaire::query()->where('elevage_id', $elevage->id)->get()
            ->map(fn (EvenementSanitaire $e) => new EvenementDomaine($e->type_evenement, $e->espece_code,
                DateTimeImmutable::createFromInterface($e->date_evenement), $e->categorie_code))
            ->values()->all();
    }
}
