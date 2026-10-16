<?php

namespace App\Services\Dossier;

use App\Domain\Dossier\Croisement;
use App\Domain\Dossier\ElementSynthese;
use App\Domain\Dossier\FamilleCauseProposee;
use App\Domain\Dossier\MessagePropose;
use App\Domain\Dossier\SyntheseVisite;
use App\Domain\Referentiels\Referentiels;
use App\Jobs\GenererFichePdf;
use App\Jobs\RafraichirContextePrelevements;
use App\Models\App\User;
use App\Models\Dossier\Action;
use App\Models\Dossier\AnalyseEau;
use App\Models\Dossier\CauseCommune;
use App\Models\Dossier\Controle;
use App\Models\Dossier\EtiquetteAliment;
use App\Models\Dossier\InterpretationVisite;
use App\Models\Dossier\Message;
use App\Models\Dossier\MesureTerrain;
use App\Models\Dossier\PlanAction;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Alerte;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\AnalyseAnimale;
use App\Models\Fourrage\Interpretation;
use App\Services\Interpretation\InterpretationService;
use App\Services\JournalService;
use App\Services\Notifications\NotificationsPublication;
use DateTimeImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * E21 — interprétation de visite (EF-M2-05 à EF-M2-07) : synthèse unique publiée à l'éleveur.
 *
 * - brouillon prérempli : niveau et messages proposés (SyntheseVisite) à partir des interprétations des lots
 *   rattachés et des analyses d'eau ; les lectures des mesures, des profils et du bilan sanitaire, et les familles de
 *   cause proposées par la grille trouble × cause, sont présentées comme éléments à croiser ;
 * - circuit brouillon → valide → (visee si niveau retenu 3) → publie, refus de visa motivé (EF-M2-04) ;
 * - publication : elle publie aussi les interprétations de lots publiables et les objets validés de la visite, fige la
 *   version de la grille, archive la version publiée précédente, passe la visite en « publiee » et s'inscrit au
 *   journal ; la fiche figée est archivée en HTML et son PDF produit par un job (FicheVisiteService) ; l'éleveur
 *   et le technicien sont notifiés (e-mail, SMS au niveau 3) ;
 * - une modification après publication crée la version suivante, copie de la précédente (T-06) ; la visite publiée
 *   n'accepte plus de saisie : une donnée nouvelle appelle une nouvelle visite (décision de l'étape 4).
 */
