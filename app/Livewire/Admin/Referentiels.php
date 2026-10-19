<?php

namespace App\Livewire\Admin;

use App\Domain\Referentiels\Edition\ControleGrille;
use App\Domain\Referentiels\Edition\EditionRefusee;
use App\Domain\Referentiels\Edition\SaisiePlage;
use App\Domain\Referentiels\Edition\SaisieSeuil;
use App\Domain\Referentiels\StatutEpistemique;
use App\Models\App\User;
use App\Models\Ref\VersionReferentiel;
use App\Services\Referentiels\EditionReferentielsService;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;
use RuntimeException;

/**
 * E24 — Administration : référentiels versionnés (EF-M10-02, EF-M10-03, ENF-EXP-04), pour la grille de seuils
 * d'interprétation (RC-1) et les plages de plausibilité (RC-2) : lignes actives filtrables par paramètre,
 * création, modification et retrait avec la décision du comité technique, version courante, historique.
 * Chaque action d'écriture est autorisée côté serveur (ENF-SEC-04, permission referentiel.ecrire).
 */
class Referentiels extends Component
{
    public const TABLES = ['seuils' => 'Grille de seuils', 'plages' => 'Plages de plausibilité', 'besoins' => 'Besoins journaliers', 'reperes_mesures' => 'Repères des mesures terrain',
        'reperes_animaux' => 'Repères des profils sanguins', 'bilan_sanitaire' => 'Seuils du bilan sanitaire', 'aliments' => 'Aliments de table', 'parametres' => 'Paramètres par défaut', 'textes' => 'Textes types'];

    /** Tables éditées par un composant dédié (lots L7 et L8) ; le composant générique reçoit la clé de la table. */
    public const COMPOSANTS = ['parametres' => 'admin.parametres-defaut', 'textes' => 'admin.textes-types', 'besoins' => 'admin.table-referentiel-ecran',
        'reperes_mesures' => 'admin.table-referentiel-ecran', 'reperes_animaux' => 'admin.table-referentiel-ecran', 'bilan_sanitaire' => 'admin.table-referentiel-ecran', 'aliments' => 'admin.aliments'];

    #[Locked]
    public string $table = 'seuils';

    public string $onglet = 'lignes';

    public string $filtreParametre = '';

    public bool $formulaireOuvert = false;

    /** Ligne en cours de modification (null : création). */
    #[Locked]
    public ?int $editionId = null;

    /** Ligne dont le retrait est demandé. */
    #[Locked]
    public ?int $retraitId = null;

    /** @var array<string, string|null> colonnes saisies */
    public array $formulaire = [];

    /** Décision du comité technique : commentaire de la version créée. */
    public string $decision = '';

    public function mount(string $table): void
    {
        abort_unless(array_key_exists($table, self::TABLES) && ! array_key_exists($table, self::COMPOSANTS), 404);
        $this->authorize('viewAny', VersionReferentiel::class);

        $this->table = $table;
    }

    public function afficher(string $onglet): void
    {
        $this->onglet = $onglet === 'historique' ? 'historique' : 'lignes';
    }

    public function nouveau(): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->annuler();

