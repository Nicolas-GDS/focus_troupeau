<?php

namespace Database\Factories\App;

use App\Models\App\Consentement;
use App\Models\App\User;
use App\Models\Fourrage\Elevage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consentement>
 */
class ConsentementFactory extends Factory
{
    protected $model = Consentement::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'elevage_id' => Elevage::factory(),
            'type' => Consentement::TYPE_REFERENCES,
            'accorde_le' => now(),
        ];
    }

    public function veterinaire(User $veterinaire): static
    {
        return $this->state(fn () => [
            'type' => Consentement::TYPE_VETERINAIRE,
            'veterinaire_user_id' => $veterinaire->id,
        ]);
    }

    public function revoque(): static
    {
        return $this->state(fn () => ['revoque_le' => now()]);
    }
}
