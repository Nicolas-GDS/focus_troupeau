<?php

namespace Database\Factories\App;

use App\Models\App\Convention;
use App\Models\Fourrage\Elevage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Convention>
 */
class ConventionFactory extends Factory
{
    protected $model = Convention::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'elevage_id' => Elevage::factory(),
            'gds_id' => fn (array $attributs) => Elevage::query()->withoutGlobalScopes()->findOrFail($attributs['elevage_id'])->gds_id,
            'date_debut' => now()->subMonths(6)->toDateString(),
            'date_fin' => now()->addMonths(6)->toDateString(),
            'statut' => Convention::STATUT_ACTIVE,
        ];
    }

    public function echue(): static
    {
        return $this->state(fn () => [
            'date_debut' => now()->subYears(2)->toDateString(),
            'date_fin' => now()->subMonth()->toDateString(),
            'statut' => Convention::STATUT_ECHUE,
        ]);
    }
}
