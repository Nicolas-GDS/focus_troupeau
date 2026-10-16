<?php

namespace Database\Seeders;

use App\Models\App\Convention;
use App\Models\App\ElevageAcces;
use App\Models\App\User;
use App\Models\Dossier\Visite;
use App\Models\Fourrage\Analyse;
use App\Models\Fourrage\Elevage;
use App\Models\Fourrage\Prelevement;
use App\Services\Dossier\VisiteService;
use App\Services\Import\ImportBulletinService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Jeu d'essai J3 — ELV-0001 (§ 9.1) : les neuf lots du prototype de fiche du document 04 (§ 3, figures 2 et 3 :
 * élevage bovin pseudonymisé de la campagne 2025-2026), transcrits des figures le 16/10/2026. Seules les valeurs
 * lisibles sur la fiche prototype sont reprises (MS, UFL 2018, PDI, BPR, MAT, minéraux et oligo-éléments, BACA) ;
 * Ca, P, S et Cl n'y figurent pas, les ratios qui en dépendent ne sont donc pas calculés. Les numéros d'échantillon
 * sont fictifs ; aucune donnée nominative (pas d'identité, pas de commune).
 *
 * Le jeu charge les bulletins au statut « recu », les passe au contrôle (ratios, plausibilité : RC-1, RC-2) et les
 * rattache à une visite en cours du 29/07/2026 (date du second prélèvement) : la recette commence à l'interprétation
 * (docs/recette/L3_recette_J3.md). Les constantes UTILISATIONS, MESSAGES et ACTIONS reprennent la restitution du
 * prototype (figures 2 à 4) : le technicien les saisit pendant la recette ; le test de recette les rejoue.
 *
 * Les agents sont ceux du jeu J4 (technicien et vétérinaire conseil du GDS 24) ; l'éleveur a son propre compte.
 * Rejouable, interdit en production.
 */
class JeuJ3Seeder extends Seeder
{
    public const CODE = 'ELV-0001';

    public const ELEVEUR = 'eleveur.elv0001@j3.test';

    public const TECHNICIEN = 'technicien.24@j4.test';

    public const DATE_VISITE = '2026-07-29';

    /**
     * Les neuf lots (document 04, figures 2 et 3), dans l'ordre de la fiche.
     *
     * @var list<array{nom: string, type: string, date: string, cycle: string|null, conservation: string, valeurs: array<string, int|float>}>
     */
    public const LOTS = [
        ['nom' => 'Ensilage Sorgho BMR Fermente', 'type' => 'ENSIL_SORGHO', 'date' => '2025-12-05', 'cycle' => null, 'conservation' => 'ensile_direct',
            'valeurs' => ['MS' => 20.8, 'MAT' => 79, 'CU' => 6.6, 'ZN' => 40.0, 'MN' => 83.4, 'NA' => 0.1, 'MG' => 1.7, 'K' => 17.0, 'FE' => 114, 'MM' => 54]],
        ['nom' => 'Foin Dactyle / Luzerne', 'type' => 'FOIN_ASSOC', 'date' => '2025-12-05', 'cycle' => '2e cycle', 'conservation' => 'foin_sec',
            'valeurs' => ['MS' => 88.0, 'UFL_2018' => 0.77, 'PDI' => 84, 'BPR' => 17, 'MAT' => 154, 'CU' => 6.3, 'ZN' => 18.3, 'MN' => 79.6, 'NA' => 1.3, 'MG' => 1.8, 'K' => 21.5, 'FE' => 123, 'MM' => 79, 'BACA' => 311]],
        ['nom' => 'Foin de Prairie Naturelle', 'type' => 'FOIN_PP', 'date' => '2025-12-05', 'cycle' => '1er cycle', 'conservation' => 'foin_sec',
            'valeurs' => ['MS' => 89.0, 'UFL_2018' => 0.71, 'PDI' => 65, 'BPR' => -47, 'MAT' => 67, 'CU' => 4.0, 'ZN' => 18.9, 'MN' => 131.5, 'NA' => 0.8, 'MG' => 1.3, 'K' => 15.0, 'FE' => 280, 'MM' => 52, 'BACA' => 186]],
        ['nom' => 'Enrubannage Dactyle / Luzerne', 'type' => 'ENRUB_LUZ', 'date' => '2025-12-05', 'cycle' => '1er cycle', 'conservation' => 'enrubanne',
            'valeurs' => ['MS' => 63.9, 'UFL_2018' => 0.83, 'PDI' => 78, 'BPR' => 3, 'MAT' => 134, 'CU' => 5.3, 'ZN' => 18.0, 'MN' => 93.6, 'NA' => 1.0, 'MG' => 1.4, 'K' => 26.2, 'FE' => 160, 'MM' => 63, 'BACA' => 299]],
        ['nom' => 'Enrubannage de Ray Grass', 'type' => 'ENRUB_HERBE', 'date' => '2026-07-29', 'cycle' => '1er cycle', 'conservation' => 'enrubanne',
            'valeurs' => ['MS' => 53.7, 'UFL_2018' => 0.78, 'PDI' => 66, 'BPR' => -30, 'MAT' => 88, 'CU' => 1.8, 'ZN' => 16.2, 'MN' => 51.8, 'NA' => 0.5, 'MG' => 1.1, 'K' => 22.9, 'FE' => 317, 'MM' => 70, 'BACA' => 280]],
        ['nom' => 'Enrubannage Dactyle Luzerne', 'type' => 'ENRUB_LUZ', 'date' => '2026-07-29', 'cycle' => '1er cycle', 'conservation' => 'enrubanne',
            'valeurs' => ['MS' => 66.6, 'UFL_2018' => 0.88, 'PDI' => 94, 'BPR' => 49, 'MAT' => 194, 'CU' => 6.4, 'ZN' => 18.6, 'MN' => 59.7, 'NA' => 1.0, 'MG' => 1.7, 'K' => 25.9, 'FE' => 156, 'MM' => 87, 'BACA' => 341]],
        ['nom' => 'Foin de Prairie Permanente', 'type' => 'FOIN_PP', 'date' => '2026-07-29', 'cycle' => '1er cycle', 'conservation' => 'foin_sec',
            'valeurs' => ['MS' => 92.9, 'UFL_2018' => 0.64, 'PDI' => 61, 'BPR' => -49, 'MAT' => 60, 'CU' => 2.8, 'ZN' => 15.0, 'MN' => 108.1, 'NA' => 2.7, 'MG' => 1.5, 'K' => 9.5, 'FE' => 271, 'MM' => 63, 'BACA' => 206]],
        ['nom' => 'Foin de Luzerne', 'type' => 'FOIN_LUZ', 'date' => '2026-07-29', 'cycle' => '2e cycle', 'conservation' => 'foin_sec',
            'valeurs' => ['MS' => 87.9, 'UFL_2018' => 0.67, 'PDI' => 77, 'BPR' => 13, 'MAT' => 143, 'CU' => 6.1, 'ZN' => 16.9, 'MN' => 57.5, 'NA' => 1.1, 'MG' => 1.8, 'K' => 18.7, 'FE' => 126, 'MM' => 80, 'BACA' => 287]],
        ['nom' => 'Luzerne et Dactyle 2025', 'type' => 'FOIN_ASSOC', 'date' => '2026-07-29', 'cycle' => '1er cycle', 'conservation' => 'foin_sec',
            'valeurs' => ['MS' => 91.5, 'UFL_2018' => 0.74, 'PDI' => 83, 'BPR' => 21, 'MAT' => 155, 'CU' => 6.1, 'ZN' => 15.8, 'MN' => 57.3, 'NA' => 1.1, 'MG' => 1.9, 'K' => 19.3, 'FE' => 115, 'MM' => 85, 'BACA' => 334]],
    ];

    private const LEGUMINEUSE = 'Vaches en lactation, brebis fin de gestation ; apporte l\'azote soluble qui manque aux foins';

    private const PRAIRIE = 'Génisses, vaches taries, brebis à l\'entretien ; à associer à une légumineuse';

    /** Utilisation conseillée par lot (figure 2), dans l'ordre de LOTS. */
    public const UTILISATIONS = [
        'Bovins à besoins modérés ; azote à compléter',
        self::LEGUMINEUSE,
        self::PRAIRIE,
        self::LEGUMINEUSE,
        'Vaches allaitantes en lactation, génisses ; vérifier la conservation',
        self::LEGUMINEUSE,
        self::PRAIRIE,
        self::LEGUMINEUSE,
        self::LEGUMINEUSE,
    ];

    /**
     * Les cinq messages de la fiche (figure 2), par gravité décroissante. Gravités du kit : rouge 2, orange 1, vert et
     * information 0. Le message vert est réduit à ce que J3 permet de vérifier (le rapport K/(Ca+Mg) demande le calcium).
     *
     * @var list<array{gravite: int, pilier: string|null, texte: string}>
     */
    public const MESSAGES = [
        ['gravite' => 2, 'pilier' => 'nutrition', 'texte' => 'Cuivre et zinc : couverture structurellement insuffisante. 9 lots sur 9 sont sous le seuil de carence en cuivre (7 mg/kg MS) et 8 sous 30 mg/kg MS de zinc. La complémentation minérale doit être raisonnée sur ces chiffres, et adaptée à l\'espèce (le cuivre est toxique pour les ovins).'],
        ['gravite' => 1, 'pilier' => 'nutrition', 'texte' => 'Vos foins de prairie permanente sont pauvres en azote (MAT 60 à 67 g/kg MS, BPR très négative) : distribués seuls, ils ne font pas fonctionner le rumen correctement. Vos lots de luzerne et de dactyle-luzerne sont exactement le complément qu\'il leur faut : les distribuer ensemble.'],
        ['gravite' => 1, 'pilier' => 'environnement', 'texte' => 'Traces de terre dans 1 lot (fer > 300 mg/kg MS) : elles réduisent la disponibilité du cuivre et du zinc et favorisent les spores butyriques. Vérifiez la hauteur de coupe (7-8 cm) et le réglage des outils de fanage.'],
        ['gravite' => 0, 'pilier' => 'environnement', 'texte' => 'Aucun lot n\'est contaminé par la terre au sens des cendres (MM < 100 g/kg MS).'],
        ['gravite' => 0, 'pilier' => null, 'texte' => 'Ce que l\'analyse ne dit pas : sélénium, iode et cobalt ne sont pas dosés dans le forfait actuel. Si des veaux faibles, des rétentions placentaires ou des baisses d\'immunité sont observés, un profil métabolique sur 5 animaux, prescrit par votre vétérinaire, complétera ce diagnostic.'],
    ];

    /**
     * Le plan d'action en cinq points (figure 4) avec son responsable. Le prototype ne donne pas de priorité : celles-ci
     * sont proposées pour la recette.
     *
     * @var list<array{priorite: int, responsable: string, titre: string, comment: string, jalon: string|null}>
     */
    public const ACTIONS = [
        ['priorite' => 1, 'responsable' => 'eleveur', 'titre' => 'Associer systématiquement foin de prairie et luzerne', 'jalon' => null,
            'comment' => 'Dans la ration des animaux à besoins (vaches en lactation, génisses en croissance) : un tiers de luzerne ou de dactyle-luzerne ramène la BPR de la ration vers l\'équilibre et couvre le calcium. Réserver le foin de prairie seul aux vaches taries et aux animaux à l\'entretien.'],
        ['priorite' => 1, 'responsable' => 'technicien', 'titre' => 'Calculer la complémentation minérale sur les teneurs mesurées', 'jalon' => null,
            'comment' => 'Plutôt que sur des valeurs de table : apport de cuivre à viser 10 mg/kg MS de ration pour les bovins (fourrage : 2 à 7 mg, complément de 3 à 8 mg), zinc à 50 mg, sel à disposition. Formuler le besoin en g/jour d\'éléments, sans prescrire de produit.'],
        ['priorite' => 2, 'responsable' => 'veterinaire', 'titre' => 'Profil métabolique ciblé si troubles observés', 'jalon' => null,
            'comment' => 'Veaux faibles, myopathie, rétentions placentaires, non-délivrances, baisse d\'immunité : cuprémie, zinc, sélénium (GSH-Px), iode urinaire sur 5 animaux représentatifs. Décision de complémentation renforcée sur prescription.'],
        ['priorite' => 2, 'responsable' => 'eleveur', 'titre' => 'Limiter la terre à la récolte', 'jalon' => null,
            'comment' => 'Hauteur de coupe 7-8 cm, andaineur réglé, éviter le fanage sur sol humide. Objectif : fer < 300 mg/kg MS et cendres < 80 g/kg MS sur les foins.'],
        ['priorite' => 3, 'responsable' => 'technicien', 'titre' => 'Prochaine campagne : analyser le foin de 1re coupe dès la mise en stock', 'jalon' => 'campagne_suivante',
            'comment' => '3 à 4 semaines après pressage, avec l\'option sélénium, iode et cobalt pour un lot représentatif, afin de lever l\'incertitude sur ces trois éléments dans le secteur.'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Le jeu d\'essai J3 ne doit pas être chargé en production.');
        }

        $this->call(JeuJ4Seeder::class);

        DB::transaction(function (): void {
            $elevage = $this->elevage();
            $this->eleveur($elevage);

            if (Prelevement::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)->exists()) {
                return;
            }

            $technicien = User::query()->where('email', self::TECHNICIEN)->firstOrFail();
            $visite = app(VisiteService::class)->creer($elevage, $technicien, self::DATE_VISITE, 'initiale',
                'Restitution des analyses de la campagne 2025-2026');
            $controle = app(ImportBulletinService::class);
            $prelevements = [];

            foreach (self::LOTS as $i => $lot) {
                $analyse = $this->bulletin($elevage, $i + 1, $lot);
                $controle->controlerAnalyseChargee($analyse);
                $prelevements[] = $analyse->prelevement_id;
            }

            app(VisiteService::class)->rattacherPrelevements($visite, $prelevements);
        });
    }

    public static function elevageJ3(): Elevage
    {
        return Elevage::query()->withoutGlobalScopes()->where('code_pseudo', self::CODE)->firstOrFail();
    }

    public static function visiteJ3(): Visite
    {
        return Visite::query()->withoutGlobalScopes()->where('elevage_id', self::elevageJ3()->id)->orderBy('id')->firstOrFail();
    }

    private function elevage(): Elevage
    {
        $elevage = Elevage::query()->withoutGlobalScopes()->firstOrCreate(
            ['code_pseudo' => self::CODE],
            ['departement' => '24', 'gds_id' => 24],
        );

        if (! Convention::query()->withoutGlobalScopes()->where('elevage_id', $elevage->id)->exists()) {
            Convention::query()->create([
                'elevage_id' => $elevage->id,
                'gds_id' => 24,
                'date_debut' => '2025-09-01',
                'date_fin' => now()->addYear()->endOfYear()->toDateString(),
            ]);
        }

        return $elevage;
    }

    private function eleveur(Elevage $elevage): void
    {
        $user = User::query()->firstOrCreate(['email' => self::ELEVEUR], [
            'nom' => 'Éleveur', 'prenom' => self::CODE, 'password' => (string) config('authentification.mot_de_passe_j4'),
        ]);
        $user->forceFill(['notice_rgpd_acceptee_le' => $user->notice_rgpd_acceptee_le ?? now()])->save();
        $user->syncRoles(['eleveur']);
        $user->elevages()->syncWithoutDetaching([$elevage->id => ['role' => ElevageAcces::ROLE_ELEVEUR]]);
    }

    /**
     * @param  array{nom: string, type: string, date: string, cycle: string|null, conservation: string, valeurs: array<string, int|float>}  $lot
     */
    private function bulletin(Elevage $elevage, int $numero, array $lot): Analyse
    {
        $prelevement = Prelevement::query()->withoutGlobalScopes()->create([
            'elevage_id' => $elevage->id,
            'campagne_code' => VisiteService::campagneDe($lot['date']),
            'date_prelevement' => $lot['date'],
            'type_fourrage_code' => $lot['type'],
            'nom_lot' => $lot['nom'],
            'cycle_coupe' => $lot['cycle'],
            'mode_conservation' => $lot['conservation'],
        ]);

        $analyse = Analyse::query()->withoutGlobalScopes()->create([
            'prelevement_id' => $prelevement->id,
            'laboratoire_id' => (int) DB::table('ref.laboratoire')->where('code', 'LANO')->value('id'),
            'num_echantillon_labo' => sprintf('J3-%02d', $numero),
            'date_reception' => $lot['date'],
            'statut' => Analyse::STATUT_RECU,
        ]);

        $unites = DB::table('ref.parametre')->whereIn('code', array_keys($lot['valeurs']))->pluck('unite', 'code');
        foreach ($lot['valeurs'] as $code => $valeur) {
            DB::table('fourrage.resultat')->insert([
                'analyse_id' => $analyse->id, 'parametre_code' => $code, 'valeur' => $valeur,
                'unite' => (string) $unites[$code], 'origine' => 'labo',
            ]);
        }

        return $analyse;
    }
}
