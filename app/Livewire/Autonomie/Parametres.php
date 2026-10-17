<?php

namespace App\Livewire\Autonomie;

use App\Livewire\Dossier\Lecture\Concerns\VoletDossier;
use App\Models\Fourrage\Elevage;
use App\Services\Dossier\SaisieRefusee;
use App\Services\ElevageCourant;
use App\Services\Referentiels\ParametresElevage;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * E16 — paramètres de l'élevage (EF-M4-07, EF-M7-03, D10) : pertes, MS par défaut, densité du vrac, marges de sécurité
 * et prix indicatifs ; la valeur de référence et sa source restent affichées, les écarts sont listés pour le
 * technicien. Une fraction se saisit en pourcentage. Écriture : convention active (ElevagePolicy::ecrireStocks, T-32).
 */
class Parametres extends Component
{
    use VoletDossier;

    /** @var array<string, string> valeur saisie par clé encodée (pourcentage pour une fraction) */
    public array $valeurs = [];

    /** @var array<string, string> */
    public array $motifs = [];

    public function mount(Elevage $elevage): void
    {
        $this->monterVolet($elevage);
    }

    public function enregistrer(string $cle, ParametresElevage $parametres): void
    {
        $elevage = $this->elevage();
        $this->authorize('ecrireStocks', $elevage);
        $ligne = collect($parametres->liste($elevage))->firstWhere('cle', $cle) ?? abort(404);
        $k = self::champ($cle);
        $saisie = str_replace(',', '.', trim($this->valeurs[$k] ?? ''));
        if (! is_numeric($saisie)) {
            $this->addError('valeurs.'.$k, 'Saisir un nombre.');

            return;
        }

        try {
            $parametres->modifier($elevage, $cle, $ligne['unite'] === 'fraction' ? (float) $saisie / 100 : (float) $saisie, $this->motifs[$k] ?? null, $this->lecteur());
            unset($this->valeurs[$k], $this->motifs[$k]);
            session()->flash('status', 'Valeur de l\'élevage enregistrée : elle s\'applique aux prochaines saisies.');
        } catch (SaisieRefusee $e) {
            $this->addError('valeurs.'.$k, $e->getMessage());
        }
    }

    public function retablir(string $cle, ParametresElevage $parametres): void
    {
        $elevage = $this->elevage();
        $this->authorize('ecrireStocks', $elevage);
        $parametres->retablir($elevage, $cle);
        session()->flash('status', 'Valeur de référence rétablie.');
    }

    /** Clé de champ sans point (Livewire lit un point comme un niveau de tableau). */
    public static function champ(string $cle): string
    {
        return str_replace('.', '__', $cle);
    }

    public function render(ParametresElevage $parametres): View
    {
        $elevage = $this->elevage();
        $groupes = ['pertes.conservation.' => 'Pertes de conservation (silos)', 'pertes.stockage.' => 'Pertes de stockage (balles, vrac)',
            'pertes.distribution.' => 'Pertes à la distribution', 'ms.defaut.' => 'Matière sèche par défaut', 'densite.' => 'Densité du vrac',
            'securite.' => 'Marges de sécurité', 'prix.' => 'Prix indicatifs'];
        $lignes = collect($parametres->liste($elevage))->groupBy(fn (array $l) => collect($groupes)->keys()->first(fn (string $p) => str_starts_with($l['cle'], $p)));

        return view('livewire.autonomie.parametres', [
            'elevage' => $elevage,
            'libelleElevage' => ElevageCourant::libelle($elevage),
            'groupes' => $groupes,
            'lignes' => $lignes,
            'ecarts' => $parametres->ecarts($elevage),
            'peutSaisir' => Gate::allows('ecrireStocks', $elevage),
            'voitEcarts' => $this->lecteur()->hasRole('technicien'),
        ])->layout('components.layouts.espace', ['titre' => 'Paramètres de l\'élevage']);
    }
}
