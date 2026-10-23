<?php

namespace Database\Factories;

use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBalance>
 */
class StockBalanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'location_id' => Location::factory(),
            'team_id' => fn (array $attributes) => Location::withoutGlobalScopes()->whereKey($attributes['location_id'])->firstOrFail()->team_id,
            'product_id' => fn (array $attributes) => Product::factory()->create(['team_id' => $attributes['team_id']]),
            'quantity' => fake()->numberBetween(1, 100),
        ];
    }
}
