<?php

namespace App\Services\Referentiels;

use App\Domain\Dossier\ConditionLecture;
use App\Domain\Dossier\LectureMesure;
use App\Domain\Dossier\LienTroubleCause;
use App\Domain\Dossier\RegleLecture;
use App\Domain\Dossier\Repere;
use App\Domain\Dossier\RepereMesureTerrain;
use App\Domain\Dossier\RepereProfilSanguin;
use App\Domain\Dossier\SeuilBilanSanitaire;
use App\Domain\Dossier\TroubleCroisement;
use App\Domain\Interpretation\LienCompatibilite;
use App\Domain\Interpretation\PlagePlausibilite;
use App\Domain\Interpretation\Seuil;
use App\Domain\Referentiels\Referentiels;
use App\Domain\Referentiels\StatutEpistemique;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;

/**
 * Référentiels lus en base (ref.*, app.parametre_defaut), mis en cache (chapitre 6.2).
 * Le cache est vidé par oublier() à chaque modification des référentiels (E24).
 */
class ReferentielsBdd implements Referentiels
{
    private const CLE_CACHE = 'referentiels:';

    private const DUREE_CACHE = 3600;

    public function __construct(private readonly Cache $cache) {}

    public function seuilsActifs(): array
    {
        /** @var list<array<string, mixed>> $lignes */
        $lignes = $this->cache->remember(self::CLE_CACHE.'seuils', self::DUREE_CACHE, fn () => DB::table('ref.seuil_interpretation')
            ->where('actif', true)
            ->whereNull('version_fin')
            ->orderBy('id')
            ->get()
            ->map(fn ($ligne) => (array) $ligne)
            ->all());

        return array_map(fn (array $l) => new Seuil(
            id: (int) $l['id'],
            parametreCode: (string) $l['parametre_code'],
            classe: (string) $l['classe'],
            gravite: (int) ($l['gravite'] ?? 0),
            borneMin: $l['borne_min'] === null ? null : (float) $l['borne_min'],
            borneMax: $l['borne_max'] === null ? null : (float) $l['borne_max'],
            familleFourrageCode: $l['famille_fourrage_code'],
            typeFourrageCode: $l['type_fourrage_code'],
            especeCode: $l['espece_code'],
            categorieCode: $l['categorie_code'],
            nature: (string) $l['nature'],
            message: $l['message'],
            referenceId: $l['reference_id'] === null ? null : (int) $l['reference_id'],
            statutEpistemique: StatutEpistemique::from((string) $l['statut_epistemique']),
            sens: $l['sens'],
            valide: (bool) $l['valide'],
        ), $lignes);
    }

    public function plagesPlausibiliteActives(): array
    {
        /** @var list<array<string, mixed>> $lignes */
        $lignes = $this->cache->remember(self::CLE_CACHE.'plages', self::DUREE_CACHE, fn () => DB::table('ref.plage_plausibilite')
            ->where('actif', true)
            ->whereNull('version_fin')
            ->orderBy('id')
            ->get()
            ->map(fn ($ligne) => (array) $ligne)
            ->all());

        return array_map(fn (array $l) => new PlagePlausibilite(
            parametreCode: (string) $l['parametre_code'],
            borneMin: $l['borne_min'] === null ? null : (float) $l['borne_min'],
            borneMax: $l['borne_max'] === null ? null : (float) $l['borne_max'],
            familleFourrageCode: $l['famille_fourrage_code'],
        ), $lignes);
    }

    public function versionGrilleCourante(): string
    {
        // Plus grand numéro (E24 numérote les versions successivement ; deux versions peuvent partager date et seconde).
        return (string) $this->cache->remember(self::CLE_CACHE.'version', self::DUREE_CACHE, fn () => DB::table('ref.version_referentiel')
            ->orderByRaw("CASE WHEN numero ~ '^[0-9]+$' THEN numero::int END DESC NULLS LAST")
            ->orderByDesc('date_version')
            ->orderByDesc('cree_le')
            ->value('numero'));
    }

