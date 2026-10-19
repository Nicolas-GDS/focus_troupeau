<?php

namespace App\Services\Referentiels;

use App\Domain\Interpretation\PlagePlausibilite;
use App\Domain\Referentiels\Edition\ControleGrille;
use App\Domain\Referentiels\Edition\EditionRefusee;
use App\Domain\Referentiels\Edition\Nomenclatures;
use App\Domain\Referentiels\Edition\SaisiePlage;
use App\Domain\Referentiels\Edition\SaisieSeuil;
use App\Domain\Referentiels\Referentiels;
use App\Domain\Referentiels\StatutEpistemique;
use App\Models\App\User;
use App\Models\Ref\PlagePlausibilite as PlagePlausibiliteModele;
use App\Models\Ref\SeuilInterpretation;
use App\Models\Ref\VersionReferentiel;
use App\Services\JournalService;
use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Édition versionnée des référentiels (E24, EF-M10-02, EF-M10-03, ENF-EXP-04) : grille de seuils
 * (ref.seuil_interpretation, RC-1) et plages de plausibilité (ref.plage_plausibilite, RC-2).
 *
 * Chaque enregistrement crée une version (ref.version_referentiel : numéro suivant, auteur, date, décision du
 * comité technique). Rien n'est mis à jour en place ni supprimé : une modification clôture l'ancienne ligne
 * (version_fin) et en insère une nouvelle (version_creation) ; un retrait clôture la ligne. Les interprétations
 * gardent ainsi la grille de leur version_grille. Après enregistrement, le cache des référentiels est vidé et
 * l'opération est journalisée (app.journal, action « modification_referentiel », ENF-SEC-08).
 */
class EditionReferentielsService
{
    public const ACTION_JOURNAL = 'modification_referentiel';

    public const TABLE_SEUILS = 'ref.seuil_interpretation';

    public const TABLE_PLAGES = 'ref.plage_plausibilite';

    public const TABLE_TEXTES = 'ref.texte_type';

    public const TABLE_PARAMETRES = 'app.parametre_defaut';

    public function __construct(
        private readonly Referentiels $referentiels,
        private readonly JournalService $journal,
        private readonly ControleGrille $controle,
    ) {}

    public function creerSeuil(SaisieSeuil $saisie, string $decision, User $auteur): int
    {
        return $this->enVersion($decision, $auteur, function (string $version) use ($saisie) {
            $saisie = $this->verifierSeuil($saisie, null);
            $id = $this->inserer(self::TABLE_SEUILS, $saisie->colonnes(), $version);

            return [$id, self::TABLE_SEUILS, 'creation', null, $this->ligne(self::TABLE_SEUILS, $id)];
        });
    }

    /** Modifie un seuil actif : clôture la ligne et insère la ligne modifiée ; renvoie l'identifiant de la nouvelle ligne. */
    public function modifierSeuil(int $id, SaisieSeuil $saisie, string $decision, User $auteur): int
    {
        return $this->enVersion($decision, $auteur, function (string $version) use ($id, $saisie) {
            $avant = $this->ligneActive(self::TABLE_SEUILS, $id);
            $saisie = $this->verifierSeuil($saisie, $id);
            $this->cloturer(self::TABLE_SEUILS, $id, $version);
            $nouveau = $this->inserer(self::TABLE_SEUILS, $saisie->colonnes(), $version);

            return [$nouveau, self::TABLE_SEUILS, 'modification', $avant, $this->ligne(self::TABLE_SEUILS, $nouveau)];
        });
    }

    public function retirerSeuil(int $id, string $decision, User $auteur): string
    {
        return $this->retirer(self::TABLE_SEUILS, $id, $decision, $auteur);
    }

    public function creerPlage(SaisiePlage $saisie, string $decision, User $auteur): int
    {
        return $this->enVersion($decision, $auteur, function (string $version) use ($saisie) {
            $this->verifierPlage($saisie, null);
            $id = $this->inserer(self::TABLE_PLAGES, $saisie->colonnes(), $version);

            return [$id, self::TABLE_PLAGES, 'creation', null, $this->ligne(self::TABLE_PLAGES, $id)];
        });
    }

    public function modifierPlage(int $id, SaisiePlage $saisie, string $decision, User $auteur): int
    {
        return $this->enVersion($decision, $auteur, function (string $version) use ($id, $saisie) {
            $avant = $this->ligneActive(self::TABLE_PLAGES, $id);
            $this->verifierPlage($saisie, $id);
            $this->cloturer(self::TABLE_PLAGES, $id, $version);
            $nouveau = $this->inserer(self::TABLE_PLAGES, $saisie->colonnes(), $version);

            return [$nouveau, self::TABLE_PLAGES, 'modification', $avant, $this->ligne(self::TABLE_PLAGES, $nouveau)];
        });
    }

