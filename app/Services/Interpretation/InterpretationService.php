<?php

namespace App\Services\Interpretation;

use App\Domain\Interpretation\Classement;
use App\Domain\Interpretation\Classeur;
use App\Domain\Interpretation\CompatibiliteEvenements;
use App\Domain\Interpretation\ContexteLot;
use App\Domain\Interpretation\EvenementSanitaire;
use App\Domain\Interpretation\NiveauGlobal;
use App\Domain\Interpretation\NiveauPropose;
use App\Domain\Interpretation\Seuil;
use App\Domain\Interpretation\ValeurAnalysee;
use App\Domain\Referentiels\Referentiels;
use App\Domain\Referentiels\StatutEpistemique;
use App\Models\App\User;
use App\Models\Fourrage\Alerte;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\EvenementSanitaire as EvenementSanitaireModele;
use App\Models\Fourrage\Interpretation;
use App\Models\Fourrage\Prelevement;
use App\Services\Cloisonnement\Perimetre;
use App\Services\JournalService;
use App\Services\Notifications\NotificationsPublication;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Interprétation d'un bulletin (E20 ; EF-M2-02, EF-M2-03, EF-M2-04 ; RC-1, RC-1.4).
 *
 * Circuit : brouillon → valide → (visee, niveau 3) → publie. Seul le brouillon se modifie ; une interprétation
 * validée, visée ou publiée est figée (une nouvelle version relèvera du lot L3, EF-M2-07).
 *
 * Les calculs passent par le domaine (Classeur, CompatibiliteEvenements, NiveauGlobal) ; seuils, paramètres et liens
 * viennent des référentiels. L'autorisation (rôle, périmètre) relève de InterpretationPolicy, appelée par l'écran ;
 * ce service garde les invariants du circuit, quel que soit l'appelant. Les lectures internes portent sur des objets
 * déjà autorisés : elles se font sans les scopes de cloisonnement, pour fonctionner aussi hors requête web.
 */