class InterpretationVisiteService
{
    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly InterpretationService $interpretations,
        private readonly EauService $eau,
        private readonly MesuresService $mesures,
        private readonly ProfilsService $profils,
        private readonly BilanSanitaireService $bilan,
        private readonly JournalService $journal,
        private readonly FicheVisiteService $fiches,
        private readonly NotificationsPublication $notifications,
    ) {}

    /** Version en cours (la plus récente non archivée). */
    public function courante(Visite $visite): ?InterpretationVisite
    {
        return InterpretationVisite::query()->where('visite_id', $visite->id)
            ->where('statut', '!=', InterpretationVisite::STATUT_ARCHIVE)
            ->orderByDesc('version')->first();
    }

    /** Dernière version publiée, visible de l'éleveur. */
    public function publiee(Visite $visite): ?InterpretationVisite
    {
        return InterpretationVisite::query()->where('visite_id', $visite->id)
            ->where('statut', InterpretationVisite::STATUT_PUBLIE)->orderByDesc('version')->first();
    }

    /** @return Collection<int, InterpretationVisite> toutes les versions, de la plus récente à la plus ancienne */
    public function versions(Visite $visite): Collection
    {
        return InterpretationVisite::query()->where('visite_id', $visite->id)->orderByDesc('version')->get();
    }

    public function creerBrouillon(Visite $visite, User $technicien): InterpretationVisite
    {
        if ($this->courante($visite) !== null) {
            throw new SaisieRefusee('Une interprétation de cette visite existe déjà.');
        }

        return DB::transaction(function () use ($visite, $technicien) {
            $elements = $this->elementsSynthese($visite);
            $interpretation = InterpretationVisite::query()->create([
                'visite_id' => $visite->id,
                'version' => 1,
                'niveau_propose' => (new SyntheseVisite)->niveauPropose($this->niveauxLots($visite), $elements['eau']),
                'version_grille' => $this->referentiels->versionGrilleCourante(),
                'redige_par' => $technicien->id,
                'maj_par' => $technicien->id,
            ]);
            $this->ecrireMessages($interpretation, $this->messagesProposes($visite));
            PlanAction::query()->create(['interpretation_visite_id' => $interpretation->id]);
            $this->journaliser('creation_interpretation_visite', $technicien, $interpretation, $visite);

            return $interpretation;
        });
    }

    /**
     * Messages proposés (EF-M2-05), au plus visite.messages_max.
     *
     * @return list<MessagePropose>
     */
    public function messagesProposes(Visite $visite): array
    {
        $elements = $this->elementsSynthese($visite);

        return (new SyntheseVisite)->messages([...$elements['lots'], ...$elements['eau']], $this->messagesMax());
    }

    public function niveauPropose(Visite $visite): int
    {
        return (new SyntheseVisite)->niveauPropose($this->niveauxLots($visite), $this->elementsSynthese($visite)['eau']);
    }

    public function enregistrer(InterpretationVisite $interpretation, RedactionVisite $redaction, User $auteur): InterpretationVisite
    {
        $this->exigerBrouillon($interpretation);
        $interpretation->update([
            'titre' => self::texte($redaction->titre),
            'synthese' => self::texte($redaction->synthese),
            'limites' => self::texte($redaction->limites),
            'niveau_global' => $redaction->niveauRetenu,
            'maj_par' => $auteur->id,
        ]);

        return $interpretation->refresh();
    }

    /**
     * Remplace les messages, dans l'ordre donné (gravité, texte, pilier).
     *
     * @param  list<array{gravite: int, texte: string, pilier?: string|null, origine?: string, reference_objet?: string|null}>  $messages
     */
    public function remplacerMessages(InterpretationVisite $interpretation, array $messages): void
    {
        $this->exigerBrouillon($interpretation);

        if (count($messages) > $this->messagesMax()) {
            throw new SaisieRefusee('L\'interprétation de visite compte au plus '.$this->messagesMax().' messages.');
        }

        DB::transaction(function () use ($interpretation, $messages) {
            Message::query()->where('interpretation_visite_id', $interpretation->id)->delete();

            foreach ($messages as $i => $m) {
                Message::query()->create([
                    'interpretation_visite_id' => $interpretation->id, 'ordre' => $i + 1, 'gravite' => $m['gravite'],
                    'texte' => trim($m['texte']), 'pilier' => $m['pilier'] ?? null, 'origine' => $m['origine'] ?? 'technicien',
                    'reference_objet' => $m['reference_objet'] ?? null,
                ]);
            }
        });
    }

    /** Remet les messages proposés par l'application à la place des messages en cours. */
    public function reproposerMessages(InterpretationVisite $interpretation, Visite $visite): void
    {
        $this->exigerBrouillon($interpretation);
        DB::transaction(function () use ($interpretation, $visite) {
            Message::query()->where('interpretation_visite_id', $interpretation->id)->delete();
            $this->ecrireMessages($interpretation, $this->messagesProposes($visite));
            $interpretation->update(['niveau_propose' => $this->niveauPropose($visite)]);
        });
    }

    public function ajouterCause(InterpretationVisite $interpretation, string $famille, string $titre, ?string $preuves, ?string $consequences): CauseCommune
    {
        $this->exigerBrouillon($interpretation);

        if (! in_array($famille, CauseCommune::FAMILLES, true)) {
            throw new SaisieRefusee('Famille de cause inconnue.');
        }

        return CauseCommune::query()->create([
            'interpretation_visite_id' => $interpretation->id, 'famille_cause' => $famille, 'titre' => trim($titre),
            'preuves' => self::texte($preuves), 'consequences' => self::texte($consequences),
        ]);
    }

    public function supprimerCause(CauseCommune $cause): void
    {
        $this->exigerBrouillon($this->interpretationDe($cause->interpretation_visite_id));
        $cause->delete();
    }

    /**
     * @param  array{priorite: int, responsable: string, titre: string, comment?: string|null, quand?: string|null, echeance?: string|null, jalon?: string|null}  $donnees
     */
    public function ajouterAction(InterpretationVisite $interpretation, array $donnees, User $auteur): Action
    {
        $this->exigerBrouillon($interpretation);
        $plan = PlanAction::query()->firstOrCreate(['interpretation_visite_id' => $interpretation->id]);
        $ordre = (int) Action::query()->where('plan_action_id', $plan->id)->where('priorite', $donnees['priorite'])->max('ordre') + 1;

        return Action::query()->create([
            'plan_action_id' => $plan->id, 'priorite' => $donnees['priorite'], 'ordre' => $ordre,
            'responsable' => $donnees['responsable'], 'titre' => trim($donnees['titre']),
            'comment' => self::texte($donnees['comment'] ?? null), 'quand' => self::texte($donnees['quand'] ?? null),
            'echeance' => $donnees['echeance'] ?? null, 'jalon' => $donnees['jalon'] ?? null, 'maj_par' => $auteur->id,
        ]);
    }

    public function supprimerAction(Action $action): void
    {
        $this->exigerBrouillon($this->interpretationDeAction($action));
        $action->delete();
    }

    public function ajouterControle(Action $action, ?string $mesureCode, string $cible, string $echeance, User $auteur): Controle
    {
        $this->exigerBrouillon($this->interpretationDeAction($action));

        return Controle::query()->create([
            'action_id' => $action->id, 'mesure_code' => self::texte($mesureCode), 'cible' => trim($cible),
            'echeance' => $echeance, 'maj_par' => $auteur->id,
        ]);
    }

    public function supprimerControle(Controle $controle): void
    {
        $this->exigerBrouillon($this->interpretationDeAction(Action::query()->findOrFail($controle->action_id)));
        $controle->delete();
    }

    /** Visa d'une action vétérinaire par le vétérinaire conseil (§ 2.3), avant publication. */
    public function validerActionVeterinaire(Action $action, User $veterinaire): Action
    {
        $interpretation = $this->interpretationDeAction($action);

        if ($action->responsable !== 'veterinaire' || in_array($interpretation->statut, [InterpretationVisite::STATUT_PUBLIE, InterpretationVisite::STATUT_ARCHIVE], true)) {
            throw new SaisieRefusee('Seule une action vétérinaire d\'un plan non publié se valide.');
        }

        $action->update(['valide_par' => $veterinaire->id, 'valide_le' => now(), 'maj_par' => $veterinaire->id]);

        return $action;
    }

    /** L'éleveur marque « faite » une action qui lui incombe, sur le plan publié (EF-M2-06). */
    public function marquerFaite(Action $action, User $eleveur, ?string $commentaire = null): Action
    {
        if ($action->responsable !== 'eleveur' || $this->interpretationDeAction($action)->statut !== InterpretationVisite::STATUT_PUBLIE) {
            throw new SaisieRefusee('Seule une action de l\'éleveur d\'un plan publié se marque faite.');
        }

        $action->update([
            'statut' => Action::STATUT_FAITE, 'fait_le' => today(), 'fait_par' => $eleveur->id,
            'commentaire_eleveur' => self::texte($commentaire), 'maj_par' => $eleveur->id,
        ]);

        return $action;
    }

    /** Résultat d'un contrôle saisi par le technicien sur le plan publié (EF-M2-06, étape « suivre »). */
    public function saisirResultatControle(Controle $controle, string $resultat, User $technicien): Controle
    {
        $action = Action::query()->findOrFail($controle->action_id);

        if ($this->interpretationDeAction($action)->statut !== InterpretationVisite::STATUT_PUBLIE) {
            throw new SaisieRefusee('Le résultat d\'un contrôle se saisit sur le plan publié.');
        }

        $controle->update(['resultat' => trim($resultat), 'statut' => Controle::STATUT_REALISE, 'maj_par' => $technicien->id]);

        return $controle;
    }

    /** brouillon → valide (EF-M2-04) : synthèse, niveau retenu et au moins un message ; la visite passe « interpretee ». */
    public function valider(InterpretationVisite $interpretation, User $technicien): InterpretationVisite
    {
        $this->exigerBrouillon($interpretation);

        if (self::texte($interpretation->synthese) === null) {
            throw new SaisieRefusee('La synthèse est obligatoire pour valider l\'interprétation de visite.');
        }

        if ($interpretation->niveau_global === null) {
            throw new SaisieRefusee('Le niveau retenu est obligatoire pour valider l\'interprétation de visite.');
        }

        if (! Message::query()->where('interpretation_visite_id', $interpretation->id)->exists()) {
            throw new SaisieRefusee('Rédigez au moins un message pour l\'éleveur.');
        }

        DB::transaction(function () use ($interpretation, $technicien) {
            $interpretation->update(['statut' => InterpretationVisite::STATUT_VALIDE, 'maj_par' => $technicien->id]);
            $visite = $this->visiteDe($interpretation);
            if ($visite->statut === Visite::STATUT_EN_COURS) {
                $visite->update(['statut' => Visite::STATUT_INTERPRETEE]);
            }
            $this->journaliser('validation_interpretation_visite', $technicien, $interpretation, $visite);
        });

        return $interpretation->refresh();
    }

    public function viser(InterpretationVisite $interpretation, User $veterinaire): InterpretationVisite
    {
        if ($interpretation->statut !== InterpretationVisite::STATUT_VALIDE || $interpretation->niveau_global !== 3) {
            throw new SaisieRefusee('Seule une interprétation de visite validée de niveau 3 se vise.');
        }

        DB::transaction(function () use ($interpretation, $veterinaire) {
            $interpretation->update(['statut' => InterpretationVisite::STATUT_VISEE, 'vise_par' => $veterinaire->id, 'vise_le' => now()]);
            $this->journaliser('visa_interpretation_visite', $veterinaire, $interpretation, $this->visiteDe($interpretation));
        });

        return $interpretation->refresh();
    }

    public function refuserVisa(InterpretationVisite $interpretation, string $motif, User $veterinaire): InterpretationVisite
    {
        if ($interpretation->statut !== InterpretationVisite::STATUT_VALIDE || $interpretation->niveau_global !== 3) {
            throw new SaisieRefusee('Seule une interprétation de visite validée de niveau 3 peut voir son visa refusé.');
        }

        if (self::texte($motif) === null) {
            throw new SaisieRefusee('Le motif du refus de visa est obligatoire.');
        }

        DB::transaction(function () use ($interpretation, $motif, $veterinaire) {
            $interpretation->update([
                'statut' => InterpretationVisite::STATUT_BROUILLON, 'refus_visa_motif' => trim($motif),
                'refus_visa_par' => $veterinaire->id, 'refus_visa_le' => now(),
            ]);
            $this->journaliser('refus_visa_interpretation_visite', $veterinaire, $interpretation, $this->visiteDe($interpretation));
        });

        return $interpretation->refresh();
    }

    /** Règle de publication d'EF-M2-04 sur le niveau retenu, partagée avec la policy. */
    public static function publiable(InterpretationVisite $interpretation): bool
    {
        $niveau = $interpretation->niveau_global;

        return ($niveau !== null && $niveau <= 2 && $interpretation->statut === InterpretationVisite::STATUT_VALIDE)
            || ($niveau === 3 && $interpretation->statut === InterpretationVisite::STATUT_VISEE);
    }

    /**
     * Objets de la visite qui empêchent la publication : interprétation de lot non publiable, objet encore en saisie.
     *
     * @return list<string>
     */
    public function obstaclesPublication(Visite $visite): array
    {
        $obstacles = [];

        foreach ($this->interpretationsLots($visite) as ['numero' => $numero, 'interpretation' => $interpretation]) {
            if ($interpretation === null) {
                $obstacles[] = "Bulletin {$numero} : aucune interprétation.";
            } elseif ($interpretation->statut !== Interpretation::STATUT_PUBLIE && ! InterpretationService::publiable($interpretation)) {
                $obstacles[] = "Bulletin {$numero} : interprétation à valider".($interpretation->niveau_global === 3 ? ' et à faire viser.' : '.');
            }
        }

        $enSaisie = [
            'analyse(s) d\'eau à valider' => AnalyseEau::query()->where('visite_id', $visite->id)->whereNotIn('statut', [AnalyseEau::STATUT_VALIDE, AnalyseEau::STATUT_PUBLIE])->count(),
            'mesure(s) terrain à valider' => MesureTerrain::query()->where('visite_id', $visite->id)->where('statut', MesureTerrain::STATUT_SAISIE)->count(),
            'profil(s) sanguin(s) à valider' => AnalyseAnimale::query()->where('visite_id', $visite->id)->where('statut', AnalyseAnimale::STATUT_SAISIE)->count(),
            'étiquette(s) à valider' => EtiquetteAliment::query()->where('visite_id', $visite->id)->where('statut', EtiquetteAliment::STATUT_SAISIE)->count(),
        ];

        foreach ($enSaisie as $libelle => $nombre) {
            if ($nombre > 0) {
                $obstacles[] = "{$nombre} {$libelle}.";
            }
        }

        return $obstacles;
    }

    /**
     * Publication (EF-M2-04, EF-M2-07, T-06) : publie les interprétations de lots publiables et les objets validés de la
     * visite, fige la version de la grille, archive la version publiée précédente, passe la visite en « publiee ».
     */
    public function publier(InterpretationVisite $interpretation, User $technicien): InterpretationVisite
    {
        if (! self::publiable($interpretation)) {
            throw new SaisieRefusee($interpretation->niveau_global === 3
                ? 'Une interprétation de visite de niveau 3 ne se publie qu\'après le visa du vétérinaire conseil.'
                : 'Seule une interprétation de visite validée se publie.');
        }

        $visite = $this->visiteDe($interpretation);
        $obstacles = $this->obstaclesPublication($visite);

        if ($obstacles !== []) {
            throw new SaisieRefusee('Publication impossible : '.implode(' ', $obstacles));
        }

        DB::transaction(function () use ($interpretation, $technicien, $visite) {
            foreach ($this->interpretationsLots($visite) as ['interpretation' => $lot]) {
                if ($lot !== null && $lot->statut !== Interpretation::STATUT_PUBLIE) {
                    // La notification de la visite couvre ses lots : pas de notification par lot
                    $this->interpretations->publier($lot, $technicien, notifier: false);
                }
            }

            AnalyseEau::query()->where('visite_id', $visite->id)->where('statut', AnalyseEau::STATUT_VALIDE)->update(['statut' => AnalyseEau::STATUT_PUBLIE]);
            MesureTerrain::query()->where('visite_id', $visite->id)->where('statut', MesureTerrain::STATUT_VALIDEE)->update(['statut' => MesureTerrain::STATUT_PUBLIEE]);
            AnalyseAnimale::query()->where('visite_id', $visite->id)->where('statut', AnalyseAnimale::STATUT_VALIDEE)->update(['statut' => AnalyseAnimale::STATUT_PUBLIEE]);
            EtiquetteAliment::query()->where('visite_id', $visite->id)->where('statut', EtiquetteAliment::STATUT_VALIDEE)->update(['statut' => EtiquetteAliment::STATUT_PUBLIEE]);

            InterpretationVisite::query()->where('visite_id', $visite->id)->where('id', '!=', $interpretation->id)
                ->where('statut', InterpretationVisite::STATUT_PUBLIE)->update(['statut' => InterpretationVisite::STATUT_ARCHIVE]);
            $interpretation->update([
                'statut' => InterpretationVisite::STATUT_PUBLIE, 'publie_par' => $technicien->id, 'publie_le' => now(),
                'version_grille' => $this->referentiels->versionGrilleCourante(), 'maj_par' => $technicien->id,
            ]);
            $visite->update(['statut' => Visite::STATUT_PUBLIEE]);

            // Fiche figée : HTML archivé dans la transaction, PDF produit par le job après validation (EF-M2-07)
            $this->fiches->figerHtml($interpretation);
            GenererFichePdf::dispatch($interpretation->id)->afterCommit();
            RafraichirContextePrelevements::dispatch()->afterCommit();

            $this->journaliser('publication_interpretation_visite', $technicien, $interpretation, $visite);

            // EF-M8-01, EF-M8-02 : e-mails et SMS journalisés, envoyés après validation de la transaction
            $this->notifications->visitePubliee($interpretation, $technicien);
        });

        return $interpretation->refresh();
    }

    /**
     * Nouvelle version après publication (EF-M2-07, T-06) : copie de la version publiée (rédaction, messages, causes,
     * plan d'action et contrôles, avec leur état de suivi), en brouillon ; la version publiée reste visible de
     * l'éleveur jusqu'à la publication de la suivante, qui l'archive.
     */
    public function nouvelleVersion(InterpretationVisite $publiee, User $technicien): InterpretationVisite
    {
        if ($publiee->statut !== InterpretationVisite::STATUT_PUBLIE) {
            throw new SaisieRefusee('Une nouvelle version se crée à partir de la version publiée.');
        }

        $visite = $this->visiteDe($publiee);
        if ($this->courante($visite)?->id !== $publiee->id) {
            throw new SaisieRefusee('Une version plus récente est déjà en cours.');
        }

        return DB::transaction(function () use ($publiee, $technicien, $visite) {
            $copie = $publiee->replicate(['statut', 'vise_par', 'vise_le', 'refus_visa_motif', 'refus_visa_par', 'refus_visa_le',
                'publie_par', 'publie_le', 'fiche_html', 'fiche_pdf']);   // la fiche de la version précédente reste archivée
            $copie->fill(['version' => $publiee->version + 1, 'statut' => InterpretationVisite::STATUT_BROUILLON,
                'redige_par' => $technicien->id, 'maj_par' => $technicien->id]);
            $copie->save();

            foreach (Message::query()->where('interpretation_visite_id', $publiee->id)->get() as $message) {
                $message->replicate()->fill(['interpretation_visite_id' => $copie->id])->save();
            }
            foreach (CauseCommune::query()->where('interpretation_visite_id', $publiee->id)->get() as $cause) {
                $cause->replicate()->fill(['interpretation_visite_id' => $copie->id])->save();
            }

            $plan = PlanAction::query()->create(['interpretation_visite_id' => $copie->id]);
            $ancien = PlanAction::query()->where('interpretation_visite_id', $publiee->id)->first();
            foreach ($ancien === null ? [] : Action::query()->where('plan_action_id', $ancien->id)->get() as $action) {
                $nouvelle = $action->replicate()->fill(['plan_action_id' => $plan->id]);
                $nouvelle->save();
                foreach (Controle::query()->where('action_id', $action->id)->get() as $controle) {
                    $controle->replicate()->fill(['action_id' => $nouvelle->id])->save();
                }
            }

            $this->journaliser('nouvelle_version_interpretation_visite', $technicien, $copie, $visite);

            return $copie;
        });
    }

    /**
     * Éléments à croiser pour l'écran E21 (étape « croiser ») : lots, eau, mesures, profils, bilan sanitaire, troubles
     * présents et familles de cause proposées.
     *
     * @return array{lots: list<array{numero: string, interpretation: Interpretation|null, analyse_id: int}>, eau: list<array{analyse: AnalyseEau, lignes: list<array<string, mixed>>}>, mesures: list<array<string, mixed>>, profils: list<array<string, mixed>>, bilan: list<array<string, mixed>>, troubles: list<string>, familles: list<FamilleCauseProposee>}
     */
    public function elementsACroiser(Visite $visite): array
    {
        $lots = [];
        foreach ($this->interpretationsLots($visite) as $analyseId => $lot) {
            $lots[] = [...$lot, 'analyse_id' => $analyseId];
        }

        $bilan = $visite->campagne_code === null ? [] : $this->bilan->lignes($visite->elevage, $visite->campagne_code);
        $classes = [];
        foreach ($bilan as $ligne) {
            if ($ligne['classe'] !== null) {
                $classes[$ligne['code']] = $ligne['classe'];
            }
        }

        $croisement = new Croisement;
        $troubles = $croisement->troublesPresents($classes, $this->bilan->evenements($visite->elevage), $this->referentiels->troublesCroisement(),
            DateTimeImmutable::createFromInterface($visite->date_visite), (int) $this->referentiels->parametre('interpretation.fenetre_evenements_mois'));

        return [
            'lots' => $lots,
            'eau' => AnalyseEau::query()->where('visite_id', $visite->id)->orderBy('date_prelevement')->get()
                ->map(fn (AnalyseEau $a) => ['analyse' => $a, 'lignes' => $this->eau->lignes($a)])->values()->all(),
            'mesures' => $this->mesures->groupes($visite),
            'profils' => $this->profils->lignes($visite),
            'bilan' => $bilan,
            'troubles' => $troubles,
            'familles' => $croisement->familles($troubles, $this->referentiels->liensTroubleCause()),
        ];
    }

    /**
     * Interprétation courante de chaque bulletin des prélèvements rattachés, par identifiant d'analyse.
     *
     * @return array<int, array{numero: string, interpretation: Interpretation|null}>
     */
    private function interpretationsLots(Visite $visite): array
    {
        $analyses = Analyse::query()->whereIn('prelevement_id', DB::table('fourrage.prelevement')->where('visite_id', $visite->id)->select('id'))
            ->where('statut', '!=', Analyse::STATUT_ANNULE)->orderBy('num_echantillon_labo')->get();

        $lots = [];
        foreach ($analyses as $analyse) {
            $lots[$analyse->id] = ['numero' => (string) $analyse->num_echantillon_labo, 'interpretation' => $this->interpretations->courante($analyse)];
        }

        return $lots;
    }

    /** @return list<int> */
    private function niveauxLots(Visite $visite): array
    {
        return array_values(array_filter(array_map(fn (array $lot) => $lot['interpretation']?->niveau_global, $this->interpretationsLots($visite)),
            fn (?int $n) => $n !== null));
    }

    /**
     * @return array{lots: list<ElementSynthese>, eau: list<ElementSynthese>}
     */
    private function elementsSynthese(Visite $visite): array
    {
        $lots = [];
        foreach ($this->interpretationsLots($visite) as ['interpretation' => $interpretation]) {
            if ($interpretation === null) {
                continue;
            }

            foreach (Alerte::query()->where('interpretation_id', $interpretation->id)->where('retenue', true)->orderByDesc('gravite')->orderBy('id')->get() as $alerte) {
                $lots[] = new ElementSynthese(ElementSynthese::ORIGINE_LOT, $alerte->gravite, $alerte->message, 'fourrage.interpretation:'.$interpretation->id);
            }
        }

        $eau = [];
        foreach (AnalyseEau::query()->where('visite_id', $visite->id)->orderBy('id')->get() as $analyse) {
            foreach ($this->eau->lignes($analyse) as $ligne) {
                if ($ligne['gravite'] !== null && $ligne['gravite'] >= 1) {
                    $message = DB::table('ref.seuil_interpretation')->where('parametre_code', $ligne['code'])->where('classe', $ligne['classe'])
                        ->where('nature', 'eau')->whereNull('version_fin')->value('message');
                    $eau[] = new ElementSynthese(ElementSynthese::ORIGINE_EAU, (int) $ligne['gravite'], (string) ($message ?? $ligne['libelle']), 'dossier.analyse_eau:'.$analyse->id);
                }
            }
        }

        return ['lots' => $lots, 'eau' => $eau];
    }

    /** @param  list<MessagePropose>  $messages */
    private function ecrireMessages(InterpretationVisite $interpretation, array $messages): void
    {
        foreach ($messages as $m) {
            Message::query()->create([
                'interpretation_visite_id' => $interpretation->id, 'ordre' => $m->ordre, 'gravite' => $m->gravite,
                'texte' => $m->texte, 'pilier' => $m->pilier, 'origine' => $m->origine,
                'reference_objet' => implode(',', $m->referencesObjet),
            ]);
        }
    }

    private function messagesMax(): int
    {
        return (int) ($this->referentiels->parametre('visite.messages_max') ?? 0);
    }

    private function exigerBrouillon(InterpretationVisite $interpretation): void
    {
        if ($interpretation->statut !== InterpretationVisite::STATUT_BROUILLON) {
            throw new SaisieRefusee('Seule une interprétation de visite en brouillon se modifie.');
        }
    }

    private function interpretationDe(int $id): InterpretationVisite
    {
        return InterpretationVisite::query()->findOrFail($id);
    }

    private function interpretationDeAction(Action $action): InterpretationVisite
    {
        return $this->interpretationDe((int) PlanAction::query()->whereKey($action->plan_action_id)->value('interpretation_visite_id'));
    }

    private function visiteDe(InterpretationVisite $interpretation): Visite
    {
        return Visite::query()->with('elevage')->findOrFail($interpretation->visite_id);
    }

    private function journaliser(string $action, User $auteur, InterpretationVisite $interpretation, Visite $visite): void
    {
        $this->journal->ecrire($action, $auteur->id, 'dossier.interpretation_visite:'.$interpretation->id, [
            'visite_id' => $visite->id,
            'version' => $interpretation->version,
            'statut' => $interpretation->statut,
            'niveau_global' => $interpretation->niveau_global,
            'niveau_propose' => $interpretation->niveau_propose,
            'version_grille' => $interpretation->version_grille,
        ], $visite->elevage_id);
    }

    private static function texte(?string $texte): ?string
    {
        $texte = $texte === null ? null : trim($texte);

        return $texte === '' ? null : $texte;
    }
}
