<?php

namespace Database\Factories\App;

use App\Models\App\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    protected static ?string $password;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'email' => fake()->unique()->safeEmail(),
            'nom' => fake()->lastName(),
            'prenom' => fake()->firstName(),
            'password' => static::$password ??= Hash::make('mot-de-passe-de-test'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Compte d'agent GDS connecté par Entra ID : sans mot de passe local (EF-M0-04).
     */
    public function entra(): static
    {
        return $this->state(fn () => [
            'password' => null,
            'entra_oid' => (string) Str::uuid(),
        ]);
    }

    public function inactif(): static
    {
        return $this->state(fn () => ['actif' => false]);
    }
}
