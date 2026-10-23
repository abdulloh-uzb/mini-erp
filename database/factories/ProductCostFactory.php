<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductCost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductCost>
 */
class ProductCostFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'team_id' => fn (array $attributes) => Product::withoutGlobalScopes()->whereKey($attributes['product_id'])->firstOrFail()->team_id,
            'average_cost' => fake()->numberBetween(1000, 30000),
        ];
    }
}
