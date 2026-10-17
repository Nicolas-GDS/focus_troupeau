<?php

namespace App\Services\Referentiels;

use App\Domain\Referentiels\Referentiels;
use App\Domain\Referentiels\StatutEpistemique;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Dossier\SaisieRefusee;
use Illuminate\Support\Facades\DB;

/**
 * Paramètres de l'élevage (E16, EF-M4-07, EF-M7-03, D10) : pertes de conservation, de stockage et de distribution, MS
 * par défaut, densité du vrac, marges de sécurité et prix indicatifs se modifient par élevage (app.parametre_elevage) ;
 * la valeur de référence (app.parametre_defaut, éventuellement propre au GDS) reste affichée et le technicien voit les
 * écarts. Les autres paramètres relèvent du référentiel et ne se surchargent pas.
 */
class ParametresElevage
{
    /** Familles de paramètres modifiables par élevage (décisions du 17/10/2026). */
    public const PREFIXES = ['pertes.conservation.', 'pertes.stockage.', 'pertes.distribution.', 'ms.defaut.', 'densite.foin_vrac', 'securite.marge', 'prix.'];

    public function __construct(private readonly Referentiels $referentiels) {}

    public static function modifiable(string $cle): bool
    {
        foreach (self::PREFIXES as $prefixe) {
            if (str_starts_with($cle, $prefixe)) {
                return true;
            }
        }

        return false;
    }

    /** Valeur en vigueur pour l'élevage : la valeur de l'élevage pour une clé modifiable, à défaut la référence. */
    public function valeur(string $cle, ?string $elevageId, ?int $gdsId): ?float
    {
        if ($elevageId !== null && self::modifiable($cle)) {
            $propre = DB::table('app.parametre_elevage')->where('elevage_id', $elevageId)->where('cle', $cle)->value('valeur');
            if ($propre !== null) {
                return (float) $propre;
            }
        }

        return $this->referentiels->parametre($cle, $gdsId);
    }

    /**
     * Paramètres modifiables avec leur référence et la valeur de l'élevage.
     *
     * @return list<array{cle: string, libelle: string, unite: string, reference: float, source: string|null, statut: string|null, valeur: float|null, motif: string|null, maj_le: string|null, maj_par: string|null}>
     */
    public function liste(Elevage $elevage): array
    {
        $propres = DB::table('app.parametre_elevage as p')->leftJoin('app.user as u', 'u.id', '=', 'p.maj_par')
            ->where('p.elevage_id', $elevage->id)->get(['p.cle', 'p.valeur', 'p.motif', 'p.maj_le', 'u.prenom', 'u.nom'])->keyBy('cle');
        $lignes = [];
        foreach ($this->references($elevage->gds_id) as $r) {
            $p = $propres[$r->cle] ?? null;
            $statut = StatutEpistemique::tryFrom((string) $r->statut_epistemique);
            $lignes[] = [
                'cle' => (string) $r->cle, 'libelle' => (string) $r->libelle, 'unite' => (string) $r->unite, 'reference' => (float) $r->valeur,
                'source' => $r->source, 'statut' => $statut === null ? null : $statut->libelle().' ['.$statut->codeSpecifications().']',
                'valeur' => $p === null ? null : (float) $p->valeur, 'motif' => $p?->motif,
                'maj_le' => $p === null ? null : (string) $p->maj_le, 'maj_par' => $p === null ? null : trim($p->prenom.' '.$p->nom),
            ];
        }

        return $lignes;
    }

    /**
     * Écarts de l'élevage à la référence (EF-M4-07 : liste pour le technicien).
     *
     * @return list<array{cle: string, libelle: string, unite: string, reference: float, source: string|null, statut: string|null, valeur: float, motif: string|null, maj_le: string|null, maj_par: string|null}>
     */
    public function ecarts(Elevage $elevage): array
    {
        /** @var list<array{cle: string, libelle: string, unite: string, reference: float, source: string|null, statut: string|null, valeur: float, motif: string|null, maj_le: string|null, maj_par: string|null}> */
        return array_values(array_filter($this->liste($elevage), fn (array $l) => $l['valeur'] !== null));
    }

    public function modifier(Elevage $elevage, string $cle, float $valeur, ?string $motif, User $auteur): void
    {
        $reference = collect($this->references($elevage->gds_id))->firstWhere('cle', $cle);
        if (! self::modifiable($cle) || $reference === null) {
            throw new SaisieRefusee('Ce paramètre relève du référentiel et ne se modifie pas par élevage.');
        }
        $this->controler((string) $reference->unite, $valeur);

        DB::table('app.parametre_elevage')->updateOrInsert(['elevage_id' => $elevage->id, 'cle' => $cle],
            ['valeur' => $valeur, 'motif' => $motif === null || trim($motif) === '' ? null : trim($motif), 'maj_par' => $auteur->id, 'maj_le' => now()]);
    }

    public function retablir(Elevage $elevage, string $cle): void
    {
        DB::table('app.parametre_elevage')->where('elevage_id', $elevage->id)->where('cle', $cle)->delete();
    }

    private function controler(string $unite, float $valeur): void
    {
        $valide = match (true) {
            $unite === 'fraction' => $valeur >= 0 && $valeur < 1,
            str_starts_with($unite, '%') => $valeur > 0 && $valeur <= 100,
            default => $valeur >= 0,
        };
        if (! $valide) {
            throw new SaisieRefusee($unite === 'fraction' ? 'Saisir une fraction entre 0 et 1 (par exemple 0,05 pour 5 %).'
                : (str_starts_with($unite, '%') ? 'Saisir un pourcentage entre 0 et 100.' : 'Saisir une valeur positive.'));
        }
    }

    /** @return list<object{cle: string, valeur: string, unite: string, libelle: string, source: string|null, statut_epistemique: string}> références en vigueur (valeur du GDS, à défaut nationale) */
    private function references(?int $gdsId): array
    {
        $lignes = DB::table('app.parametre_defaut')
            ->where(fn ($q) => $q->whereNull('gds_id')->when($gdsId !== null, fn ($q) => $q->orWhere('gds_id', $gdsId)))
            ->whereDate('valide_du', '<=', today())->where(fn ($q) => $q->whereNull('valide_au')->orWhereDate('valide_au', '>=', today()))
            ->orderBy('cle')->orderByRaw('gds_id IS NULL')->orderByDesc('valide_du')->get(['cle', 'valeur', 'unite', 'libelle', 'source', 'statut_epistemique']);

        /** @var list<object{cle: string, valeur: string, unite: string, libelle: string, source: string|null, statut_epistemique: string}> */
        return $lignes->unique('cle')->filter(fn ($l) => self::modifiable((string) $l->cle))->values()->all();
    }
}
