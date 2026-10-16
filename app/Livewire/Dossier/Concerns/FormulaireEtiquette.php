<?php

namespace App\Livewire\Dossier\Concerns;

use App\Domain\Dossier\ApportEtiquette;
use App\Models\Dossier\EtiquetteAliment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Formulaire d'une étiquette (EF-M1-06), commun à la saisie de visite et au volet Minéraux et aliments (E08) :
 * nature, libellé tel que sur le sac, dose, date limite, composition par kg de produit.
 */
trait FormulaireEtiquette
{
    public string $nature = 'mineral';

    public string $libelle = '';

    public ?string $dose = null;

    public ?string $dateLimite = null;

    public string $modeDistribution = '';

    /** @var list<array{code: string, valeur: string|null, unite: string}> */
    public array $composition = [['code' => '', 'valeur' => null, 'unite' => 'mg/kg']];

    public function ajouterLigne(): void
    {
        $this->composition[] = ['code' => '', 'valeur' => null, 'unite' => 'mg/kg'];
    }

    public function retirerLigne(int $index): void
    {
        unset($this->composition[$index]);
        $this->composition = array_values($this->composition);
    }

    /**
     * Valide la saisie ; renvoie les données de l'étiquette et sa composition (lignes complètes seulement).
     *
     * @return array{donnees: array<string, mixed>, composition: array<string, array{valeur: float, unite: string}>}
     */
    protected function etiquetteSaisie(): array
    {
        $this->validate([
            'nature' => ['required', Rule::in(EtiquetteAliment::NATURES)],
            'libelle' => ['required', 'string', 'max:200'],
            'dose' => ['nullable', 'numeric', 'min:0'],
            'dateLimite' => ['nullable', 'date'],
            'modeDistribution' => ['nullable', 'string', 'max:200'],
            'composition.*.code' => ['nullable', 'string', Rule::in(DB::table('ref.parametre')->pluck('code')->map(fn ($v) => (string) $v)->all())],
            'composition.*.valeur' => ['nullable', 'numeric', 'min:0'],
            'composition.*.unite' => ['required', Rule::in(ApportEtiquette::unitesAdmises())],
        ], [], ['libelle' => 'libellé', 'dateLimite' => 'date limite', 'composition.*.code' => 'élément', 'composition.*.valeur' => 'teneur']);

        $composition = [];
        foreach ($this->composition as $ligne) {
            if ($ligne['code'] !== '' && ($v = self::nombreSaisi($ligne['valeur'])) !== null) {
                $composition[$ligne['code']] = ['valeur' => $v, 'unite' => $ligne['unite']];
            }
        }

        return [
            'donnees' => [
                'nature' => $this->nature,
                'libelle_eleveur' => trim($this->libelle),
                'dose_g_par_animal_jour' => self::nombreSaisi($this->dose),
                'date_limite' => $this->dateLimite ?: null,
                'mode_distribution' => trim($this->modeDistribution) === '' ? null : trim($this->modeDistribution),
            ],
            'composition' => $composition,
        ];
    }

    protected function viderFormulaireEtiquette(): void
    {
        $this->reset('libelle', 'dose', 'dateLimite', 'modeDistribution', 'composition');
    }

    /** @return array<string, string> */
    public static function natures(): array
    {
        return EtiquetteAliment::LIBELLES_NATURES;
    }

    /** @return array{elements: Collection<string, string>, unites: list<string>} */
    protected static function referentielsEtiquette(): array
    {
        return [
            'elements' => DB::table('ref.parametre')->whereIn('famille_code', ['MINERAL_MAJEUR', 'OLIGO'])->orderBy('ordre')->pluck('libelle', 'code'),
            'unites' => ApportEtiquette::unitesAdmises(),
        ];
    }

    private static function nombreSaisi(mixed $valeur): ?float
    {
        return $valeur === null || $valeur === '' ? null : (float) str_replace(',', '.', (string) $valeur);
    }
}
