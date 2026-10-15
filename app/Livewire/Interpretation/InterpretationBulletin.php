<?php

namespace App\Livewire\Interpretation;

use App\Models\App\User;
use App\Models\Fourrage\Alerte;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Interpretation;
use App\Models\Fourrage\Prelevement;
use App\Models\Ref\CategorieAnimale;
use App\Models\Ref\Parametre;
use App\Services\Cloisonnement\RegleStatut;
use App\Services\ElevageCourant;
use App\Services\Interpretation\InterpretationRefusee;
use App\Services\Interpretation\InterpretationService;
use App\Services\Interpretation\RedactionInterpretation;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * E20 — Interprétation d'un bulletin (EF-M2-02, EF-M2-03, EF-M2-04 ; RC-1, RC-1.4 ; EF-GEN-02, EF-GEN-03).
 *
 * Résultats classés avec repère et source, alertes à retenir ou écarter, trois lectures dans l'ordre imposé,
 * synthèse, catégorie cible, utilisation, niveau proposé et niveau retenu, orientation vétérinaire ; circuit
 * brouillon → valide → (visée) → publiée. L'analyse est relue à chaque requête à travers les scopes de cloisonnement
 * et chaque action est autorisée côté serveur (ENF-SEC-04).
 */
class InterpretationBulletin extends Component
{
    #[Locked]
    public int $analyseId;

    public ?string $categorieCible = null;

    public string $lectureConservation = '';

    public string $lectureMineraux = '';

    public string $lectureNutritionnelle = '';

    public string $synthese = '';

    public string $recommandations = '';

    public string $utilisationConseillee = '';

    /** @var list<string> */
    public array $utilisationDeconseillee = [];

    public ?string $niveauRetenu = null;

    public bool $orientationVeterinaire = false;

    /** @var array<int, string> motif d'écart saisi par alerte */
    public array $motifsEcart = [];

    /** Motif d'un refus de visa (vétérinaire conseil). */
    public string $motifRefusVisa = '';

    /** @var array{parametre: string, classe: string, gravite: string, message: string} */
    public array $nouvelleAlerte = ['parametre' => '', 'classe' => '', 'gravite' => '1', 'message' => ''];

    public function mount(Analyse $analyse): void
    {
        $this->authorize('viewAny', [Interpretation::class, $analyse]);
        $this->analyseId = $analyse->id;

        $interpretation = $this->interpretation();

        if ($interpretation !== null) {
            $this->remplir($interpretation);
        } else {
            $this->categorieCible = Prelevement::query()->findOrFail($analyse->prelevement_id)->destination_categorie_code;
        }
    }

    public function creer(InterpretationService $service): void
    {
        $analyse = $this->analyse();
        $this->authorize('create', [Interpretation::class, $analyse]);
        $this->validate(['categorieCible' => ['nullable', 'string', Rule::exists(CategorieAnimale::class, 'code')]], [], ['categorieCible' => 'catégorie cible']);

        $this->executer(function () use ($service, $analyse) {
            $this->remplir($service->creerBrouillon($analyse, $this->utilisateur(), $this->categorieCible ?: null));
            session()->flash('status', 'Brouillon créé : alertes automatiques et niveau proposé calculés.');
        });
    }

    public function reclasser(InterpretationService $service): void
    {
        $interpretation = $this->interpretationAutorisee('update');
        $this->validate(['categorieCible' => ['nullable', 'string', Rule::exists(CategorieAnimale::class, 'code')]], [], ['categorieCible' => 'catégorie cible']);

        $this->executer(function () use ($service, $interpretation) {
            $service->changerCategorieCible($interpretation, $this->categorieCible ?: null);
            session()->flash('status', 'Bulletin reclassé pour la catégorie cible choisie.');
        });
    }

    public function retenir(int $alerteId, InterpretationService $service): void
    {
        $alerte = $this->alerte($alerteId);

        $this->executer(fn () => $service->retenirAlerte($alerte));
    }

    public function ecarter(int $alerteId, InterpretationService $service): void
    {
        $alerte = $this->alerte($alerteId);
        $this->validate(["motifsEcart.{$alerteId}" => ['required', 'string', 'min:5', 'max:500']], [], ["motifsEcart.{$alerteId}" => 'motif']);

        $this->executer(function () use ($service, $alerte, $alerteId) {
            $service->ecarterAlerte($alerte, $this->motifsEcart[$alerteId]);
            unset($this->motifsEcart[$alerteId]);
        });
    }