    public function retirerPlage(int $id, string $decision, User $auteur): string
    {
        return $this->retirer(self::TABLE_PLAGES, $id, $decision, $auteur);
    }

    /**
     * Modifie un texte type (EF-M10-03) : clôture la ligne en vigueur et insère le nouveau texte dans une nouvelle
     * version ; renvoie l'identifiant de la nouvelle ligne.
     */
    public function modifierTexte(string $code, string $texte, string $decision, User $auteur): int
    {
        if (trim($texte) === '') {
            throw new EditionRefusee(['texte' => 'Le texte ne peut pas être vide.']);
        }

        return $this->enVersion($decision, $auteur, function (string $version) use ($code, $texte) {
            $avant = DB::table(self::TABLE_TEXTES)->where('code', $code)->whereNull('version_fin')->first();
            if ($avant === null) {
                throw new EditionRefusee(['texte' => 'Texte type introuvable : '.$code.'.']);
            }
            DB::table(self::TABLE_TEXTES)->where('id', $avant->id)->update(['version_fin' => $version]);
            $id = (int) DB::table(self::TABLE_TEXTES)->insertGetId(['code' => $code, 'texte' => trim($texte), 'source' => $avant->source, 'version_creation' => $version]);

            return [$id, self::TABLE_TEXTES, 'modification', (array) $avant, (array) DB::table(self::TABLE_TEXTES)->find($id)];
        });
    }

    /**
     * Modifie une valeur par défaut (EF-M10-02, app.parametre_defaut, versionnement daté) : la ligne en vigueur est close
     * la veille et une ligne datée du jour est insérée (nationale, ou propre au GDS) ; une valeur déjà modifiée le jour
     * même est remplacée. Source et statut épistémique se saisissent avec la valeur. Une nouvelle version des
     * référentiels porte la décision du comité technique.
     */
    public function modifierParametre(string $cle, ?int $gdsId, float $valeur, ?string $source, ?string $statut, string $decision, User $auteur): int
    {
        return $this->enVersion($decision, $auteur, function () use ($cle, $gdsId, $valeur, $source, $statut) {
            $vigueur = fn (?int $gds) => DB::table(self::TABLE_PARAMETRES)->where('cle', $cle)->where(fn ($q) => $gds === null ? $q->whereNull('gds_id') : $q->where('gds_id', $gds))
                ->whereDate('valide_du', '<=', today())->where(fn ($q) => $q->whereNull('valide_au')->orWhereDate('valide_au', '>=', today()))->orderByDesc('valide_du')->first();
            $avant = $vigueur($gdsId);
            $modele = $avant ?? $vigueur(null);
            if ($modele === null) {
                throw new EditionRefusee(['valeur' => 'Paramètre inconnu : '.$cle.'.']);
            }
            $erreur = ParametresElevage::erreurValeur((string) $modele->unite, $valeur);
            if ($erreur !== null) {
                throw new EditionRefusee(['valeur' => $erreur]);
            }
            $colonnes = ['valeur' => $valeur, 'source' => trim((string) $source) ?: $modele->source,
                'statut_epistemique' => (StatutEpistemique::tryFrom((string) $statut) ?? StatutEpistemique::from((string) $modele->statut_epistemique))->value];

            if ($avant !== null && Carbon::parse($avant->valide_du)->isToday()) {
                DB::table(self::TABLE_PARAMETRES)->where('id', $avant->id)->update($colonnes);
                $id = (int) $avant->id;
            } else {
                if ($avant !== null) {
                    DB::table(self::TABLE_PARAMETRES)->where('id', $avant->id)->update(['valide_au' => today()->subDay()->toDateString()]);
                }
                $id = (int) DB::table(self::TABLE_PARAMETRES)->insertGetId([...$colonnes, 'gds_id' => $gdsId, 'cle' => $cle, 'unite' => $modele->unite,
                    'libelle' => $modele->libelle, 'valide_du' => today()->toDateString()]);
            }

            return [$id, self::TABLE_PARAMETRES, $avant === null ? 'creation' : 'modification', $avant === null ? null : (array) $avant, (array) DB::table(self::TABLE_PARAMETRES)->find($id)];
        });
    }

