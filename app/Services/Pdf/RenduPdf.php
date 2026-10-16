<?php

namespace App\Services\Pdf;

/**
 * Rendu d'une page HTML autonome en PDF A4 (EF-GEN-08, chapitre 6.1 : Browsershot, Chromium sans interface).
 * Le pied de page (pagination) est un gabarit Chromium : classes « pageNumber » et « totalPages ».
 */
interface RenduPdf
{
    public function pdf(string $html, string $piedDePage): string;
}