    public function ajouterAlerte(InterpretationService $service): void
    {
        $interpretation = $this->interpretationAutorisee('update');
        $this->validate([
            'nouvelleAlerte.parametre' => ['nullable', 'string', Rule::exists(Parametre::class, 'code')],
            'nouvelleAlerte.classe' => ['required', 'string', 'max:60'],
            'nouvelleAlerte.gravite' => ['required', 'integer', 'between:0,3'],
            'nouvelleAlerte.message' => ['required', 'string', 'min:5', 'max:500'],
        ], [], [
            'nouvelleAlerte.parametre' => 'paramètre', 'nouvelleAlerte.classe' => 'classe',
            'nouvelleAlerte.gravite' => 'gravité', 'nouvelleAlerte.message' => 'message',
        ]);

        $this->executer(function () use ($service, $interpretation) {
            $parametre = $this->nouvelleAlerte['parametre'] === '' ? null : $this->nouvelleAlerte['parametre'];
            $valeur = $parametre === null ? null : DB::table('fourrage.resultat')
                ->where('analyse_id', $this->analyseId)->where('parametre_code', $parametre)->value('valeur');

            $service->ajouterAlerte($interpretation, $parametre, $this->nouvelleAlerte['classe'], (int) $this->nouvelleAlerte['gravite'],
                $this->nouvelleAlerte['message'], $valeur === null ? null : (float) $valeur);
            $this->reset('nouvelleAlerte');
        });
    }

    public function enregistrer(InterpretationService $service): void
    {
        $interpretation = $this->interpretationAutorisee('update');
        $this->validerRedaction();

        $this->executer(function () use ($service, $interpretation) {
            $service->enregistrer($interpretation, $this->redaction());
            session()->flash('status', 'Interprétation enregistrée.');
        });
    }

    public function valider(InterpretationService $service): void
    {
        $interpretation = $this->interpretationAutorisee('valider');
        $this->validerRedaction();

        $this->executer(function () use ($service, $interpretation) {
            $service->valider($service->enregistrer($interpretation, $this->redaction()), $this->utilisateur());
            session()->flash('status', 'Interprétation validée.');
        });
    }

    public function viser(InterpretationService $service): void
    {
        $interpretation = $this->interpretationAutorisee('viser');

        $this->executer(function () use ($service, $interpretation) {
            $service->viser($interpretation, $this->utilisateur());
            session()->flash('status', 'Interprétation visée : le technicien peut la publier.');
        });
    }

    public function refuserVisa(InterpretationService $service): void
    {
        $interpretation = $this->interpretationAutorisee('refuserVisa');
        $this->validate(['motifRefusVisa' => ['required', 'string', 'min:5', 'max:1000']], [], ['motifRefusVisa' => 'motif du refus']);

        $this->executer(function () use ($service, $interpretation) {
            $service->refuserVisa($interpretation, $this->motifRefusVisa, $this->utilisateur());
            $this->motifRefusVisa = '';
            session()->flash('status', 'Visa refusé : l\'interprétation revient en brouillon chez le technicien.');
        });
    }

    public function publier(InterpretationService $service): void
    {
        $interpretation = $this->interpretationAutorisee('publier');

        $this->executer(function () use ($service, $interpretation) {
            $service->publier($interpretation, $this->utilisateur());
            session()->flash('status', 'Interprétation publiée.');
        });
    }

