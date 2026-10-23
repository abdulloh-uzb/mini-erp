<?php

namespace Database\Factories;

use App\Enums\DebtEntryType;
use App\Enums\DebtSide;
use App\Models\Counterparty;
use App\Models\DebtEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * By default an opening supplier debt owed to a new counterparty of the author's store.
 *
 * @extends Factory<DebtEntry>
 */
class DebtEntryFactory extends Factory
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
            'side' => DebtSide::Supplier,
            'type' => DebtEntryType::OpeningBalance,
            'amount' => fake()->numberBetween(1, 1000).'000.00',
        ];
    }
}