    public function parametre(string $cle, ?int $gdsId = null): ?float
    {
        $valeur = DB::table('app.parametre_defaut')
            ->where('cle', $cle)
            ->where(fn ($q) => $q->whereNull('gds_id')->when($gdsId !== null, fn ($q) => $q->orWhere('gds_id', $gdsId)))
            ->whereDate('valide_du', '<=', today())
            ->where(fn ($q) => $q->whereNull('valide_au')->orWhereDate('valide_au', '>=', today()))
            ->orderByRaw('gds_id IS NULL')
            ->orderByDesc('valide_du')
            ->value('valeur');

        return $valeur === null ? null : (float) $valeur;
    }

    public function familleDuType(string $typeFourrageCode): string
    {
        return (string) $this->cache->remember(self::CLE_CACHE.'famille:'.$typeFourrageCode, self::DUREE_CACHE,
            fn () => DB::table('ref.type_fourrage')->where('code', $typeFourrageCode)->value('famille_code'));
    }

    public function especeDeLaCategorie(string $categorieCode): ?string
    {
        $espece = $this->cache->remember(self::CLE_CACHE.'espece:'.$categorieCode, self::DUREE_CACHE,
            fn () => DB::table('ref.categorie_animale')->where('code', $categorieCode)->value('espece_code'));

        return $espece === null ? null : (string) $espece;
    }

    public function liensCompatibilite(): array
    {
        /** @var list<array<string, mixed>> $lignes */
        $lignes = $this->cache->remember(self::CLE_CACHE.'compatibilites', self::DUREE_CACHE, fn () => DB::table('ref.compatibilite_parametre_trouble')
            ->whereNull('version_fin')
            ->orderBy('id')
            ->get()
            ->map(fn ($ligne) => (array) $ligne)
            ->all());

        return array_map(fn (array $l) => new LienCompatibilite(
            parametreCode: (string) $l['parametre_code'],
            sens: (string) $l['sens'],
            troubleCode: (string) $l['trouble_code'],
            force: (int) $l['force'],
            especes: self::tableau($l['especes']),
            categories: self::tableau($l['categories']),
        ), $lignes);
    }

    public function reperesMesuresTerrain(): array
    {
        return array_map(fn (array $l) => new RepereMesureTerrain(
            id: (int) $l['id'],
            mesureCode: (string) $l['mesure_code'],
            borneBasse: self::nombre($l['borne_basse']),
            borneHaute: self::nombre($l['borne_haute']),
            source: self::source($l),
            especeCode: $l['espece_code'],
            stade: $l['stade'],
        ), $this->lignes('ref.repere_mesure_terrain', 'reperes_mesures'));
    }

    public function reglesLectureMesures(): array
    {
        return array_map(fn (array $l) => new RegleLecture(
            code: (string) $l['code'],
            ordre: (int) $l['ordre'],
            conditions: array_map(fn (array $c) => new ConditionLecture(
                mesureCode: (string) $c['mesure'],
                lecture: isset($c['lecture']) ? LectureMesure::from((string) $c['lecture']) : null,
                operateur: $c['op'] ?? null,
                valeur: isset($c['valeur']) ? (float) $c['valeur'] : null,
            ), (array) json_decode((string) $l['conditions'], true, flags: JSON_THROW_ON_ERROR)),
            libellePropose: (string) $l['libelle_propose'],
            source: self::source($l),
            especeCode: $l['espece_code'],
            stade: $l['stade'],
        ), $this->lignes('ref.grille_lecture_mesure', 'regles_lecture'));
    }