    /**
     * Seuils actifs de la grille (actif, version_fin nulle), filtrés par paramètre le cas échéant.
     *
     * @return Collection<int, SeuilInterpretation>
     */
    public function seuilsActifs(?string $parametreCode = null): Collection
    {
        return SeuilInterpretation::query()
            ->with('reference')
            ->where('actif', true)->whereNull('version_fin')
            ->when($parametreCode !== null && $parametreCode !== '', fn ($q) => $q->where('parametre_code', $parametreCode))
            ->orderBy('parametre_code')
            ->orderByRaw('famille_fourrage_code NULLS FIRST, type_fourrage_code NULLS FIRST, espece_code NULLS FIRST, categorie_code NULLS FIRST, borne_min NULLS FIRST')
            ->orderBy('id')
            ->get();
    }

    /**
     * Plages de plausibilité actives, filtrées par paramètre le cas échéant.
     *
     * @return Collection<int, PlagePlausibiliteModele>
     */
    public function plagesActives(?string $parametreCode = null): Collection
    {
        return PlagePlausibiliteModele::query()
            ->where('actif', true)->whereNull('version_fin')
            ->when($parametreCode !== null && $parametreCode !== '', fn ($q) => $q->where('parametre_code', $parametreCode))
            ->orderBy('parametre_code')
            ->orderByRaw('famille_fourrage_code NULLS FIRST')
            ->orderBy('id')
            ->get();
    }

    /** Auteur d'une version (nom affiché), s'il est connu. */
    public function auteur(?VersionReferentiel $version): ?string
    {
        return $version?->auteur_id === null ? null : User::query()->find($version->auteur_id)?->nomComplet();
    }

    /** Version courante des référentiels : celle de plus grand numéro. */
    public function versionCourante(): ?VersionReferentiel
    {
        return VersionReferentiel::query()->orderByRaw(self::ORDRE_NUMERO_DECROISSANT)->first();
    }

    /**
     * Historique des versions, de la plus récente à la plus ancienne, avec pour chacune les lignes créées et
     * clôturées dans chaque table.
     *
     * @return list<array{version: VersionReferentiel, auteur: string|null, seuils_crees: list<array<string, mixed>>, seuils_clotures: list<array<string, mixed>>, plages_creees: list<array<string, mixed>>, plages_cloturees: list<array<string, mixed>>}>
     */
    public function historique(): array
    {
        $seuils = $this->lignes(self::TABLE_SEUILS);
        $plages = $this->lignes(self::TABLE_PLAGES);

        $versions = VersionReferentiel::query()->orderByRaw(self::ORDRE_NUMERO_DECROISSANT)->get();
        $auteurs = User::query()->whereIn('id', $versions->pluck('auteur_id')->filter()->all())->get()->keyBy('id');

        return $versions
            ->map(fn (VersionReferentiel $v) => [
                'version' => $v,
                'auteur' => $v->auteur_id === null ? null : $auteurs->get($v->auteur_id)?->nomComplet(),
                'seuils_crees' => array_values(array_filter($seuils, fn ($l) => $l['version_creation'] === $v->numero)),
                'seuils_clotures' => array_values(array_filter($seuils, fn ($l) => $l['version_fin'] === $v->numero)),
                'plages_creees' => array_values(array_filter($plages, fn ($l) => $l['version_creation'] === $v->numero)),
                'plages_cloturees' => array_values(array_filter($plages, fn ($l) => $l['version_fin'] === $v->numero)),
            ])
            ->values()
            ->all();
    }

    public function nomenclatures(): Nomenclatures
    {
        /** @var list<string> $parametres */
        $parametres = DB::table('ref.parametre')->pluck('code')->all();
        /** @var list<string> $familles */
        $familles = DB::table('ref.famille_fourrage')->pluck('code')->all();
        /** @var array<string, string> $familleParType */
        $familleParType = DB::table('ref.type_fourrage')->pluck('famille_code', 'code')->all();
        /** @var list<string> $especes */
        $especes = DB::table('ref.espece_animale')->pluck('code')->all();
        /** @var array<string, string> $especeParCategorie */
        $especeParCategorie = DB::table('ref.categorie_animale')->pluck('espece_code', 'code')->all();
        $references = DB::table('ref.reference_biblio')->pluck('id')->map(fn ($id) => (int) $id)->values()->all();

        return new Nomenclatures($parametres, $familles, $familleParType, $especes, $especeParCategorie, $references);
    }

    /** Tri numérique décroissant des numéros de version ('10', …, '2', '1'). */
    private const ORDRE_NUMERO_DECROISSANT = "CASE WHEN numero ~ '^[0-9]+$' THEN numero::int END DESC NULLS LAST, cree_le DESC";

