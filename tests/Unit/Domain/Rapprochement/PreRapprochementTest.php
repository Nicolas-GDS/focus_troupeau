<?php

use App\Domain\Rapprochement\ElevageCandidat;
use App\Domain\Rapprochement\PreRapprochement;
use App\Domain\Rapprochement\PropositionRapprochement;

// Pré-rapprochement d'un bulletin « à rapprocher » (EF-M9-01) : classement des élevages sous convention active.

function candidat(string $id, string $raisonSociale, ?string $cp = null, ?string $commune = null, ?string $adresse = null, ?string $contact = null): ElevageCandidat
{
    return new ElevageCandidat($id, 'ELV-'.$id, $raisonSociale, $contact, $adresse, $cp, $commune);
}

it('propose en tête l\'élevage dont le nom, le code postal et la commune correspondent', function () {
    $propositions = (new PreRapprochement)->proposer(
        ['eleveur' => 'EARL DU PECH', 'code_postal' => '24260', 'commune' => 'LE BUGUE', 'adresse' => 'LIEU-DIT LE PECH'],
        [candidat('1', 'GAEC des Chênes', '24260', 'Le Bugue'), candidat('2', 'EARL du Pech', '24260', 'Le Bugue', 'Lieu-dit le Pech')],
    );

    expect($propositions[0]->elevage->elevageId)->toBe('2')
        ->and($propositions[0]->score)->toBe(100)
        ->and($propositions[0]->niveau)->toBe(PropositionRapprochement::FORTE)
        ->and($propositions[0]->motifs)->toBe(['Nom identique', 'Code postal identique', 'Commune identique', 'Adresse proche'])
        ->and($propositions[1]->elevage->elevageId)->toBe('1')
        ->and($propositions[1]->niveau)->toBe(PropositionRapprochement::FAIBLE);
});

it('ignore accents, casse, formes juridiques et tolère une faute de frappe', function () {
    $propositions = (new PreRapprochement)->proposer(
        ['eleveur' => 'SCEA CHATAIGNERAIE'],
        [candidat('1', 'GAEC de la Châtaigneraie'), candidat('2', 'EARL Chataigneraye')],
    );

    expect($propositions)->toHaveCount(2)
        ->and($propositions[0]->score)->toBe(60)
        ->and($propositions[1]->score)->toBe(60)
        ->and($propositions[0]->motifs)->toBe(['Nom identique']);
});

it('rapproche aussi sur le nom du contact ou du tiers, et lit « ST » comme « SAINT »', function () {
    $propositions = (new PreRapprochement)->proposer(
        ['eleveur' => null, 'tiers' => 'MR DUPONT JEAN', 'commune' => 'ST CYPRIEN'],
        [candidat('1', 'GAEC Les Granges', commune: 'Saint-Cyprien', contact: 'Jean Dupont')],
    );

    expect($propositions[0]->score)->toBe(70)->and($propositions[0]->motifs)->toBe(['Nom identique', 'Commune identique']);
});

it('ne propose rien quand rien ne correspond ou que l\'en-tête est vide', function () {
    $rapprochement = new PreRapprochement;
    $candidats = [candidat('1', 'EARL du Pech', '24260', 'Le Bugue')];

    expect($rapprochement->proposer(['eleveur' => 'GAEC MARTIN', 'code_postal' => '50008', 'commune' => 'SAINT-LO CEDEX'], $candidats))->toBe([])
        ->and($rapprochement->proposer([], $candidats))->toBe([]);
});

it('ne propose pas sur le seul code postal', function () {
    $propositions = (new PreRapprochement)->proposer(['code_postal' => '24260', 'eleveur' => 'GAEC MARTIN'], [candidat('1', 'EARL du Pech', '24260')]);

    expect($propositions)->toBe([]);
});

it('limite le nombre de propositions et départage à score égal par le libellé', function () {
    $candidats = array_map(fn (array $c) => new ElevageCandidat($c[0], 'ELV-'.$c[0], $c[1], null, 'Le Bourg', '24260'),
        [['1', 'C'], ['2', 'A'], ['3', 'B'], ['4', 'D']]);

    $propositions = (new PreRapprochement)->proposer(['code_postal' => '24260', 'adresse' => 'LE BOURG'], $candidats, 2);

    expect(array_map(fn (PropositionRapprochement $p) => $p->elevage->elevageId, $propositions))->toBe(['2', '3']);
});

it('ne met en avant qu\'une proposition forte sans concurrente', function () {
    $forte = fn (string $id) => new PropositionRapprochement(candidat($id, 'X'), 90, PropositionRapprochement::FORTE, []);
    $moyenne = new PropositionRapprochement(candidat('3', 'Y'), 50, PropositionRapprochement::MOYENNE, []);

    expect(PreRapprochement::evidente([$forte('1'), $moyenne])?->elevage->elevageId)->toBe('1')
        ->and(PreRapprochement::evidente([$forte('1'), $forte('2')]))->toBeNull()
        ->and(PreRapprochement::evidente([$moyenne]))->toBeNull();
});
