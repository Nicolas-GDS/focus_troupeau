<?php

namespace App\Services\Export;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Models\Stock\Inventaire;
use App\Services\Autonomie\PdfAutonomie;
use App\Services\Import\ImportBulletinService;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Exports de l'élevage (EF-M9-04, EF-M9-05, § 3.13 « Précisions du lot L7 ») :
 *
 * - rationnement : CSV « ; », virgule décimale, UTF-8 avec BOM, une ligne par lot analysé (dernière analyse non
 *   annulée), valeurs INRA 2018 et minéraux avec l'unité des bulletins ; format d'INRAtion V5 / Rumin'Al à confirmer ;
 * - données de l'élevage (RGPD, portabilité) : archive ZIP des fiches de visite publiées, des bulletins, du bilan
 *   d'autonomie et d'un CSV par famille de données.
 *
 * Un lecteur externe (éleveur) ne reçoit que les analyses et interprétations publiées (§ 6.3). Chaque export est
 * journalisé.
 */
class ExportsService
{
    /** Colonnes de l'export rationnement (codes de ref.parametre), dans l'ordre. */
    public const RATIONNEMENT = ['MS', 'MM', 'MAT', 'CB', 'NDF', 'ADF', 'AMI', 'UFL_2018', 'UFV_2018', 'PDI', 'BPR', 'P', 'CA', 'MG', 'K', 'NA', 'S', 'CL',
        'CU', 'ZN', 'MN', 'FE', 'SE', 'I', 'CO'];

    public function __construct(
        private readonly JournalService $journal,
        private readonly PdfAutonomie $pdfAutonomie,
    ) {}

    public function rationnement(Elevage $elevage, bool $publiesSeulement, User $auteur, ?string $campagne = null): string
    {
        $parametres = DB::table('ref.parametre')->whereIn('code', self::RATIONNEMENT)->get(['code', 'unite', 'decimales'])->keyBy('code');
        $colonnes = array_values(array_filter(self::RATIONNEMENT, fn ($c) => isset($parametres[$c])));
        $entete = ['lot', 'type_fourrage', 'campagne', 'date_prelevement', 'bulletin', ...array_map(fn ($c) => $c.' ('.$parametres[$c]->unite.')', $colonnes)];
        $lignes = [];
        foreach ($this->analyses($elevage, $publiesSeulement, $campagne) as $a) {
            $valeurs = DB::table('fourrage.resultat')->where('analyse_id', $a->analyse_id)->whereNotNull('valeur')->pluck('valeur', 'parametre_code');
            $lignes[] = [(string) ($a->nom_lot ?? $a->code), (string) $a->type, (string) $a->campagne_code, (string) $a->date_prelevement, (string) $a->num_echantillon_labo,
                ...array_map(fn ($c) => isset($valeurs[$c]) ? number_format((float) $valeurs[$c], (int) $parametres[$c]->decimales, ',', '') : '', $colonnes)];
        }
        $this->journal->ecrire('export_rationnement', $auteur->id, 'fourrage.elevage:'.$elevage->id, ['lots' => count($lignes)], $elevage->id);

        return "\xEF\xBB\xBF".$this->csv([$entete, ...$lignes]);
    }

