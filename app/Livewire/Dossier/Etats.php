<?php

namespace App\Livewire\Dossier;

use App\Domain\Dossier\ClasseBilan;
use App\Domain\Dossier\LectureMesure;
use App\Domain\Dossier\LectureProfil;
use App\Domain\Troupeau\ClasseCouverture;

/**
 * Rendu visuel des lectures du dossier (EF-GEN-03 : couleur, libellé et valeur, jamais la couleur seule). Les
 * sources ne donnent pas de gravité aux lectures des mesures, des profils et du bilan : la correspondance avec les
 * états du kit graphique est une convention d'affichage (lot L3, étape 3), sans effet sur le niveau proposé.
 */
final class Etats
{
    /** @return array{libelle: string, etat: string} */
    public static function mesure(?string $code): array
    {
        return match ($code === null ? null : LectureMesure::from($code)) {
            null => ['libelle' => 'Repère non disponible', 'etat' => 'information'],
            LectureMesure::Normal => ['libelle' => 'Dans le repère', 'etat' => 'couvert'],
            LectureMesure::Bas => ['libelle' => 'Sous le repère', 'etat' => 'vigilance'],
            LectureMesure::Haut => ['libelle' => 'Au-dessus du repère', 'etat' => 'vigilance'],
        };
    }

    /** @return array{libelle: string, etat: string} */
    public static function profil(?LectureProfil $lecture): array
    {
        return match ($lecture) {
            null => ['libelle' => 'Repère non disponible', 'etat' => 'information'],
            LectureProfil::CarenceSevere => ['libelle' => $lecture->libelle(), 'etat' => 'alerte'],
            LectureProfil::Carence, LectureProfil::Exces => ['libelle' => $lecture->libelle(), 'etat' => 'vigilance'],
            LectureProfil::NormalBas, LectureProfil::Normal => ['libelle' => $lecture->libelle(), 'etat' => 'couvert'],
        };
    }

    /** @return array{libelle: string, etat: string} */
    public static function bilan(?ClasseBilan $classe): array
    {
        return match ($classe) {
            null => ['libelle' => 'Sans seuil', 'etat' => 'information'],
            ClasseBilan::Favorable => ['libelle' => $classe->libelle(), 'etat' => 'couvert'],
            ClasseBilan::Degradee => ['libelle' => $classe->libelle(), 'etat' => 'vigilance'],
            ClasseBilan::TresDegradee => ['libelle' => $classe->libelle(), 'etat' => 'alerte'],
        };
    }

    /**
     * Classe de la grille eau selon sa gravité.
     *
     * @return array{libelle: string, etat: string}
     */
    public static function classe(?string $classe, ?int $gravite): array
    {
        if ($classe === null) {
            return ['libelle' => 'Repère non disponible', 'etat' => 'information'];
        }

        return ['libelle' => ucfirst(str_replace('_', ' ', $classe)), 'etat' => match ($gravite) {
            1 => 'vigilance', 2 => 'alerte', 3 => 'critique', default => 'couvert',
        }];
    }

    /**
     * Couverture d'un élément par la ration (RC-9) : déficit en alerte, limite en vigilance, excès selon sa gravité,
     * maximum réglementaire dépassé en critique.
     *
     * @return array{libelle: string, etat: string}
     */
    public static function couverture(?ClasseCouverture $classe, ?int $gravite): array
    {
        return match ($classe) {
            null => ['libelle' => 'Non calculée', 'etat' => 'information'],
            ClasseCouverture::Deficit => ['libelle' => $classe->libelle(), 'etat' => 'alerte'],
            ClasseCouverture::Limite => ['libelle' => $classe->libelle(), 'etat' => 'vigilance'],
            ClasseCouverture::Couvert => ['libelle' => $classe->libelle(), 'etat' => 'couvert'],
            ClasseCouverture::Exces => ['libelle' => $classe->libelle(), 'etat' => $gravite >= 1 ? 'vigilance' : 'information'],
            ClasseCouverture::MaximumDepasse => ['libelle' => $classe->libelle(), 'etat' => 'critique'],
        };
    }

    /**
     * Gravité d'un contrôle de ration (RC-9) : null dans le repère.
     *
     * @return array{libelle: string, etat: string}
     */
    public static function controle(?int $gravite): array
    {
        return match ($gravite) {
            null => ['libelle' => 'Dans le repère', 'etat' => 'couvert'],
            0 => ['libelle' => 'Information', 'etat' => 'information'],
            1 => ['libelle' => 'Vigilance', 'etat' => 'vigilance'],
            default => ['libelle' => 'Alerte', 'etat' => 'alerte'],
        };
    }

    /** @return array{libelle: string, etat: string} */
    public static function statut(string $statut): array
    {
        return match ($statut) {
            'saisie', 'recu' => ['libelle' => 'Saisie', 'etat' => 'information'],
            'controle' => ['libelle' => 'Contrôlée', 'etat' => 'information'],
            'anomalie' => ['libelle' => 'En anomalie', 'etat' => 'critique'],
            'validee', 'valide' => ['libelle' => 'Validée', 'etat' => 'vigilance'],
            'publiee', 'publie' => ['libelle' => 'Publiée', 'etat' => 'couvert'],
            'en_cours' => ['libelle' => 'En cours', 'etat' => 'information'],
            'interpretee' => ['libelle' => 'Interprétée', 'etat' => 'vigilance'],
            'archivee' => ['libelle' => 'Archivée', 'etat' => 'information'],
            default => ['libelle' => ucfirst($statut), 'etat' => 'information'],
        };
    }
}
