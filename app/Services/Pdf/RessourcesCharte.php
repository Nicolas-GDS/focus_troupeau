<?php

namespace App\Services\Pdf;

/**
 * Ressources de la charte pour les documents PDF (§ 8.4) : jetons de couleur, polices auto-hébergées, logos des GDS,
 * marque co-brandée, pictogrammes d'état. Toutes sont intégrées en URI de données, pour que le document figé ne
 * dépende d'aucun fichier externe.
 */
final class RessourcesCharte
{
    /**
     * Polices, logos et pictogrammes intégrés en URI de données : la fiche figée ne dépend d'aucun fichier externe.
     *
     * @return array{css_jetons: string, polices: array<string, string>, images: array<string, string>}
     */
    public static function toutes(): array
    {
        $jetons = (string) file_get_contents(resource_path('css/jetons.css'));
        $jetons = substr($jetons, 0, strpos($jetons, '@media') ?: strlen($jetons));
        $donnee = fn (string $fichier, string $type) => 'data:'.$type.';base64,'.base64_encode((string) file_get_contents(public_path($fichier)));

        $images = ['gds-france' => $donnee('img/logos/gds-france.png', 'image/png')];
        foreach (glob(public_path('img/logos/gds-*.jpg')) ?: [] as $logo) {
            $images[basename($logo, '.jpg')] = $donnee('img/logos/'.basename($logo), 'image/jpeg');
        }
        foreach (glob(public_path('img/marque/focus-troupeau-horizontal*.svg')) ?: [] as $marque) {
            $images[basename($marque, '.svg')] = $donnee('img/marque/'.basename($marque), 'image/svg+xml');
        }
        foreach (['critique', 'alerte', 'vigilance', 'couvert', 'information'] as $etat) {
            $images['etat-'.$etat] = $donnee('img/puces/etat-'.$etat.'.svg', 'image/svg+xml');
        }
        foreach (['faible', 'normal', 'eleve'] as $position) {
            $images['position-'.$position] = $donnee('img/puces/position-'.$position.'.svg', 'image/svg+xml');
        }

        return [
            'css_jetons' => $jetons,
            'polices' => [
                'outfit' => $donnee('fonts/Outfit.woff2', 'font/woff2'),
                'serif' => $donnee('fonts/SourceSerif4.woff2', 'font/woff2'),
                'serif_italique' => $donnee('fonts/SourceSerif4-Italic.woff2', 'font/woff2'),
            ],
            'images' => $images,
        ];
    }
}
