<?php

namespace Database\Factories;

use App\Enums\DebtSide;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\OpeningDebt;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningDebt>
 */
class OpeningDebtFactory extends Factory
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
            'counterparty_id' => fn (array $attributes) => Counterparty::factory()->create([
                'team_id' => Document::withoutGlobalScopes()->whereKey($attributes['document_id'])->firstOrFail()->team_id,
            ]),
            'side' => DebtSide::Supplier,
            'amount' => fake()->numberBetween(1, 1000).'000.00',
        ];
    }
}