        // Une ligne nouvelle est une proposition tant que le comité ne l'a pas qualifiée (« à valider »).
        $this->formulaire = array_fill_keys($this->table === 'seuils' ? self::CHAMPS_SEUIL : self::CHAMPS_PLAGE, null);
        $this->formulaire['statut_epistemique'] = StatutEpistemique::HypotheseLocale->value;
        $this->formulaire['parametre_code'] = $this->filtreParametre !== '' ? $this->filtreParametre : null;
        $this->formulaireOuvert = true;
    }

    public function modifier(int $id): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->annuler();

        $ligne = DB::table($this->nomTable())->where('id', $id)->where('actif', true)->whereNull('version_fin')->first();
        abort_if($ligne === null, 404);

        $champs = $this->table === 'seuils' ? self::CHAMPS_SEUIL : self::CHAMPS_PLAGE;
        $this->formulaire = array_map(
            fn ($v) => $v === null ? null : self::versTexte($v),
            array_intersect_key((array) $ligne, array_flip($champs)),
        );
        $this->editionId = $id;
        $this->formulaireOuvert = true;
    }

    public function demanderRetrait(int $id): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->annuler();
        $this->retraitId = $id;
    }

    public function enregistrer(EditionReferentielsService $service): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->validerSaisie();

        try {
            if ($this->table === 'seuils') {
                $saisie = SaisieSeuil::depuis($this->formulaire);
                $this->editionId === null
                    ? $service->creerSeuil($saisie, $this->decision, $this->utilisateur())
                    : $service->modifierSeuil($this->editionId, $saisie, $this->decision, $this->utilisateur());
            } else {
                $saisie = SaisiePlage::depuis($this->formulaire);
                $this->editionId === null
                    ? $service->creerPlage($saisie, $this->decision, $this->utilisateur())
                    : $service->modifierPlage($this->editionId, $saisie, $this->decision, $this->utilisateur());
            }
        } catch (EditionRefusee $refus) {
            $this->signaler($refus);

            return;
        }

        $version = $service->versionCourante()?->numero;
        session()->flash('status', ($this->editionId === null ? 'Ligne créée' : 'Ligne modifiée')." : version {$version} des référentiels enregistrée.");
        $this->annuler();
    }

    public function retirer(EditionReferentielsService $service): void
    {
        $this->authorize('ecrire', VersionReferentiel::class);
        $this->validate(['decision' => self::REGLE_DECISION], [], ['decision' => 'décision du comité technique']);

        if ($this->retraitId === null) {
            return;
        }

        try {
            $version = $this->table === 'seuils'
                ? $service->retirerSeuil($this->retraitId, $this->decision, $this->utilisateur())
                : $service->retirerPlage($this->retraitId, $this->decision, $this->utilisateur());
        } catch (EditionRefusee $refus) {
            $this->signaler($refus);

            return;
        }

        session()->flash('status', "Ligne retirée : version {$version} des référentiels enregistrée.");
        $this->annuler();
    }

    public function annuler(): void
    {
        $this->reset('formulaireOuvert', 'editionId', 'retraitId', 'formulaire', 'decision');
        $this->resetValidation();
    }

    public function render(EditionReferentielsService $service): View
    {
        $courante = $service->versionCourante();
        $filtre = $this->filtreParametre === '' ? null : $this->filtreParametre;

        return view('livewire.admin.referentiels', [
            'versionCourante' => $courante,
            'auteurCourant' => $service->auteur($courante),
            'lignes' => $this->onglet === 'lignes'
                ? ($this->table === 'seuils' ? $service->seuilsActifs($filtre) : $service->plagesActives($filtre))
                : collect(),
            'historique' => $this->onglet === 'historique' ? $service->historique() : [],
            'peutEcrire' => Auth::user()?->can('ecrire', VersionReferentiel::class) ?? false,
            'parametres' => DB::table('ref.parametre')->orderBy('ordre')->orderBy('code')->get(['code', 'libelle', 'unite']),
            'familles' => DB::table('ref.famille_fourrage')->orderBy('code')->pluck('libelle', 'code'),
            'types' => DB::table('ref.type_fourrage')->orderBy('famille_code')->orderBy('ordre')->get(['code', 'libelle', 'famille_code']),
            'especes' => DB::table('ref.espece_animale')->orderBy('code')->pluck('libelle', 'code'),
            'categories' => DB::table('ref.categorie_animale')->orderBy('ordre')->get(['code', 'libelle', 'espece_code']),
            'references' => DB::table('ref.reference_biblio')->orderBy('cle')->get(['id', 'cle', 'titre']),
            'statuts' => StatutEpistemique::cases(),
            'natures' => self::NATURES,
        ]);
    }

    /** Libellés des natures de seuil (contrainte du schéma, document 02). */
    public const NATURES = [
        'nutritionnel' => 'Nutritionnel',
        'sanitaire' => 'Sanitaire',
        'reglementaire' => 'Réglementaire',
        'statistique_local' => 'Statistique locale',
    ];

    /**
     * Gravité d'un seuil (0 information, 1 vigilance, 2 alerte, 3 critique, document 02) : libellé et état
     * visuel, la couleur n'étant jamais seule (EF-GEN-03).
     *
     * @return array{libelle: string, etat: string}
     */
    public static function gravite(?int $gravite): array
    {
        return match ($gravite) {
            0 => ['libelle' => '0 — Information', 'etat' => 'information'],
            1 => ['libelle' => '1 — Vigilance', 'etat' => 'vigilance'],
            2 => ['libelle' => '2 — Alerte', 'etat' => 'alerte'],
            3 => ['libelle' => '3 — Critique', 'etat' => 'critique'],
            default => ['libelle' => 'Non renseignée', 'etat' => 'information'],
        };
    }

    public static function sens(?string $sens): string
    {
        return match ($sens) {
            'bas' => 'Bas (carence, valeur faible)',
            'haut' => 'Haut (excès, valeur élevée)',
            default => 'Non renseigné',
        };
    }

    public static function intervalle(mixed $min, mixed $max, bool $semiOuvert): string
    {
        return ControleGrille::intervalle(
            $min === null ? null : (float) $min,
            $max === null ? null : (float) $max,
            $semiOuvert,
        );
    }

    /**
     * Résumé d'une ligne pour l'historique.
     *
     * @param  array<string, mixed>  $ligne
     */
    public static function resume(array $ligne, bool $seuil): string
    {
        $portee = array_filter([
            $ligne['famille_fourrage_code'] ?? null,
            $ligne['type_fourrage_code'] ?? null,
            $ligne['espece_code'] ?? null,
            $ligne['categorie_code'] ?? null,
        ]);

        return sprintf('n° %d — %s %s%s%s',
            self::versEntier($ligne['id'] ?? 0),
            self::versTexte($ligne['parametre_code'] ?? ''),
            self::intervalle($ligne['borne_min'] ?? null, $ligne['borne_max'] ?? null, $seuil),
            $seuil ? ' « '.self::versTexte($ligne['classe'] ?? '').' », gravité '.self::versTexte($ligne['gravite'] ?? '—') : '',
            $portee === [] ? ($seuil ? ' (tous fourrages, toutes espèces)' : ' (tous fourrages)') : ' ('.implode(', ', array_map(self::versTexte(...), $portee)).')',
        );
    }

    private const CHAMPS_SEUIL = [
        'parametre_code', 'famille_fourrage_code', 'type_fourrage_code', 'espece_code', 'categorie_code', 'nature',
        'borne_min', 'borne_max', 'classe', 'gravite', 'sens', 'message', 'reference_id', 'statut_epistemique', 'commentaire',
    ];

    private const CHAMPS_PLAGE = [
        'parametre_code', 'famille_fourrage_code', 'borne_min', 'borne_max', 'statut_epistemique', 'source', 'commentaire',
    ];

    /** @var list<string> */
    private const REGLE_DECISION = ['required', 'string', 'min:5', 'max:2000'];

    private const REGLE_NOMBRE = ['nullable', 'regex:/^\s*-?\d+([.,]\d+)?\s*$/'];

    /** Contrôle de forme ; les règles de cohérence sont celles du domaine (ControleGrille). */
    private function validerSaisie(): void
    {
        $regles = [
            'decision' => self::REGLE_DECISION,
            'formulaire.parametre_code' => ['required', 'string'],
            'formulaire.borne_min' => self::REGLE_NOMBRE,
            'formulaire.borne_max' => self::REGLE_NOMBRE,
            'formulaire.statut_epistemique' => ['required', 'string'],
        ];

        if ($this->table === 'seuils') {
            $regles += [
                'formulaire.nature' => ['required', 'string'],
                'formulaire.classe' => ['required', 'string', 'max:60'],
                'formulaire.gravite' => ['required', 'integer'],
                'formulaire.sens' => ['required', 'string'],
                'formulaire.message' => ['nullable', 'string', 'max:2000'],
                'formulaire.reference_id' => ['nullable', 'integer'],
            ];
        }

        $this->validate($regles, [], [
            'decision' => 'décision du comité technique',
            'formulaire.parametre_code' => 'paramètre',
            'formulaire.borne_min' => 'borne basse',
            'formulaire.borne_max' => 'borne haute',
            'formulaire.statut_epistemique' => 'statut épistémique',
            'formulaire.nature' => 'nature',
            'formulaire.classe' => 'classe',
            'formulaire.gravite' => 'gravité',
            'formulaire.sens' => 'sens',
            'formulaire.message' => 'message',
            'formulaire.reference_id' => 'référence',
        ]);
    }

    private function signaler(EditionRefusee $refus): void
    {
        foreach ($refus->erreurs as $champ => $message) {
            $cle = match ($champ) {
                'decision' => 'decision',
                'chevauchement', 'ligne' => 'formulaire',
                default => "formulaire.{$champ}",
            };
            $this->addError($cle, $message);
        }
    }

    private function nomTable(): string
    {
        return $this->table === 'seuils' ? EditionReferentielsService::TABLE_SEUILS : EditionReferentielsService::TABLE_PLAGES;
    }

    private static function versTexte(mixed $valeur): string
    {
        if (is_bool($valeur)) {
            return $valeur ? '1' : '0';
        }

        return is_scalar($valeur) ? (string) $valeur : '';
    }

    private static function versEntier(mixed $valeur): int
    {
        return is_numeric($valeur) ? (int) $valeur : 0;
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