class InterpretationService
{
    /** Nombre maximal de lignes de la synthèse (EF-M2-03 : « 3 à 5 lignes »). */
    private const LIGNES_SYNTHESE_MAX = 5;

    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly JournalService $journal,
    ) {}

    /**
     * Interprétation en cours d'une analyse : la dernière version non archivée.
     */
    public function courante(Analyse $analyse): ?Interpretation
    {
        return Interpretation::query()->withoutGlobalScopes()
            ->where('analyse_id', $analyse->id)
            ->where('statut', '!=', Interpretation::STATUT_ARCHIVE)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Dernière version publiée d'une analyse : celle que lisent l'éleveur et le vétérinaire d'élevage (§ 2.3).
     */
    public function publiee(Analyse $analyse): ?Interpretation
    {
        return Interpretation::query()->withoutGlobalScopes()
            ->where('analyse_id', $analyse->id)
            ->where('statut', Interpretation::STATUT_PUBLIE)
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Crée le brouillon d'un bulletin contrôlé (EF-M2-02) : alertes automatiques de la grille active, niveau proposé.
     * La catégorie cible reprend, à défaut, la destination du prélèvement.
     */
    public function creerBrouillon(Analyse $analyse, User $technicien, ?string $categorieCible = null): Interpretation
    {
        if ($analyse->statut !== Analyse::STATUT_CONTROLE) {
            throw new InterpretationRefusee('Seul un bulletin contrôlé peut être interprété (statut actuel : '.$analyse->statut.').');
        }

        if ($this->courante($analyse) !== null) {
            throw new InterpretationRefusee('Ce bulletin a déjà une interprétation en cours.');
        }

        $prelevement = $this->prelevement($analyse);
        $categorieCible = $this->categorieValide($categorieCible ?? $prelevement->destination_categorie_code);

        return DB::transaction(function () use ($analyse, $technicien, $categorieCible) {
            $version = (int) DB::table('fourrage.interpretation')->where('analyse_id', $analyse->id)->max('version');

            $interpretation = Interpretation::query()->create([
                'analyse_id' => $analyse->id,
                'version' => $version + 1,
                'date_interpretation' => today(),
                'technicien_id' => $this->personneDe($technicien),
                'categorie_cible_code' => $categorieCible,
                'statut' => Interpretation::STATUT_BROUILLON,
                'version_grille' => $this->referentiels->versionGrilleCourante(),
            ]);

            $this->genererAlertesAutomatiques($interpretation);
            $this->recalculerNiveauPropose($interpretation);

            return $interpretation->refresh();
        });
    }

    /**
     * Change la catégorie cible d'un brouillon et reclasse le bulletin (RC-1 : la catégorie précise la grille).
     * Les alertes automatiques sont recalculées — l'écart motivé d'une alerte qui subsiste est conservé —, les alertes
     * ajoutées par le technicien restent.
     */
    public function changerCategorieCible(Interpretation $interpretation, ?string $categorieCible): Interpretation
    {
        $this->exigerBrouillon($interpretation);
        $categorieCible = $this->categorieValide($categorieCible);

        return DB::transaction(function () use ($interpretation, $categorieCible) {
            $interpretation->update([
                'categorie_cible_code' => $categorieCible,
                'version_grille' => $this->referentiels->versionGrilleCourante(),
            ]);
            $this->genererAlertesAutomatiques($interpretation);
            $this->recalculerNiveauPropose($interpretation);

            return $interpretation->refresh();
        });
    }

    public function retenirAlerte(Alerte $alerte): void
    {
        $interpretation = $this->interpretationDe($alerte);
        $this->exigerBrouillon($interpretation);

        $alerte->update(['retenue' => true, 'motif_ecart' => null]);
        $this->recalculerNiveauPropose($interpretation);
    }

    /**
     * Écarte une alerte (EF-M2-03 : « avec motif ») ; le niveau proposé ne compte plus sa gravité (RC-1.4).
     */
    public function ecarterAlerte(Alerte $alerte, string $motif): void
    {
        $interpretation = $this->interpretationDe($alerte);
        $this->exigerBrouillon($interpretation);

        if (trim($motif) === '') {
            throw new InterpretationRefusee('Le motif est obligatoire pour écarter une alerte.');
        }

        $alerte->update(['retenue' => false, 'motif_ecart' => trim($motif)]);
        $this->recalculerNiveauPropose($interpretation);
    }

    /**
     * Ajoute une alerte du technicien (origine « technicien », retenue). Faute de seuil, elle n'entre pas dans la
     * recherche d'événements compatibles (RC-1.4 s'appuie sur le sens du seuil classant), mais sa gravité compte.
     */
    public function ajouterAlerte(Interpretation $interpretation, ?string $parametreCode, string $classe, int $gravite, string $message, ?float $valeur = null): Alerte
    {
        $this->exigerBrouillon($interpretation);

        if ($gravite < 0 || $gravite > 3 || trim($classe) === '' || trim($message) === '') {
            throw new InterpretationRefusee('Une alerte demande une classe, une gravité de 0 à 3 et un message.');
        }

        if ($parametreCode !== null && ! DB::table('ref.parametre')->where('code', $parametreCode)->exists()) {
            throw new InterpretationRefusee('Paramètre inconnu : '.$parametreCode.'.');
        }

        $alerte = Alerte::query()->create([
            'interpretation_id' => $interpretation->id,
            'parametre_code' => $parametreCode,
            'valeur' => $valeur,
            'classe' => trim($classe),
            'gravite' => $gravite,
            'message' => trim($message),
            'origine' => 'technicien',
            'retenue' => true,
        ]);
        $this->recalculerNiveauPropose($interpretation);

        return $alerte;
    }

    /**
     * Enregistre la rédaction du brouillon (EF-M2-03). Le niveau proposé n'est jamais écrasé par le niveau retenu.
     */
    public function enregistrer(Interpretation $interpretation, RedactionInterpretation $redaction): Interpretation
    {
        $this->exigerBrouillon($interpretation);

        if ($redaction->niveauRetenu !== null && ($redaction->niveauRetenu < 0 || $redaction->niveauRetenu > 3)) {
            throw new InterpretationRefusee('Le niveau retenu va de 0 à 3.');
        }

        $deconseillees = array_values(array_unique($redaction->utilisationDeconseillee));
        $connues = DB::table('ref.categorie_animale')->whereIn('code', $deconseillees)->count();
        if ($connues !== count($deconseillees)) {
            throw new InterpretationRefusee('Catégorie animale inconnue dans l\'utilisation déconseillée.');
        }

        if (self::nombreDeLignes($redaction->synthese) > self::LIGNES_SYNTHESE_MAX) {
            throw new InterpretationRefusee('La synthèse tient en '.self::LIGNES_SYNTHESE_MAX.' lignes au plus.');
        }

        $interpretation->update([
            'lecture_sanitaire' => self::texte($redaction->lectureConservation),
            'lecture_mineraux' => self::texte($redaction->lectureMineraux),
            'lecture_nutritionnelle' => self::texte($redaction->lectureNutritionnelle),
            'synthese' => self::texte($redaction->synthese),
            'recommandations' => self::texte($redaction->recommandations),
            'utilisation_conseillee' => self::texte($redaction->utilisationConseillee),
            'utilisation_deconseillee' => self::versTableau($deconseillees),
            'niveau_global' => $redaction->niveauRetenu,
            'orientation_veterinaire' => $redaction->orientationVeterinaire,
        ]);

        return $interpretation->refresh();
    }

    /**
     * brouillon → valide : synthèse et niveau retenu obligatoires ; le bulletin passe au statut « valide ».
     */
    public function valider(Interpretation $interpretation, User $technicien): Interpretation
    {
        $this->exigerBrouillon($interpretation);

        if (self::texte($interpretation->synthese) === null) {
            throw new InterpretationRefusee('La synthèse est obligatoire pour valider l\'interprétation.');
        }

        if ($interpretation->niveau_global === null) {
            throw new InterpretationRefusee('Le niveau global retenu est obligatoire pour valider l\'interprétation.');
        }

        DB::transaction(function () use ($interpretation, $technicien) {
            $interpretation->update(['statut' => Interpretation::STATUT_VALIDE]);
            Analyse::query()->withoutGlobalScopes()->whereKey($interpretation->analyse_id)->update(['statut' => Analyse::STATUT_VALIDE]);

            $this->journaliser('validation_interpretation', $technicien, $interpretation);
        });

        return $interpretation->refresh();
    }

    /**
     * valide → visee : visa du vétérinaire conseil, réservé au niveau 3 (EF-M2-04).
     */
    public function viser(Interpretation $interpretation, User $veterinaire): Interpretation
    {
        if ($interpretation->statut !== Interpretation::STATUT_VALIDE || $interpretation->niveau_global !== 3) {
            throw new InterpretationRefusee('Seule une interprétation validée de niveau 3 se vise.');
        }

        DB::transaction(function () use ($interpretation, $veterinaire) {
            $interpretation->update(['statut' => Interpretation::STATUT_VISEE, 'vise_par' => $veterinaire->id, 'vise_le' => now()]);

            $this->journaliser('visa_interpretation', $veterinaire, $interpretation);
        });

        return $interpretation->refresh();
    }

    /**
     * Refus de visa par le vétérinaire conseil (EF-M2-04, décision du 15/10/2026) : l'interprétation validée de niveau 3
     * revient en brouillon avec le motif, pour que le technicien la reprenne ; l'analyse redevient « controle ».
     */
    public function refuserVisa(Interpretation $interpretation, string $motif, User $veterinaire): Interpretation
    {
        if ($interpretation->statut !== Interpretation::STATUT_VALIDE || $interpretation->niveau_global !== 3) {
            throw new InterpretationRefusee('Seule une interprétation validée de niveau 3 peut voir son visa refusé.');
        }

        if (self::texte($motif) === null) {
            throw new InterpretationRefusee('Le motif du refus de visa est obligatoire.');
        }

        DB::transaction(function () use ($interpretation, $motif, $veterinaire) {
            $interpretation->update([
                'statut' => Interpretation::STATUT_BROUILLON,
                'refus_visa_motif' => trim($motif),
                'refus_visa_par' => $veterinaire->id,
                'refus_visa_le' => now(),
            ]);
            Analyse::query()->withoutGlobalScopes()->whereKey($interpretation->analyse_id)->update(['statut' => Analyse::STATUT_CONTROLE]);

            $this->journaliser('refus_visa_interpretation', $veterinaire, $interpretation);
        });

        return $interpretation->refresh();
    }

    /**
     * Publication (EF-M2-04, T-05) : niveaux 0 à 2 depuis « valide » ; niveau 3 seulement depuis « visee ».
     *
     * Lot L3 : l'éleveur et le technicien sont notifiés (EF-M8-01 ; SMS au niveau 3, EF-M8-02), sauf quand le lot est
     * publié avec sa visite, dont la notification le couvre ; la fiche figée est celle de la visite (EF-M2-07).
     */
    public function publier(Interpretation $interpretation, User $technicien, bool $notifier = true): Interpretation
    {
        if (! self::publiable($interpretation)) {
            throw new InterpretationRefusee($interpretation->niveau_global === 3
                ? 'Une interprétation de niveau 3 ne se publie qu\'après le visa du vétérinaire conseil.'
                : 'Seule une interprétation validée se publie.');
        }

        DB::transaction(function () use ($interpretation, $technicien, $notifier) {
            $interpretation->update(['statut' => Interpretation::STATUT_PUBLIE]);
            app(Perimetre::class)->oublier(); // la règle de statut change pour l'éleveur

            $this->journaliser('publication_interpretation', $technicien, $interpretation);

            if ($notifier) {
                app(NotificationsPublication::class)->resultatPublie($interpretation, $technicien);
            }
        });

        return $interpretation->refresh();
    }

    /** Règle de publication d'EF-M2-04, partagée avec la policy. */
    public static function publiable(Interpretation $interpretation): bool
    {
        $niveau = $interpretation->niveau_global;

        return ($niveau !== null && $niveau <= 2 && $interpretation->statut === Interpretation::STATUT_VALIDE)
            || ($niveau === 3 && $interpretation->statut === Interpretation::STATUT_VISEE);
    }

    /**
     * Résultats du bulletin, chacun avec sa classe, son repère et sa source (EF-M2-02, EF-GEN-02, EF-GEN-03),
     * classés pour la catégorie cible donnée.
     *
     * @return list<array{code: string, libelle: string, valeur: float|null, censure: string|null, unite: string, decimales: int, calcule: bool, anomalie: bool, classe: string|null, gravite: int|null, repere: string|null, source: string|null, statut: StatutEpistemique|null, a_valider: bool}>
     */
    public function lignesResultats(Analyse $analyse, ?string $categorieCible): array
    {
        $lot = $this->contexte($this->prelevement($analyse), $categorieCible);
        $seuils = $this->referentiels->seuilsActifs();
        $classeur = new Classeur;

        $resultats = DB::table('fourrage.resultat as r')
            ->join('ref.parametre as p', 'p.code', '=', 'r.parametre_code')
            ->where('r.analyse_id', $analyse->id)
            ->orderBy('p.ordre')->orderBy('p.code')
            ->get(['r.parametre_code', 'r.valeur', 'r.censure', 'r.unite', 'r.origine', 'r.flag_anomalie', 'p.libelle', 'p.decimales']);

        $classements = [];
        foreach ($resultats as $r) {
            if ($r->valeur !== null) {
                $classements[$r->parametre_code] = $classeur->classer(
                    new ValeurAnalysee((string) $r->parametre_code, (float) $r->valeur, $r->censure === null ? null : (string) $r->censure),
                    $lot,
                    $seuils,
                );
            }
        }

        $sources = $this->sources(array_map(fn (Classement $c) => $c->seuil->referenceId, array_filter($classements)));

        $lignes = [];
        foreach ($resultats as $r) {
            $seuil = ($classements[$r->parametre_code] ?? null)?->seuil;
            $lignes[] = [
                'code' => (string) $r->parametre_code,
                'libelle' => (string) $r->libelle,
                'valeur' => $r->valeur === null ? null : (float) $r->valeur,
                'censure' => $r->censure === null ? null : (string) $r->censure,
                'unite' => (string) $r->unite,
                'decimales' => (int) $r->decimales,
                'calcule' => $r->origine === 'calcul_gds',
                'anomalie' => (bool) $r->flag_anomalie,
                'classe' => $seuil?->classe,
                'gravite' => $seuil?->gravite,
                'repere' => $seuil === null ? null : self::repere($seuil->borneMin, $seuil->borneMax, (string) $r->unite),
                'source' => $seuil?->referenceId === null ? null : ($sources[$seuil->referenceId] ?? null),
                'statut' => $seuil?->statutEpistemique,
                'a_valider' => $seuil !== null && ! $seuil->valide,
            ];
        }

        return $lignes;
    }

    /**
     * Alertes de l'interprétation, avec le repère et la source de leur seuil (EF-M2-02 : « avec le seuil, la source
     * et le message proposé »).
     *
     * @return list<array{alerte: Alerte, repere: string|null, source: string|null, statut: StatutEpistemique|null}>
     */
    public function lignesAlertes(Interpretation $interpretation): array
    {
        $alertes = Alerte::query()->withoutGlobalScopes()
            ->where('interpretation_id', $interpretation->id)
            ->orderByDesc('gravite')->orderBy('id')
            ->get();

        $seuils = DB::table('ref.seuil_interpretation')->whereIn('id', $alertes->pluck('seuil_id')->filter())->get()->keyBy('id');
        $unites = DB::table('ref.parametre')->whereIn('code', $alertes->pluck('parametre_code')->filter())->pluck('unite', 'code');
        $sources = $this->sources($seuils->pluck('reference_id')->filter()->map(fn ($id) => (int) $id)->all());

        return $alertes->map(function (Alerte $alerte) use ($seuils, $unites, $sources) {
            $seuil = $alerte->seuil_id === null ? null : $seuils->get($alerte->seuil_id);

            return [
                'alerte' => $alerte,
                'repere' => $seuil === null ? null : self::repere(
                    $seuil->borne_min === null ? null : (float) $seuil->borne_min,
                    $seuil->borne_max === null ? null : (float) $seuil->borne_max,
                    (string) ($unites[$alerte->parametre_code] ?? ''),
                ),
                'source' => $seuil?->reference_id === null ? null : ($sources[(int) $seuil->reference_id] ?? null),
                'statut' => $seuil === null ? null : StatutEpistemique::from((string) $seuil->statut_epistemique),
            ];
        })->values()->all();
    }

    /**
     * Motifs des rehaussements du niveau proposé (RC-1.4), tels que conservés à son dernier calcul.
     *
     * @return list<string>
     */
    public function motifsRehaussement(Interpretation $interpretation): array
    {
        return self::depuisTableau($interpretation->motifs_rehaussement);
    }

    /**
     * Codes des catégories à qui le lot est déconseillé.
     *
     * @return list<string>
     */
    public function utilisationDeconseillee(Interpretation $interpretation): array
    {
        return self::depuisTableau($interpretation->utilisation_deconseillee);
    }

    /**
     * Repère d'un seuil [borneMin ; borneMax[ en clair, dans l'unité du paramètre.
     */
    public static function repere(?float $borneMin, ?float $borneMax, string $unite): string
    {
        $unite = $unite === '' || $unite === '-' ? '' : ' '.$unite;

        return match (true) {
            $borneMin === null && $borneMax === null => 'toutes valeurs',
            $borneMin === null => 'moins de '.self::nombre((float) $borneMax).$unite,
            $borneMax === null => self::nombre($borneMin).$unite.' et plus',
            default => 'de '.self::nombre($borneMin).' à moins de '.self::nombre($borneMax).$unite,
        };
    }

    public static function nombre(float $valeur, ?int $decimales = null): string
    {
        if ($decimales !== null) {
            return number_format($valeur, $decimales, ',', ' ');
        }

        $texte = rtrim(rtrim(sprintf('%.4F', $valeur), '0'), '.');

        return str_replace('.', ',', $texte);
    }

    /**
     * Recalcule les alertes automatiques (RC-1) pour la catégorie cible courante. Les seuils de gravité ≥ 1 produisent
     * une alerte retenue, sauf si le technicien avait écarté, avec motif, l'alerte du même seuil.
     */
    private function genererAlertesAutomatiques(Interpretation $interpretation): void
    {
        $analyse = Analyse::query()->withoutGlobalScopes()->findOrFail($interpretation->analyse_id);
        $lot = $this->contexte($this->prelevement($analyse), $interpretation->categorie_cible_code);

        $ecarts = Alerte::query()->withoutGlobalScopes()
            ->where('interpretation_id', $interpretation->id)
            ->where('origine', 'automatique')
            ->where('retenue', false)
            ->whereNotNull('seuil_id')
            ->pluck('motif_ecart', 'seuil_id');

        Alerte::query()->withoutGlobalScopes()->where('interpretation_id', $interpretation->id)->where('origine', 'automatique')->delete();

        $classements = (new Classeur)->classerTout($this->valeurs($analyse), $lot, $this->referentiels->seuilsActifs());

        foreach ($classements as $classement) {
            if (! $classement->produitUneAlerte()) {
                continue;
            }

            $seuil = $classement->seuil;
            $ecarte = $ecarts->has($seuil->id);

            Alerte::query()->create([
                'interpretation_id' => $interpretation->id,
                'parametre_code' => $classement->valeur->parametreCode,
                'seuil_id' => $seuil->id,
                'valeur' => $classement->valeur->valeur,
                'censure' => $classement->valeur->censure,
                'classe' => $seuil->classe,
                'gravite' => $seuil->gravite,
                'message' => $seuil->message ?? $classement->valeur->parametreCode.' : '.$seuil->classe,
                'origine' => 'automatique',
                'retenue' => ! $ecarte,
                'motif_ecart' => $ecarte ? $ecarts->get($seuil->id) : null,
            ]);
        }
    }

    /**
     * RC-1.4 : niveau proposé à partir des alertes retenues, des événements sanitaires de l'élevage (fenêtre et force
     * minimale lues dans les paramètres) et du seuil cuivre propre aux ovins lu dans la grille.
     */
    private function recalculerNiveauPropose(Interpretation $interpretation): NiveauPropose
    {
        $analyse = Analyse::query()->withoutGlobalScopes()->findOrFail($interpretation->analyse_id);
        $prelevement = $this->prelevement($analyse);
        $lot = $this->contexte($prelevement, $interpretation->categorie_cible_code);
        $gdsId = (int) DB::table('fourrage.elevage')->where('id', $prelevement->elevage_id)->value('gds_id');

        $retenues = Alerte::query()->withoutGlobalScopes()
            ->where('interpretation_id', $interpretation->id)
            ->where('retenue', true)
            ->get();

        $seuils = [];
        foreach ($this->referentiels->seuilsActifs() as $seuil) {
            $seuils[$seuil->id] = $seuil;
        }

        // Alertes automatiques retenues, rattachées à leur seuil : seules elles portent un sens (bas / haut).
        $classements = [];
        foreach ($retenues as $alerte) {
            if ($alerte->origine === 'automatique' && $alerte->seuil_id !== null && isset($seuils[$alerte->seuil_id]) && $alerte->parametre_code !== null) {
                $classements[] = new Classement(
                    new ValeurAnalysee($alerte->parametre_code, (float) $alerte->valeur, $alerte->censure),
                    $seuils[$alerte->seuil_id],
                );
            }
        }

        $compatibilite = new CompatibiliteEvenements;
        $correspondances = $compatibilite->correspondances(
            $classements,
            $this->evenements($prelevement->elevage_id),
            $this->referentiels->liensCompatibilite(),
            DateTimeImmutable::createFromInterface($interpretation->date_interpretation),
            (int) $this->parametreObligatoire('interpretation.fenetre_evenements_mois', $gdsId),
        );
        $parametresCompatibles = $compatibilite->parametresPourRehaussement(
            $correspondances,
            (int) $this->parametreObligatoire('interpretation.force_min_rehaussement', $gdsId),
        );

        $niveau = (new NiveauGlobal)->proposer(
            $retenues->map(fn (Alerte $a) => (int) $a->gravite)->values()->all(),
            $retenues->filter(fn (Alerte $a) => $a->gravite >= 1 && $a->parametre_code !== null)
                ->map(fn (Alerte $a) => (string) $a->parametre_code)->unique()->values()->all(),
            $parametresCompatibles,
            $lot,
            $this->cuivre($analyse),
            $this->seuilCuivreOvins(array_values($seuils), $lot),
        );

        $interpretation->update([
            'niveau_propose' => $niveau->niveau,
            'motifs_rehaussement' => self::versTableau($niveau->rehaussements),
        ]);

        return $niveau;
    }

    /**
     * Borne basse du seuil cuivre propre aux ovins (sens « haut ») applicable au lot ; null si la grille n'en a pas.
     *
     * @param  list<Seuil>  $seuils
     */
    private function seuilCuivreOvins(array $seuils, ContexteLot $lot): ?float
    {
        $bornes = [];
        $lotOvin = new ContexteLot($lot->typeFourrageCode, $lot->familleFourrageCode, NiveauGlobal::ESPECE_OVINE, $lot->categorieCode);

        foreach ($seuils as $seuil) {
            if ($seuil->parametreCode === 'CU' && $seuil->especeCode === NiveauGlobal::ESPECE_OVINE
                && $seuil->sens === Seuil::SENS_HAUT && $seuil->borneMin !== null && $seuil->sAppliqueA($lotOvin)) {
                $bornes[] = $seuil->borneMin;
            }
        }

        return $bornes === [] ? null : min($bornes);
    }

    private function cuivre(Analyse $analyse): ?float
    {
        $valeur = DB::table('fourrage.resultat')->where('analyse_id', $analyse->id)->where('parametre_code', 'CU')->value('valeur');

        return $valeur === null ? null : (float) $valeur;
    }

    /**
     * @return list<EvenementSanitaire>
     */
    private function evenements(string $elevageId): array
    {
        return EvenementSanitaireModele::query()->withoutGlobalScopes()
            ->where('elevage_id', $elevageId)
            ->get()
            ->map(fn (EvenementSanitaireModele $e) => new EvenementSanitaire(
                $e->type_evenement,
                $e->espece_code,
                DateTimeImmutable::createFromInterface($e->date_evenement),
                $e->categorie_code,
            ))->values()->all();
    }

    /**
     * @return list<ValeurAnalysee>
     */
    private function valeurs(Analyse $analyse): array
    {
        return DB::table('fourrage.resultat as r')
            ->join('ref.parametre as p', 'p.code', '=', 'r.parametre_code')
            ->where('r.analyse_id', $analyse->id)
            ->whereNotNull('r.valeur')
            ->orderBy('p.ordre')->orderBy('p.code')
            ->get(['r.parametre_code', 'r.valeur', 'r.censure'])
            ->map(fn ($r) => new ValeurAnalysee((string) $r->parametre_code, (float) $r->valeur, $r->censure === null ? null : (string) $r->censure))
            ->values()->all();
    }

    private function contexte(Prelevement $prelevement, ?string $categorieCible): ContexteLot
    {
        return new ContexteLot(
            $prelevement->type_fourrage_code,
            $this->referentiels->familleDuType($prelevement->type_fourrage_code),
            $categorieCible === null ? null : $this->referentiels->especeDeLaCategorie($categorieCible),
            $categorieCible,
        );
    }

    private function prelevement(Analyse $analyse): Prelevement
    {
        return Prelevement::query()->withoutGlobalScopes()->findOrFail($analyse->prelevement_id);
    }

    private function interpretationDe(Alerte $alerte): Interpretation
    {
        return Interpretation::query()->withoutGlobalScopes()->findOrFail($alerte->interpretation_id);
    }

    /**
     * Journal d'audit (ENF-SEC-08) : validation, visa, publication.
     */
    private function journaliser(string $action, User $auteur, Interpretation $interpretation): void
    {
        $elevageId = DB::table('fourrage.analyse as a')
            ->join('fourrage.prelevement as p', 'p.id', '=', 'a.prelevement_id')
            ->where('a.id', $interpretation->analyse_id)
            ->value('p.elevage_id');

        $this->journal->ecrire($action, $auteur->id, 'fourrage.interpretation:'.$interpretation->id, [
            'version' => $interpretation->version,
            'statut' => $interpretation->statut,
            'niveau_global' => $interpretation->niveau_global,
            'niveau_propose' => $interpretation->niveau_propose,
        ], $elevageId === null ? null : (string) $elevageId);
    }

    private function exigerBrouillon(Interpretation $interpretation): void
    {
        if ($interpretation->statut !== Interpretation::STATUT_BROUILLON) {
            throw new InterpretationRefusee('Une interprétation validée, visée ou publiée ne se modifie plus.');
        }
    }

    private function categorieValide(?string $code): ?string
    {
        if ($code === null || $code === '') {
            return null;
        }

        if (! DB::table('ref.categorie_animale')->where('code', $code)->exists()) {
            throw new InterpretationRefusee('Catégorie animale inconnue : '.$code.'.');
        }

        return $code;
    }

    /**
     * Paramètre indispensable au calcul : aucune valeur de repli n'est codée ici (CLAUDE.md).
     */
    private function parametreObligatoire(string $cle, int $gdsId): float
    {
        return $this->referentiels->parametre($cle, $gdsId)
            ?? throw new InterpretationRefusee("Paramètre {$cle} absent des valeurs par défaut : interprétation impossible.");
    }

    /**
     * Fiche prive.personne du technicien (fourrage.interpretation.technicien_id), créée à sa première interprétation.
     */
    private function personneDe(User $user): int
    {
        if ($user->personne_id !== null) {
            return $user->personne_id;
        }

        $structure = $user->gds()->orderBy('id')->pluck('nom')->implode(', ');

        $id = (int) DB::table('prive.personne')->insertGetId([
            'nom' => $user->nom,
            'prenom' => $user->prenom,
            'structure' => $structure === '' ? null : $structure,
            'role' => 'technicien_gds',
            'courriel' => $user->email,
        ]);

        $user->forceFill(['personne_id' => $id])->save();

        return $id;
    }

    /**
     * Libellés des références bibliographiques (source du repère, EF-GEN-02).
     *
     * @param  array<int|null>  $ids
     * @return array<int, string>
     */
    private function sources(array $ids): array
    {
        $ids = array_values(array_unique(array_filter($ids, fn ($id) => $id !== null)));

        if ($ids === []) {
            return [];
        }

        $sources = [];
        foreach (DB::table('ref.reference_biblio')->whereIn('id', $ids)->get() as $ref) {
            $auteur = trim(($ref->auteurs ?? '').' '.($ref->annee ?? ''));
            $sources[(int) $ref->id] = ($auteur === '' ? '' : $auteur.' — ').$ref->titre;
        }

        return $sources;
    }

    private static function texte(?string $texte): ?string
    {
        $texte = $texte === null ? null : trim($texte);

        return $texte === '' ? null : $texte;
    }

    private static function nombreDeLignes(?string $texte): int
    {
        if ($texte === null) {
            return 0;
        }

        return count(array_filter(preg_split('/\R/', trim($texte)) ?: [], fn (string $ligne) => trim($ligne) !== ''));
    }

    /**
     * Liste PHP en tableau PostgreSQL littéral (« {"a","b"} ») ; liste vide : NULL.
     *
     * @param  list<string>  $valeurs
     */
    private static function versTableau(array $valeurs): ?string
    {
        if ($valeurs === []) {
            return null;
        }

        return '{'.implode(',', array_map(fn (string $v) => '"'.str_replace(['\\', '"'], ['\\\\', '\\"'], $v).'"', $valeurs)).'}';
    }

    /**
     * Tableau PostgreSQL littéral en liste PHP.
     *
     * @return list<string>
     */
    private static function depuisTableau(?string $tableau): array
    {
        if ($tableau === null || $tableau === '{}') {
            return [];
        }

        $valeurs = str_getcsv(substr($tableau, 1, -1), ',', '"', '\\');

        return array_map(fn ($v) => stripcslashes((string) $v), $valeurs);
    }
}
