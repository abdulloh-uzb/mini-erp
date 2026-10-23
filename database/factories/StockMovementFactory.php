<?php

namespace Database\Factories;

use App\Models\DocumentLine;
use App\Models\StockMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_line_id' => DocumentLine::factory(),
            'product_id' => fn (array $attributes) => DocumentLine::query()->whereKey($attributes['document_line_id'])->firstOrFail()->product_id,
            'team_id' => fn (array $attributes) => DocumentLine::query()->whereKey($attributes['document_line_id'])->firstOrFail()->document->team_id,
            'location_id' => fn (array $attributes) => DocumentLine::query()->whereKey($attributes['document_line_id'])->firstOrFail()->location_id,
            'quantity' => fake()->numberBetween(1, 100),
        ];
    }
}