    /**
     * Exécute une opération dans une nouvelle version, sous verrou (les numéros et les contrôles de cohérence
     * ne peuvent pas se croiser entre deux administrateurs), puis vide le cache.
     *
     * @param  Closure(string): array{0: int, 1: string, 2: string, 3: array<string, mixed>|null, 4: array<string, mixed>|null}  $operation
     */
    private function enVersion(string $decision, User $auteur, Closure $operation): int
    {
        $erreurs = $this->controle->controlerDecision($decision);

        if ($erreurs !== []) {
            throw new EditionRefusee($erreurs);
        }

        $id = DB::transaction(function () use ($decision, $auteur, $operation) {
            DB::statement('LOCK TABLE ref.version_referentiel IN EXCLUSIVE MODE');

            $version = $this->nouvelleVersion(trim($decision), $auteur);
            [$id, $table, $nature, $avant, $apres] = $operation($version);

            $this->journal->ecrire(self::ACTION_JOURNAL, $auteur->id, "{$table}#".($avant['id'] ?? $id), [
                'table' => $table,
                'operation' => $nature,
                'version' => $version,
                'decision' => trim($decision),
                'avant' => $avant,
                'apres' => $apres,
            ]);

            return $id;
        });

        $this->referentiels->oublier();

        return $id;
    }

    private function retirer(string $table, int $id, string $decision, User $auteur): string
    {
        $version = '';

        $this->enVersion($decision, $auteur, function (string $numero) use ($table, $id, &$version) {
            $avant = $this->ligneActive($table, $id);
            $this->cloturer($table, $id, $numero);
            $version = $numero;

            return [$id, $table, 'retrait', $avant, $this->ligne($table, $id)];
        });

        return $version;
    }

    private function nouvelleVersion(string $decision, User $auteur): string
    {
        $dernier = (int) DB::table('ref.version_referentiel')->selectRaw("max(CASE WHEN numero ~ '^[0-9]+$' THEN numero::int END) AS n")->value('n');
        $numero = (string) ($dernier + 1);

        VersionReferentiel::query()->create([
            'numero' => $numero,
            'date_version' => today(),
            'auteur_id' => $auteur->id,
            'commentaire' => $decision,
        ]);

        return $numero;
    }

    private function verifierSeuil(SaisieSeuil $saisie, ?int $remplace): SaisieSeuil
    {
        $nomenclatures = $this->nomenclatures();
        $saisie = $saisie->normalisee($nomenclatures);

        // Lecture fraîche (cache vidé) des seuils actifs, sous le verrou de version.
        $this->referentiels->oublier();
        $autres = array_values(array_filter($this->referentiels->seuilsActifs(), fn ($s) => $s->id !== $remplace));

        $erreurs = $this->controle->controlerSeuil($saisie, $autres, $nomenclatures);

        if ($erreurs !== []) {
            throw new EditionRefusee($erreurs);
        }

        return $saisie;
    }

    private function verifierPlage(SaisiePlage $saisie, ?int $remplace): void
    {
        $autres = DB::table(self::TABLE_PLAGES)
            ->where('actif', true)->whereNull('version_fin')
            ->when($remplace !== null, fn ($q) => $q->where('id', '<>', $remplace))
            ->get()
            ->map(fn ($l) => new PlagePlausibilite(
                parametreCode: (string) $l->parametre_code,
                borneMin: $l->borne_min === null ? null : (float) $l->borne_min,
                borneMax: $l->borne_max === null ? null : (float) $l->borne_max,
                familleFourrageCode: $l->famille_fourrage_code,
            ))
            ->values()
            ->all();

        $erreurs = $this->controle->controlerPlage($saisie, $autres, $this->nomenclatures());

        if ($erreurs !== []) {
            throw new EditionRefusee($erreurs);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function ligneActive(string $table, int $id): array
    {
        $ligne = DB::table($table)->where('id', $id)->lockForUpdate()->first();

        if ($ligne === null || ! $ligne->actif || $ligne->version_fin !== null) {
            throw new EditionRefusee(['ligne' => 'Cette ligne n\'est plus active : elle a été modifiée ou retirée entre-temps.']);
        }

        return (array) $ligne;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function ligne(string $table, int $id): ?array
    {
        $ligne = DB::table($table)->where('id', $id)->first();

        return $ligne === null ? null : (array) $ligne;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lignes(string $table): array
    {
        return DB::table($table)->orderBy('parametre_code')->orderBy('id')->get()->map(fn ($l) => (array) $l)->values()->all();
    }

    /**
     * @param  array<string, mixed>  $colonnes
     */
    private function inserer(string $table, array $colonnes, string $version): int
    {
        return (int) DB::table($table)->insertGetId($colonnes + ['actif' => true, 'version_creation' => $version, 'version_fin' => null]);
    }

    private function cloturer(string $table, int $id, string $version): void
    {
        DB::table($table)->where('id', $id)->whereNull('version_fin')->update(['version_fin' => $version]);
    }
}