    public function reperesProfilsSanguins(): array
    {
        return array_map(fn (array $l) => new RepereProfilSanguin(
            id: (int) $l['id'],
            element: (string) $l['element'],
            matrice: (string) $l['matrice'],
            borneBasse: self::nombre($l['borne_basse']),
            borneHaute: self::nombre($l['borne_haute']),
            unite: (string) $l['unite'],
            source: self::source($l),
            especeCode: $l['espece_code'],
        ), $this->lignes('ref.repere_analyse_animale', 'reperes_profils'));
    }

    public function seuilsBilanSanitaire(): array
    {
        return array_map(fn (array $l) => new SeuilBilanSanitaire(
            id: (int) $l['id'],
            indicateurCode: (string) $l['indicateur_code'],
            s1: self::nombre($l['s1']),
            s2: self::nombre($l['s2']),
            sens: (string) $l['sens'],
            source: self::source($l),
            especeCode: $l['espece_code'],
            systeme: $l['systeme'],
        ), $this->lignes('ref.seuil_bilan_sanitaire', 'seuils_bilan'));
    }

    public function troublesCroisement(): array
    {
        /** @var list<array<string, mixed>> $lignes */
        $lignes = $this->cache->remember(self::CLE_CACHE.'troubles_croisement', self::DUREE_CACHE, fn () => DB::table('ref.trouble_croisement')
            ->whereNull('version_fin')
            ->orderBy('ordre')
            ->get()
            ->map(fn ($ligne) => (array) $ligne)
            ->all());

        return array_map(fn (array $l) => new TroubleCroisement(
            code: (string) $l['code'],
            libelle: (string) $l['libelle'],
            ordre: (int) $l['ordre'],
            indicateursSanitaires: self::tableau($l['indicateurs_sanitaires']) ?? [],
            troublesSanitaires: self::tableau($l['troubles_sanitaires']) ?? [],
        ), $lignes);
    }

    public function liensTroubleCause(): array
    {
        return array_map(fn (array $l) => new LienTroubleCause(
            troubleCode: (string) $l['trouble_code'],
            familleCause: (string) $l['famille_cause'],
            force: (int) $l['force'],
            source: self::source($l),
        ), $this->lignes('ref.grille_trouble_cause', 'grille_trouble_cause'));
    }

    /**
     * Lignes actives et en vigueur d'une table de repères, mises en cache.
     *
     * @return list<array<string, mixed>>
     */
    private function lignes(string $table, string $cle): array
    {
        /** @var list<array<string, mixed>> */
        return $this->cache->remember(self::CLE_CACHE.$cle, self::DUREE_CACHE, fn () => DB::table($table)
            ->where('actif', true)
            ->whereNull('version_fin')
            ->orderBy('id')
            ->get()
            ->map(fn ($ligne) => (array) $ligne)
            ->all());
    }

    /**
     * @param  array<string, mixed>  $l
     */
    private static function source(array $l): Repere
    {
        return new Repere(
            referenceId: $l['reference_id'] === null ? null : (int) $l['reference_id'],
            statutEpistemique: StatutEpistemique::from((string) $l['statut_epistemique']),
            valide: (bool) $l['valide'],
        );
    }

    private static function nombre(mixed $valeur): ?float
    {
        return $valeur === null ? null : (float) $valeur;
    }

    /**
     * Tableau PostgreSQL littéral « {A,B} » en liste PHP ; NULL reste NULL (toutes).
     *
     * @return list<string>|null
     */
    private static function tableau(mixed $valeur): ?array
    {
        if ($valeur === null) {
            return null;
        }

        $contenu = trim((string) $valeur, '{}');

        return $contenu === '' ? [] : array_map(fn ($v) => trim($v, '"'), explode(',', $contenu));
    }

    public function oublier(): void
    {
        foreach (['seuils', 'plages', 'version', 'compatibilites', 'reperes_mesures', 'regles_lecture', 'reperes_profils',
            'seuils_bilan', 'troubles_croisement', 'grille_trouble_cause'] as $cle) {
            $this->cache->forget(self::CLE_CACHE.$cle);
        }
    }
}
