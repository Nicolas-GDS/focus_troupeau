<?php

use App\Services\Cloisonnement\RegleStatut;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\JeuCloisonnement;

// § 2.3 et § 6.3 : l'éleveur ne voit un objet que lorsqu'il est publié ; les agents voient tous les statuts.
uses(RefreshDatabase::class);

it('ne montre que les objets publiés aux comptes externes', function (string $compte, string $statut, bool $visible) {
    $jeu = JeuCloisonnement::creer();

    expect(app(RegleStatut::class)->peutVoir($jeu->{$compte}, $statut))->toBe($visible);
})->with([
    ['eleveur24a', 'publie', true],
    ['eleveur24a', 'valide', false],
    ['eleveur24a', 'brouillon', false],
    ['veterinaire', 'publie', true],
    ['veterinaire', 'valide', false],
    ['technicien24', 'brouillon', true],
    ['veterinaireConseil24', 'valide', true],
    ['admin24', 'brouillon', true],
    ['adminGlobal', 'brouillon', true],
]);