    /** Archive ZIP des données de l'élevage ; renvoie le chemin d'un fichier temporaire. */
    public function donnees(Elevage $elevage, bool $publiesSeulement, User $auteur): string
    {
        $chemin = tempnam(sys_get_temp_dir(), 'ft-export-');
        if ($chemin === false) {
            throw new RuntimeException('Fichier temporaire impossible.');
        }
        $zip = new ZipArchive;
        if ($zip->open($chemin, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Archive impossible.');
        }
        $id = $elevage->id;
        $zip->addFromString('LISEZ-MOI.txt', $this->lisezMoi($elevage, $publiesSeulement));

        $tables = [
            'identite.csv' => DB::table('prive.elevage_identite')->where('elevage_id', $id)->get(),
            'elevage.csv' => DB::table('fourrage.elevage')->where('id', $id)->get(['id', 'code_pseudo', 'departement', 'code_insee_commune', 'especes', 'systeme_bovin', 'systeme_ovin',
                'systeme_caprin', 'effectif_bovins', 'effectif_ovins', 'effectif_caprins', 'adherent_gds', 'cree_le']),
            'conventions.csv' => DB::table('app.convention')->where('elevage_id', $id)->get(['type', 'date_debut', 'date_fin', 'statut', 'reference_externe']),
            'consentements.csv' => DB::table('app.consentement')->where('elevage_id', $id)->get(['type', 'veterinaire_user_id', 'accorde_le', 'revoque_le']),
            'visites.csv' => DB::table('dossier.visite')->where('elevage_id', $id)->when($publiesSeulement, fn ($q) => $q->where('statut', 'publiee'))->get(['id', 'campagne_code', 'date_visite', 'motif', 'statut']),
            'indicateurs_sanitaires.csv' => DB::table('dossier.indicateur_sanitaire')->where('elevage_id', $id)->get(['campagne_code', 'indicateur_code', 'valeur', 'nb_cas', 'denominateur', 'source']),
            'inventaires.csv' => DB::table('stock.inventaire')->where('elevage_id', $id)->get(['id', 'date_inventaire', 'type', 'date_debut', 'date_fin', 'marge_securite', 'statut']),
            'lots_stock.csv' => DB::table('stock.lot_stock')->where('elevage_id', $id)->get(['id', 'inventaire_id', 'libelle', 'famille_code', 'forme', 'ms_pct', 'ms_origine', 'pertes_pct', 'stock_utile_t_ms']),
            'mouvements.csv' => DB::table('stock.mouvement as m')->join('stock.lot_stock as l', 'l.id', '=', 'm.lot_stock_id')->where('l.elevage_id', $id)->get(['m.lot_stock_id', 'm.date_mouvement', 'm.type', 'm.quantite_t_ms', 'm.prix_eur', 'm.motif']),
            'lots_animaux.csv' => DB::table('plan.lot_animaux')->where('elevage_id', $id)->get(['id', 'inventaire_id', 'libelle', 'categorie_code', 'coeff_ugb', 'poids_vif_kg']),
            'scenarios.csv' => DB::table('plan.scenario as s')->join('stock.inventaire as i', 'i.id', '=', 's.inventaire_id')->where('i.elevage_id', $id)->get(['s.id', 's.inventaire_id', 's.libelle', 's.statut', 's.retenu_le']),
        ];
        foreach ($tables as $nom => $lignes) {
            $zip->addFromString('donnees/'.$nom, "\xEF\xBB\xBF".$this->csvObjets($lignes->all()));
        }
        $zip->addFromString('donnees/analyses.csv', $this->rationnement($elevage, $publiesSeulement, $auteur));
        $zip->addFromString('donnees/interpretations.csv', "\xEF\xBB\xBF".$this->csvObjets(DB::table('fourrage.interpretation as it')->join('fourrage.analyse as a', 'a.id', '=', 'it.analyse_id')
            ->join('fourrage.prelevement as p', 'p.id', '=', 'a.prelevement_id')->where('p.elevage_id', $id)
            ->when($publiesSeulement, fn ($q) => $q->where('it.statut', 'publie'))->get(['a.num_echantillon_labo', 'it.version', 'it.statut', 'it.niveau_global', 'it.synthese'])->all()));

        foreach ($this->analyses($elevage, $publiesSeulement, null) as $a) {
            if ($a->fichier_pdf && Storage::disk(ImportBulletinService::DISQUE)->exists($a->fichier_pdf)) {
                $zip->addFromString('bulletins/'.$a->num_echantillon_labo.'.pdf', (string) Storage::disk(ImportBulletinService::DISQUE)->get($a->fichier_pdf));
            }
        }
        $fiches = DB::table('dossier.interpretation_visite as iv')->join('dossier.visite as v', 'v.id', '=', 'iv.visite_id')->where('v.elevage_id', $id)
            ->where('iv.statut', 'publie')->get(['v.date_visite', 'iv.version', 'iv.fiche_pdf', 'iv.fiche_html']);
        foreach ($fiches as $f) {
            $base = 'fiches/visite-'.$f->date_visite.'-v'.$f->version;
            $disque = Storage::disk((string) config('fiches.disque'));
            if ($f->fiche_pdf && $disque->exists($f->fiche_pdf)) {
                $zip->addFromString($base.'.pdf', (string) $disque->get($f->fiche_pdf));
            } elseif ($f->fiche_html && $disque->exists($f->fiche_html)) {
                $zip->addFromString($base.'.html', (string) $disque->get($f->fiche_html));
            }
        }
        $inventaire = Inventaire::query()->withoutGlobalScopes()->where('elevage_id', $id)->where('statut', Inventaire::STATUT_COURANT)->first();
        if ($inventaire !== null) {
            $zip->addFromString('fiches/bilan-autonomie.pdf', $this->pdfAutonomie->bilan($inventaire));
        }
        $zip->close();
        $this->journal->ecrire('export_donnees', $auteur->id, 'fourrage.elevage:'.$id, ['publies_seulement' => $publiesSeulement], $id);

        return $chemin;
    }

    /** @return list<object> dernière analyse non annulée de chaque prélèvement (publiée pour un lecteur externe) */
    private function analyses(Elevage $elevage, bool $publiesSeulement, ?string $campagne): array
    {
        return DB::table('fourrage.prelevement as p')
            ->join('fourrage.analyse as a', 'a.prelevement_id', '=', 'p.id')
            ->leftJoin('ref.type_fourrage as t', 't.code', '=', 'p.type_fourrage_code')
            ->where('p.elevage_id', $elevage->id)->where('a.statut', '!=', 'annule')
            ->when($campagne, fn ($q, $c) => $q->where('p.campagne_code', $c))
            ->when($publiesSeulement, fn ($q) => $q->whereExists(fn ($i) => $i->from('fourrage.interpretation')->whereColumn('analyse_id', 'a.id')->where('statut', 'publie')))
            ->whereRaw('a.id = (select max(a2.id) from fourrage.analyse a2 where a2.prelevement_id = p.id and a2.statut <> ?)', ['annule'])
            ->orderBy('p.date_prelevement')->orderBy('p.id')
            ->get(['a.id as analyse_id', 'a.num_echantillon_labo', 'a.fichier_pdf', 'p.code', 'p.nom_lot', 'p.campagne_code', 'p.date_prelevement', 't.libelle as type'])->all();
    }

    private function lisezMoi(Elevage $elevage, bool $publiesSeulement): string
    {
        return implode("\n", [
            config('brand.nom').' — export des données de l\'élevage '.$elevage->code_pseudo.' du '.now()->format('d/m/Y H:i'),
            '',
            'Portabilité des données (RGPD, article 20 ; EF-M9-05).',
            'donnees/ : un fichier CSV par famille de données (séparateur « ; », encodage UTF-8).',
            'bulletins/ : bulletins d\'analyse des laboratoires.',
            'fiches/ : fiches de visite publiées et bilan d\'autonomie de l\'inventaire en cours.',
            $publiesSeulement ? 'Seuls les résultats et interprétations publiés figurent dans cet export.' : 'Export établi par un agent du GDS : résultats non publiés compris.',
        ])."\n";
    }

    /** @param  list<list<string>>  $lignes */
    private function csv(array $lignes): string
    {
        $flux = fopen('php://temp', 'r+');
        if ($flux === false) {
            throw new RuntimeException('Flux impossible.');
        }
        foreach ($lignes as $l) {
            fputcsv($flux, $l, ';', '"', '');
        }
        rewind($flux);

        return (string) stream_get_contents($flux);
    }

    /** @param  list<object>  $objets */
    private function csvObjets(array $objets): string
    {
        if ($objets === []) {
            return '';
        }
        $entete = array_keys((array) $objets[0]);

        return $this->csv([$entete, ...array_map(fn ($o) => array_map(fn ($v) => is_bool($v) ? ($v ? 'oui' : 'non') : (string) $v, array_values((array) $o)), $objets)]);
    }
}
