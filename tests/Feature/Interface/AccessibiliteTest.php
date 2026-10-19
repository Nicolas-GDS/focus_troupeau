<?php

// T-41 (ENF-ACC-01) : garde-fous statiques complétant l'audit axe-core (php artisan accessibilite:auditer).

it('rend chaque zone défilante atteignable au clavier (tabindex, RGAA 7.3 ; axe « scrollable-region-focusable »)', function () {
    $fautifs = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $fichier) {
        if (! str_ends_with((string) $fichier, '.blade.php')) {
            continue;
        }
        preg_match_all('/<(?:section|div)\b[^>]*class="[^"]*overflow-(?:x-)?auto[^"]*"[^>]*>/', (string) file_get_contents((string) $fichier), $balises);
        foreach ($balises[0] as $balise) {
            if (! str_contains($balise, 'tabindex=')) {
                $fautifs[] = str_replace(resource_path('views').'/', '', (string) $fichier);
            }
        }
    }

    expect(array_values(array_unique($fautifs)))->toBe([]);
});

it('donne une alternative textuelle à chaque image des vues (RGAA 1.1)', function () {
    $fautifs = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $fichier) {
        if (str_ends_with((string) $fichier, '.blade.php')) {
            preg_match_all('/<img\b[^>]*>/', (string) file_get_contents((string) $fichier), $images);
            foreach ($images[0] as $img) {
                if (! str_contains($img, 'alt=')) {
                    $fautifs[] = str_replace(resource_path('views').'/', '', (string) $fichier);
                }
            }
        }
    }

    expect($fautifs)->toBe([]);
});
