<?php

namespace Database\Factories;

use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Team>
 */
class TeamFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'blocked_at' => null,
        ];
    }

    /**
     * Indicate that the super-admin has blocked the store.
     */
    public function blocked(): static
    {
        return $this->state(fn (array $attributes) => [
            'blocked_at' => now(),
        ]);
    }
}
