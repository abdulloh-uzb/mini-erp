<?php

namespace Database\Factories;

use App\Enums\LoanDirection;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\OpeningLoan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OpeningLoan>
 */
class OpeningLoanFactory extends Factory
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
            'direction' => LoanDirection::Given,
            'principal' => fake()->numberBetween(1, 1000).'000.00',
            'due_date' => null,
            'note' => null,
        ];
    }
}
