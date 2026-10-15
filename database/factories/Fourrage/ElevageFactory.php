<?php

namespace Database\Factories\Fourrage;

use App\Models\Fourrage\Elevage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Élevage synthétique (aucune donnée réelle) ; le GDS suit le département, comme la reprise du § 5.2.
 *
 * @extends Factory<Elevage>
 */
class ElevageFactory extends Factory
{
    protected $model = Elevage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $departement = fake()->randomElement(['24', '47']);

        return [
            'code_pseudo' => 'TST-'.fake()->unique()->numerify('#####'),
            'departement' => $departement,
            'gds_id' => (int) $departement,
        ];
    }

    public function gds(int $gdsId): static
    {
        return $this->state(fn () => [
            'departement' => (string) $gdsId,
            'gds_id' => $gdsId,
        ]);
    }
}
