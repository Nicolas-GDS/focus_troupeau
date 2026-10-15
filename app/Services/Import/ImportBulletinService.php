<?php

namespace App\Services\Import;

use App\Domain\Interpretation\PlagePlausibilite;
use App\Domain\Interpretation\Plausibilite;
use App\Domain\Interpretation\Ratios;
use App\Domain\Interpretation\ValeurAnalysee;
use App\Domain\Referentiels\Referentiels;
use App\Jobs\ExtraireBulletinLano;
use App\Models\App\ImportBulletin;
use App\Models\App\User;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Prelevement;
use App\Notifications\Interpretation\BulletinEnAnomalieNotification;
use App\Services\JournalService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Import d'un bulletin de laboratoire (EF-M9-01, EF-M2-01, RC-2, § 6.4) :
 * dépôt (empreinte SHA-256, doublons) → extraction → rapprochement → analyse et résultats, ratios (RC-1),
 * contrôle de plausibilité (RC-2) → statut « controle » ou « anomalie » (technicien notifié, exclu des références).
 *
 * Rapprochement : par numéro d'échantillon (analyse « attendue »), sinon par numéro client du laboratoire
 * (prive.elevage_identite.num_client_labo) dans le GDS du dépôt — un prélèvement « à compléter » est alors créé —,
 * sinon le bulletin attend que le technicien désigne l'élevage (rapprocher()).
 */
class ImportBulletinService
{
    public const DISQUE = 'bulletins';

    public function __construct(
        private readonly ExtracteurBulletin $extracteur,
        private readonly CorrespondanceLano $correspondance,
        private readonly TypeFourrageLano $typeFourrage,
        private readonly Referentiels $referentiels,
        private readonly JournalService $journal,
    ) {}

    /**
     * Dépose un PDF et lance son extraction en file d'attente.
     */
    public function deposer(string $cheminTemporaire, string $nomFichier, int $gdsId, User $auteur): ImportBulletin
    {
        $empreinte = hash_file('sha256', $cheminTemporaire);
        $taille = filesize($cheminTemporaire);

        if ($empreinte === false || $taille === false) {
            throw new RuntimeException("Fichier illisible : {$nomFichier}");
        }

        $import = ImportBulletin::query()->create([
            'gds_id' => $gdsId,
            'depose_par' => $auteur->id,
            'nom_fichier' => $nomFichier,
            'hash_sha256' => $empreinte,
            'taille_octets' => $taille,
        ]);

        $existant = ImportBulletin::query()->withoutGlobalScopes()
            ->where('hash_sha256', $empreinte)
            ->whereKeyNot($import->id)
            ->whereNotIn('statut', ['doublon', 'erreur'])
            ->first();

        if ($existant !== null) {
            $import->update(['statut' => 'doublon', 'erreur' => "Fichier déjà déposé (import n° {$existant->id})."]);

            return $import;
        }

        $chemin = "{$gdsId}/a_rapprocher/{$empreinte}.pdf";
        Storage::disk(self::DISQUE)->putFileAs(dirname($chemin), $cheminTemporaire, basename($chemin));
        $import->update(['chemin' => $chemin]);

        $this->journal->ecrire('depot_bulletin', $auteur->id, 'app.import_bulletin:'.$import->id, ['fichier' => $nomFichier]);

        ExtraireBulletinLano::dispatch($import->id);

        return $import;
    }

    /**
     * Extraction puis import (exécuté par le job ExtraireBulletinLano).
     */
    public function traiter(ImportBulletin $import): ImportBulletin
    {
        $import->update(['statut' => 'en_extraction']);

        try {
            $donnees = $this->extracteur->extraire(Storage::disk(self::DISQUE)->path((string) $import->chemin));
        } catch (ExtractionImpossible $e) {
            $import->update(['statut' => 'erreur', 'erreur' => $e->getMessage()]);

            return $import;
        }

        $import->update(['donnees_extraites' => $donnees]);

        return $this->importer($import);
    }

    /**
     * Rapprochement automatique puis enregistrement ; à défaut, statut « a_rapprocher ».
     */
    public function importer(ImportBulletin $import): ImportBulletin
    {
        $entete = (array) ($import->donnees_extraites['entete'] ?? []);
        $numero = $entete['num_echantillon'] ?? null;

        if (! is_string($numero) || $numero === '') {
            $import->update(['statut' => 'erreur', 'erreur' => 'Numéro d\'échantillon introuvable sur le bulletin.']);

            return $import;
        }

        $analyse = Analyse::query()->withoutGlobalScopes()
            ->where('laboratoire_id', $this->laboratoireLano())
            ->where('num_echantillon_labo', $numero)
            ->first();

        if ($analyse !== null && $analyse->statut !== 'attendu') {
            $import->update(['statut' => 'doublon', 'erreur' => "Bulletin {$numero} déjà importé.", 'analyse_id' => $analyse->id]);

            return $import;
        }

        if ($analyse !== null) {
            $prelevement = Prelevement::query()->withoutGlobalScopes()->findOrFail($analyse->prelevement_id);

            return $this->enregistrer($import, $prelevement, $analyse);
        }

        $elevage = $this->elevageParNumeroClient($entete['client_lano'] ?? null, $import->gds_id);

        if ($elevage === null) {
            $import->update(['statut' => 'a_rapprocher', 'erreur' => null]);

            return $import;
        }

        return $this->enregistrer($import, $this->creerPrelevement($import, $elevage), null);
    }

