<?php

use App\Livewire\Compte\Consentements;
use App\Models\App\Consentement;
use App\Models\App\User;
use App\Services\Cloisonnement\Perimetre;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Support\JeuCloisonnement;

// E17 — consentements de l'éleveur (EF-M0-07, § 2.5) : références et vétérinaire, accord et révocation journalisés,
// effet immédiat sur l'accès du vétérinaire.
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->jeu = JeuCloisonnement::creer();
    $this->veto = User::factory()->create(['nom' => 'Vétérinaire Nouveau', 'actif' => true]);
    $this->veto->assignRole('veterinaire');
});

it('laisse l\'éleveur accorder et révoquer ses consentements, l\'accès du vétérinaire suit (EF-M0-07)', function () {
    $this->actingAs($this->jeu->eleveur24a);
    $perimetre = app(Perimetre::class);
    expect($perimetre->couvreElevage($this->veto, $this->jeu->elevage24a))->toBeFalse();

    Livewire::test(Consentements::class)
        ->assertSee('Références départementales')->assertSee($this->jeu->veterinaire->nomComplet())
        ->call('accorderReferences', $this->jeu->elevage24a->id)->assertHasNoErrors()
        ->set('veterinaires.'.$this->jeu->elevage24a->id, (string) $this->veto->id)->call('accorderVeterinaire', $this->jeu->elevage24a->id)->assertHasNoErrors()
        ->assertSee('Vétérinaire Nouveau');
    expect($perimetre->couvreElevage($this->veto, $this->jeu->elevage24a))->toBeTrue();

    $c = Consentement::query()->withoutGlobalScopes()->where('veterinaire_user_id', $this->veto->id)->whereNull('revoque_le')->sole();
    Livewire::test(Consentements::class)->call('revoquer', $c->id)->assertHasNoErrors()->assertSee('révoqué');

    expect($perimetre->couvreElevage($this->veto, $this->jeu->elevage24a))->toBeFalse()
        ->and(Consentement::query()->withoutGlobalScopes()->where('elevage_id', $this->jeu->elevage24a->id)->where('type', 'references')->whereNull('revoque_le')->exists())->toBeTrue()
        ->and(DB::table('app.journal')->whereIn('action', ['accord_consentement', 'revocation_consentement'])->where('elevage_id', $this->jeu->elevage24a->id)->count())->toBe(3);
});

it('refuse un compte non vétérinaire, un doublon et l\'élevage d\'un autre éleveur', function () {
    $this->actingAs($this->jeu->eleveur24a);
    Livewire::test(Consentements::class)
        ->set('veterinaires.'.$this->jeu->elevage24a->id, (string) $this->jeu->technicien24->id)->call('accorderVeterinaire', $this->jeu->elevage24a->id)->assertHasErrors('elevage-'.$this->jeu->elevage24a->id)
        ->set('veterinaires.'.$this->jeu->elevage24a->id, (string) $this->jeu->veterinaire->id)->call('accorderVeterinaire', $this->jeu->elevage24a->id)->assertHasErrors('elevage-'.$this->jeu->elevage24a->id)
        ->call('accorderReferences', $this->jeu->elevage47->id)->assertNotFound();
});

it('ouvre la page aux éleveurs seulement', function () {
    $this->actingAs($this->jeu->eleveur24a);
    $this->get(route('compte.consentements'))->assertOk()->assertSee('Partage avec votre vétérinaire');
});
