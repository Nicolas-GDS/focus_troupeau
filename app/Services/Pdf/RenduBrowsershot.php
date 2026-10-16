<?php

namespace App\Services\Pdf;

use Spatie\Browsershot\Browsershot;

/**
 * Rendu PDF par Browsershot avec le Chromium du système (image Docker, docker/php/Dockerfile) : A4, fond imprimé,
 * marges de la charte, pied de page Chromium pour la pagination.
 */
class RenduBrowsershot implements RenduPdf
{
    public function pdf(string $html, string $piedDePage): string
    {
        $rendu = Browsershot::html($html)
            ->format('A4')
            ->margins(14, 12, 18, 12)
            ->showBackground()
            ->showBrowserHeaderAndFooter()
            ->headerHtml('<span></span>')
            ->footerHtml($piedDePage)
            ->noSandbox()
            ->timeout((int) config('fiches.delai_secondes'));

        if (($chrome = config('fiches.chrome')) !== null) {
            $rendu->setChromePath((string) $chrome);
        }

        if (($modules = config('fiches.node_modules')) !== null) {
            $rendu->setNodeModulePath((string) $modules);
        }

        return $rendu->pdf();
    }
}