    public function render(InterpretationService $service): View
    {
        $analyse = $this->analyse();
        $prelevement = Prelevement::query()->with(['elevage', 'typeFourrage'])->findOrFail($analyse->prelevement_id);
        $interpretation = $this->interpretation();
        $alertes = $interpretation === null ? [] : $service->lignesAlertes($interpretation);
        if (! app(RegleStatut::class)->voitTousLesStatuts($this->utilisateur())) {
            // Lecture publiée (éleveur, vétérinaire, administrateur) : seules les alertes retenues sont montrées.
            $alertes = array_values(array_filter($alertes, fn (array $ligne) => $ligne['alerte']->retenue));
        }

        return view('livewire.interpretation.interpretation-bulletin', [
            'analyse' => $analyse,
            'prelevement' => $prelevement,
            'elevage' => $prelevement->elevage,
            'interpretation' => $interpretation,
            'resultats' => $service->lignesResultats($analyse, $interpretation === null ? $this->categorieCible : $interpretation->categorie_cible_code),
            'alertes' => $alertes,
            'motifsRehaussement' => $interpretation === null ? [] : $service->motifsRehaussement($interpretation),
            'deconseillees' => $interpretation === null ? [] : $service->utilisationDeconseillee($interpretation),
            'categories' => DB::table('ref.categorie_animale')->orderBy('espece_code')->orderBy('code')->pluck('libelle', 'code'),
            'parametresBulletin' => DB::table('fourrage.resultat as r')->join('ref.parametre as p', 'p.code', '=', 'r.parametre_code')
                ->where('r.analyse_id', $analyse->id)->orderBy('p.ordre')->pluck('p.libelle', 'p.code'),
            'peutCreer' => $interpretation === null && Gate::allows('create', [Interpretation::class, $analyse]),
            'peutModifier' => $interpretation !== null && Gate::allows('update', $interpretation),
            'peutViser' => $interpretation !== null && Gate::allows('viser', $interpretation),
            'peutPublier' => $interpretation !== null && Gate::allows('publier', $interpretation),
            'libelleElevage' => ElevageCourant::libelle($prelevement->elevage),
        ])->layout('components.layouts.espace', ['titre' => 'Interprétation d\'un bulletin']);
    }

    /**
     * Libellé et état visuel d'un niveau global (0 à 3) : la couleur n'est jamais seule (EF-GEN-03).
     *
     * @return array{libelle: string, etat: string}
     */
    public static function niveau(?int $niveau): array
    {
        return match ($niveau) {
            0 => ['libelle' => 'Niveau 0 — rien à signaler', 'etat' => 'couvert'],
            1 => ['libelle' => 'Niveau 1 — vigilance', 'etat' => 'vigilance'],
            2 => ['libelle' => 'Niveau 2 — alerte', 'etat' => 'alerte'],
            3 => ['libelle' => 'Niveau 3 — critique', 'etat' => 'critique'],
            default => ['libelle' => 'Niveau non fixé', 'etat' => 'information'],
        };
    }

    /**
     * Pastille d'une classe de la grille selon sa gravité (0 information, 1 vigilance, 2 alerte, 3 critique).
     *
     * @return array{libelle: string, etat: string}
     */
    public static function classe(?string $classe, ?int $gravite): array
    {
        if ($classe === null) {
            return ['libelle' => 'Sans repère dans la grille', 'etat' => 'information'];
        }

        $libelle = ucfirst(str_replace('_', ' ', $classe));

        return match ($gravite) {
            1 => ['libelle' => $libelle, 'etat' => 'vigilance'],
            2 => ['libelle' => $libelle, 'etat' => 'alerte'],
            3 => ['libelle' => $libelle, 'etat' => 'critique'],
            default => ['libelle' => $libelle, 'etat' => 'couvert'],
        };
    }

    /** @return array{libelle: string, etat: string} */
    public static function statut(string $statut): array
    {
        return match ($statut) {
            Interpretation::STATUT_BROUILLON => ['libelle' => 'Brouillon', 'etat' => 'information'],
            Interpretation::STATUT_VALIDE => ['libelle' => 'Validée', 'etat' => 'vigilance'],
            Interpretation::STATUT_VISEE => ['libelle' => 'Visée par le vétérinaire conseil', 'etat' => 'vigilance'],
            Interpretation::STATUT_PUBLIE => ['libelle' => 'Publiée', 'etat' => 'couvert'],
            default => ['libelle' => ucfirst($statut), 'etat' => 'information'],
        };
    }

    /** L'analyse, relue à travers les scopes de cloisonnement (404 hors périmètre). */
    private function analyse(): Analyse
    {
        return Analyse::query()->findOrFail($this->analyseId);
    }