    /**
     * Rapprochement manuel par le technicien : l'élevage doit appartenir au GDS du dépôt.
     */
    public function rapprocher(ImportBulletin $import, Elevage $elevage, User $technicien): ImportBulletin
    {
        if ($import->statut !== 'a_rapprocher') {
            throw new RuntimeException('Ce bulletin n\'est pas en attente de rapprochement.');
        }

        if ($elevage->gds_id !== $import->gds_id) {
            throw new RuntimeException('L\'élevage choisi n\'appartient pas au GDS du dépôt.');
        }

        $this->journal->ecrire('rapprochement_bulletin', $technicien->id, 'app.import_bulletin:'.$import->id, [], $elevage->id);

        return $this->enregistrer($import, $this->creerPrelevement($import, $elevage), null);
    }

    /**
     * Lève l'anomalie d'un bulletin après vérification (RC-2) : le motif est obligatoire, les drapeaux restent.
     */
    public function leverAnomalie(Analyse $analyse, string $motif, User $technicien): void
    {
        if ($analyse->statut !== Analyse::STATUT_ANOMALIE || trim($motif) === '') {
            throw new RuntimeException('Levée impossible : bulletin non en anomalie ou motif manquant.');
        }

        $analyse->update([
            'statut' => Analyse::STATUT_CONTROLE,
            'exclu_references' => false,
            'motif_exclusion' => null,
            'anomalie_levee_le' => now(),
            'anomalie_levee_par' => $technicien->id,
            'anomalie_motif_levee' => $motif,
        ]);

        $this->journal->ecrire('levee_anomalie', $technicien->id, 'fourrage.analyse:'.$analyse->id, ['motif' => $motif]);
    }

    /**
     * Annule un bulletin (RC-2 : « ou annule le bulletin ») ; rien n'est supprimé.
     */
    public function annuler(Analyse $analyse, string $motif, User $technicien): void
    {
        if (trim($motif) === '') {
            throw new RuntimeException('Le motif d\'annulation est obligatoire.');
        }

        $analyse->update(['statut' => Analyse::STATUT_ANNULE, 'exclu_references' => true, 'motif_exclusion' => $motif]);

        $this->journal->ecrire('annulation_bulletin', $technicien->id, 'fourrage.analyse:'.$analyse->id, ['motif' => $motif]);
    }

    private function enregistrer(ImportBulletin $import, Prelevement $prelevement, ?Analyse $analyse): ImportBulletin
    {
        $donnees = (array) $import->donnees_extraites;
        $entete = (array) ($donnees['entete'] ?? []);
        ['resultats' => $resultats, 'avertissements' => $avertissements] = $this->correspondance->traduire((array) ($donnees['resultats'] ?? []));

        $import = DB::transaction(function () use ($import, $prelevement, $analyse, $entete, $donnees, $resultats, $avertissements) {
            $numero = (string) $entete['num_echantillon'];
            $chemin = "{$import->gds_id}/{$prelevement->elevage_id}/{$numero}.pdf";

            if ($import->chemin !== null && $import->chemin !== $chemin) {
                Storage::disk(self::DISQUE)->move($import->chemin, $chemin);
            }

            $analyse ??= new Analyse;
            $analyse->fill([
                'prelevement_id' => $prelevement->id,
                'laboratoire_id' => $this->laboratoireLano(),
                'num_echantillon_labo' => $numero,
                'code_dossier_labo' => $entete['code_dossier'] ?? null,
                'type_bulletin' => (string) ($donnees['famille_rapport'] ?? ''),
                'date_reception' => self::date($entete['date_reception'] ?? null),
                'date_edition' => self::date($entete['date_edition'] ?? null),
                'fichier_pdf' => $chemin,
                'hash_pdf' => $import->hash_sha256,
                'statut' => Analyse::STATUT_RECU,
            ])->save();

            $valeurs = array_map(fn (ResultatExtrait $r) => new ValeurAnalysee($r->parametreCode, $r->valeur, $r->censure), $resultats);
            $presents = array_map(fn (ResultatExtrait $r) => $r->parametreCode, $resultats);
            $ratios = array_values(array_filter((new Ratios)->calculer($valeurs), fn ($r) => ! in_array($r->parametreCode, $presents, true)));

            $famille = $this->referentiels->familleDuType($prelevement->type_fourrage_code);
            $anomalies = (new Plausibilite)->controler(array_merge($valeurs, $ratios), $famille, $this->referentiels->plagesPlausibiliteActives());
            $horsPlage = array_map(fn ($a) => $a['valeur']->parametreCode, $anomalies);

            DB::table('fourrage.resultat')->where('analyse_id', $analyse->id)->delete();
            foreach ($resultats as $r) {
                DB::table('fourrage.resultat')->insert([
                    'analyse_id' => $analyse->id, 'parametre_code' => $r->parametreCode, 'valeur' => $r->valeur,
                    'censure' => $r->censure, 'unite' => $r->unite, 'origine' => 'labo',
                    'flag_anomalie' => in_array($r->parametreCode, $horsPlage, true),
                ]);
            }
            foreach ($ratios as $r) {
                DB::table('fourrage.resultat')->insert([
                    'analyse_id' => $analyse->id, 'parametre_code' => $r->parametreCode, 'valeur' => $r->valeur,
                    'unite' => (string) DB::table('ref.parametre')->where('code', $r->parametreCode)->value('unite'),
                    'origine' => 'calcul_gds', 'flag_anomalie' => in_array($r->parametreCode, $horsPlage, true),
                ]);
            }

            $enAnomalie = $anomalies !== [];
            $analyse->update([
                'statut' => $enAnomalie ? Analyse::STATUT_ANOMALIE : Analyse::STATUT_CONTROLE,
                'anomalie_detail' => $enAnomalie ? self::detailAnomalies($anomalies) : null,
                'exclu_references' => $enAnomalie,
                'motif_exclusion' => $enAnomalie ? 'Bulletin en anomalie (RC-2)' : null,
            ]);

            $import->update([
                'statut' => $enAnomalie ? 'anomalie' : 'importe',
                'chemin' => $chemin,
                'analyse_id' => $analyse->id,
                'erreur' => $avertissements === [] ? null : implode("\n", $avertissements),
            ]);

            $this->journal->ecrire('import_bulletin', $import->depose_par, 'fourrage.analyse:'.$analyse->id, [
                'statut' => $analyse->statut, 'resultats' => count($resultats), 'ratios' => count($ratios), 'hors_plage' => $horsPlage,
            ], $prelevement->elevage_id);

            return $import;
        });

        if ($import->statut === 'anomalie') {
            $this->notifierAnomalie($import);
        }

        return $import;
    }

