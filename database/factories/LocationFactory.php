<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Location>
 */
class LocationFactory extends Factory
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
            'parent_id' => null,
            'name' => fake()->unique()->city().' ombori',
            'archived_at' => null,
        ];
    }

    /**
     * Indicate that the location is a sub-warehouse inside the given warehouse.
     */
    public function inside(Location $warehouse): static
    {
        return $this->state(fn (array $attributes) => [
            'team_id' => $warehouse->team_id,
            'parent_id' => $warehouse->id,
        ]);
    }

    /**
     * Indicate that the location has been archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes) => [
            'archived_at' => now(),
        ]);
    }
}
