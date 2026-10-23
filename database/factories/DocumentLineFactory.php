<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentLine>
 */
class DocumentLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'document_id' => Document::factory(),
            'product_id' => fn (array $attributes) => Product::factory()->create([
                'team_id' => Document::withoutGlobalScopes()->whereKey($attributes['document_id'])->firstOrFail()->team_id,
            ]),
            'location_id' => null,
            'quantity' => fake()->numberBetween(1, 100),
            'pieces_per_block' => fn (array $attributes) => Product::withoutGlobalScopes()->whereKey($attributes['product_id'])->firstOrFail()->pieces_per_block,
            'price' => fake()->numberBetween(2, 30) * 1000,
            'unit_cost' => null,
        ];
    }
}
