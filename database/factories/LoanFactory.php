<?php

namespace Database\Factories;

use App\Enums\LoanDirection;
use App\Models\Counterparty;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A loan row alone, as an opening loan becomes one: no cash operation is recorded. Use the
 * loan ledger when the register should move.
 *
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'team_id' => fn (array $attributes) => User::query()->whereKey($attributes['author_id'])->firstOrFail()->team_id,
            'counterparty_id' => fn (array $attributes) => Counterparty::factory()->create(['team_id' => $attributes['team_id']]),
            'direction' => LoanDirection::Given,
            'principal' => fake()->numberBetween(1, 1000).'000.00',
            'due_date' => null,
            'note' => null,
        ];
    }
}
