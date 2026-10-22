<?php

namespace App\Services\Administration;

use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use App\Services\Cloisonnement\Perimetre;
use App\Services\Dossier\SaisieRefusee;
use App\Services\JournalService;
use Illuminate\Support\Facades\DB;

/**
 * Élevages (EF-M0-02, E23) : le technicien ou l'administrateur crée l'élevage d'un de ses GDS (code pseudonyme
 * `ELV-nnnn`, § 5) et son identité (prive.elevage_identite, numéro EDE unique), puis la complète. Le consentement au
 * traitement des données conditionne la création des comptes éleveurs (§ 2.5). Journalisé.
 */
class ElevagesService
{
    /** Champs d'identité saisis (prive.elevage_identite). */
    public const IDENTITE = ['raison_sociale', 'num_cheptel_ede', 'siret', 'nom_contact', 'adresse', 'code_postal', 'telephone', 'courriel', 'num_client_labo'];

    public const ESPECES = ['BOV' => 'Bovins', 'OVI' => 'Ovins', 'CAP' => 'Caprins'];

    public function __construct(
        private readonly Perimetre $perimetre,
        private readonly JournalService $journal,
    ) {}

    /** @return list<int> GDS dans lesquels l'agent crée un élevage */
    public function gdsPossibles(User $auteur): array
    {
        return DB::table('app.gds')->where('actif', true)
            ->when(! $this->perimetre->sansRestriction($auteur), fn ($q) => $q->whereIn('id', $this->perimetre->gdsAccessibles($auteur)))
            ->orderBy('id')->pluck('id')->map(fn ($i) => (int) $i)->all();
    }

    /** Département d'un élevage créé dans un GDS (l'identifiant du GDS est son numéro de département). */
    public static function departementDuGds(int $gds): string
    {
        return str_pad((string) $gds, 2, '0', STR_PAD_LEFT);
    }

    /**
     * @param  array<string, mixed>  $d  identité, gds_id, especes, effectifs, systemes, consentement_donnees
     */
    public function creer(array $d, User $auteur): Elevage
    {
        $gds = (int) $d['gds_id'];
        if (! in_array($gds, $this->gdsPossibles($auteur), true)) {
            throw new SaisieRefusee('GDS hors de votre périmètre.');
        }
        $this->controlerEde($d['num_cheptel_ede'] ?? null, null);

        return DB::transaction(function () use ($d, $gds, $auteur) {
            $elevage = Elevage::query()->create([
                'code_pseudo' => $this->prochainCode(), 'departement' => self::departementDuGds($gds), 'gds_id' => $gds,
                ...$this->caracteristiques($d),
            ]);
            DB::table('prive.elevage_identite')->insert([...$this->identite($d), 'elevage_id' => $elevage->id]);
            $this->journal->ecrire('creation_elevage', $auteur->id, 'fourrage.elevage:'.$elevage->id, [], $elevage->id);

            return $elevage;
        });
    }

    /**
     * @param  array<string, mixed>  $d
     */
    public function modifier(Elevage $elevage, array $d, User $auteur): void
    {
        if (! $this->perimetre->couvreElevage($auteur, $elevage)) {
            throw new SaisieRefusee('Élevage hors de votre périmètre.');
        }
        $this->controlerEde($d['num_cheptel_ede'] ?? null, $elevage->id);
        DB::transaction(function () use ($elevage, $d, $auteur) {
            $elevage->update($this->caracteristiques($d));
            DB::table('prive.elevage_identite')->updateOrInsert(['elevage_id' => $elevage->id], [...$this->identite($d), 'maj_le' => now()]);
            $this->journal->ecrire('modification_elevage', $auteur->id, 'fourrage.elevage:'.$elevage->id, [], $elevage->id);
        });
    }

    /** Code pseudonyme suivant (`ELV-0001`, § 5 : identifiant affiché dans les restitutions anonymisées). */
    public function prochainCode(): string
    {
        $max = (int) DB::table('fourrage.elevage')->where('code_pseudo', '~', '^ELV-[0-9]+$')->selectRaw('max(substring(code_pseudo from 5)::int) as m')->value('m');

        return sprintf('ELV-%04d', $max + 1);
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function caracteristiques(array $d): array
    {
        $entier = fn ($v) => $v === null || $v === '' ? null : (int) $v;

        return [
            'especes' => '{'.implode(',', array_values(array_intersect(array_keys(self::ESPECES), (array) ($d['especes'] ?? [])))).'}',
            'systeme_bovin' => ($d['systeme_bovin'] ?? null) ?: null, 'systeme_ovin' => ($d['systeme_ovin'] ?? null) ?: null, 'systeme_caprin' => ($d['systeme_caprin'] ?? null) ?: null,
            'effectif_bovins' => $entier($d['effectif_bovins'] ?? null), 'effectif_ovins' => $entier($d['effectif_ovins'] ?? null), 'effectif_caprins' => $entier($d['effectif_caprins'] ?? null),
            'adherent_gds' => (bool) ($d['adherent_gds'] ?? true), 'code_insee_commune' => ($d['code_insee_commune'] ?? null) ?: null,
        ];
    }

    /**
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>
     */
    private function identite(array $d): array
    {
        if (trim((string) ($d['raison_sociale'] ?? '')) === '') {
            throw new SaisieRefusee('La raison sociale est obligatoire.');
        }
        $valeurs = [];
        foreach (self::IDENTITE as $champ) {
            $valeurs[$champ] = trim((string) ($d[$champ] ?? '')) ?: null;
        }
        $valeurs['num_cheptel_ede'] = $valeurs['num_cheptel_ede'] === null ? null : strtoupper(str_replace(' ', '', $valeurs['num_cheptel_ede']));
        $consentement = (bool) ($d['consentement_donnees'] ?? false);

        return [...$valeurs, 'consentement_donnees' => $consentement, 'consentement_date' => $consentement ? (($d['consentement_date'] ?? null) ?: now()->toDateString()) : null];
    }

    private function controlerEde(?string $ede, ?string $elevageId): void
    {
        $ede = $ede === null ? '' : strtoupper(str_replace(' ', '', $ede));
        if ($ede === '') {
            return;
        }
        if (preg_match('/^FR\d{8}$/', $ede) !== 1) {
            throw new SaisieRefusee('Numéro EDE attendu : FR suivi de 8 chiffres.');
        }
        if (DB::table('prive.elevage_identite')->where('num_cheptel_ede', $ede)->when($elevageId, fn ($q) => $q->where('elevage_id', '!=', $elevageId))->exists()) {
            throw new SaisieRefusee('Ce numéro EDE est déjà celui d\'un autre élevage.');
        }
    }
}
