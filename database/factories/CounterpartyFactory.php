<?php

namespace Database\Factories;

use App\Models\Counterparty;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Counterparty>
 */
class CounterpartyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'name' => fake()->name(),
            'phone' => '+99890'.fake()->unique()->numerify('#######'),
            'note' => null,
        ];
    }
}
