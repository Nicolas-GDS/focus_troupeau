<?php

namespace App\Services\Referentiels;

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
        foreach (['seuils', 'plages', 'version', 'compatibilites'] as $cle) {
            $this->cache->forget(self::CLE_CACHE.$cle);
        }
    }
}