    private function creerPrelevement(ImportBulletin $import, Elevage $elevage): Prelevement
    {
        $donnees = (array) $import->donnees_extraites;
        $entete = (array) ($donnees['entete'] ?? []);
        $type = $this->typeFourrage->deduire((string) ($donnees['famille_rapport'] ?? ''), $entete);
        $date = self::date($entete['date_prelevement'] ?? null) ?? today();

        return Prelevement::query()->create([
            'elevage_id' => $elevage->id,
            'campagne_code' => DB::table('fourrage.campagne')->whereDate('date_debut', '<=', $date)->whereDate('date_fin', '>=', $date)->value('code'),
            'date_prelevement' => $date,
            'type_fourrage_code' => $type['type'],
            'nom_lot' => $entete['nom_echantillon'] ?? null,
            'date_recolte' => self::date($entete['date_recolte'] ?? null),
            'a_completer' => true,
            'commentaire' => $type['certain'] ? null : 'Type de fourrage à confirmer (déduction incertaine à l\'import).',
        ]);
    }

    private function elevageParNumeroClient(mixed $numeroClient, int $gdsId): ?Elevage
    {
        if (! is_string($numeroClient) || $numeroClient === '') {
            return null;
        }

        $ids = DB::table('prive.elevage_identite')->where('num_client_labo', $numeroClient)->pluck('elevage_id');

        return Elevage::query()->withoutGlobalScopes()->whereIn('id', $ids)->where('gds_id', $gdsId)->first();
    }

    private function notifierAnomalie(ImportBulletin $import): void
    {
        $techniciens = User::role('technicien')
            ->where('actif', true)
            ->whereHas('gds', fn ($q) => $q->where('id', $import->gds_id))
            ->get();

        foreach ($techniciens as $technicien) {
            $technicien->notify(new BulletinEnAnomalieNotification($import));

            DB::table('app.notification')->insert([
                'user_id' => $technicien->id,
                'canal' => 'email',
                'type' => 'bulletin_anomalie',
                'objet' => 'Bulletin en anomalie : '.$import->nom_fichier,
                'reference_objet' => 'fourrage.analyse:'.$import->analyse_id,
                'statut' => 'envoye',
                'envoye_le' => now(),
            ]);
        }
    }

    private function laboratoireLano(): int
    {
        return (int) DB::table('ref.laboratoire')->where('code', 'LANO')->value('id');
    }

    /**
     * @param  list<array{valeur: ValeurAnalysee, plage: PlagePlausibilite}>  $anomalies
     */
    private static function detailAnomalies(array $anomalies): string
    {
        return implode(' ; ', array_map(function (array $a) {
            $plage = $a['plage'];
            $bornes = ($plage->borneMin ?? '−∞').' – '.($plage->borneMax ?? '+∞');

            return "{$a['valeur']->parametreCode} = ".($a['valeur']->censure ?? '').$a['valeur']->valeur." hors de la plage plausible [{$bornes}]";
        }, $anomalies));
    }

    private static function date(mixed $texte): ?Carbon
    {
        return is_string($texte) && preg_match('#^\d{2}/\d{2}/\d{4}$#', $texte) ? Carbon::createFromFormat('d/m/Y', $texte)?->startOfDay() : null;
    }
}