    /**
     * Interprétation montrée : la version en cours ; à défaut de pouvoir la lire (compte externe et version non
     * publiée), la dernière version publiée.
     */
    private function interpretation(): ?Interpretation
    {
        $courante = app(InterpretationService::class)->courante($this->analyse());

        if ($courante === null || Gate::allows('view', $courante)) {
            return $courante;
        }

        $publiee = Interpretation::query()
            ->where('analyse_id', $this->analyseId)
            ->where('statut', Interpretation::STATUT_PUBLIE)
            ->orderByDesc('version')
            ->first();

        if ($publiee !== null) {
            $this->authorize('view', $publiee);
        }

        return $publiee;
    }

    private function interpretationAutorisee(string $capacite): Interpretation
    {
        $interpretation = $this->interpretation();

        if ($interpretation === null) {
            abort(404);
        }

        $this->authorize($capacite, $interpretation);

        return $interpretation;
    }

    /** Une alerte de l'interprétation en cours, modifiable par l'utilisateur. */
    private function alerte(int $alerteId): Alerte
    {
        $interpretation = $this->interpretationAutorisee('update');

        return Alerte::query()->where('interpretation_id', $interpretation->id)->findOrFail($alerteId);
    }

    private function remplir(Interpretation $interpretation): void
    {
        $this->categorieCible = $interpretation->categorie_cible_code;
        $this->lectureConservation = (string) $interpretation->lecture_sanitaire;
        $this->lectureMineraux = (string) $interpretation->lecture_mineraux;
        $this->lectureNutritionnelle = (string) $interpretation->lecture_nutritionnelle;
        $this->synthese = (string) $interpretation->synthese;
        $this->recommandations = (string) $interpretation->recommandations;
        $this->utilisationConseillee = (string) $interpretation->utilisation_conseillee;
        $this->utilisationDeconseillee = app(InterpretationService::class)->utilisationDeconseillee($interpretation);
        $this->niveauRetenu = $interpretation->niveau_global === null ? null : (string) $interpretation->niveau_global;
        $this->orientationVeterinaire = $interpretation->orientation_veterinaire;
    }

    private function validerRedaction(): void
    {
        $this->validate([
            'lectureConservation' => ['nullable', 'string', 'max:4000'],
            'lectureMineraux' => ['nullable', 'string', 'max:4000'],
            'lectureNutritionnelle' => ['nullable', 'string', 'max:4000'],
            'synthese' => ['nullable', 'string', 'max:2000'],
            'recommandations' => ['nullable', 'string', 'max:4000'],
            'utilisationConseillee' => ['nullable', 'string', 'max:1000'],
            'utilisationDeconseillee' => ['array'],
            'utilisationDeconseillee.*' => ['string', Rule::exists(CategorieAnimale::class, 'code')],
            'niveauRetenu' => ['nullable', 'integer', 'between:0,3'],
            'orientationVeterinaire' => ['boolean'],
        ], [], [
            'lectureConservation' => 'lecture « propreté et conservation »', 'lectureMineraux' => 'lecture « minéraux »',
            'lectureNutritionnelle' => 'lecture « valeur nutritionnelle »', 'synthese' => 'synthèse',
            'utilisationConseillee' => 'utilisation conseillée', 'utilisationDeconseillee' => 'utilisation déconseillée',
            'niveauRetenu' => 'niveau retenu',
        ]);
    }

    private function redaction(): RedactionInterpretation
    {
        return new RedactionInterpretation(
            lectureConservation: $this->lectureConservation,
            lectureMineraux: $this->lectureMineraux,
            lectureNutritionnelle: $this->lectureNutritionnelle,
            synthese: $this->synthese,
            recommandations: $this->recommandations,
            utilisationConseillee: $this->utilisationConseillee,
            utilisationDeconseillee: array_map('strval', $this->utilisationDeconseillee),
            niveauRetenu: $this->niveauRetenu === null || $this->niveauRetenu === '' ? null : (int) $this->niveauRetenu,
            orientationVeterinaire: $this->orientationVeterinaire,
        );
    }

    /**
     * Exécute une opération du service ; un refus du circuit (EF-M2-04) est affiché, sans erreur technique.
     */
    private function executer(callable $operation): void
    {
        try {
            $operation();
        } catch (InterpretationRefusee $e) {
            $this->addError('circuit', $e->getMessage());
        }
    }

    private function utilisateur(): User
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            throw new RuntimeException('Utilisateur non connecté.');
        }

        return $user;
    }
}
