<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\CashBalance;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CashBalance>
 */
class CashBalanceFactory extends Factory
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
            'payment_method' => PaymentMethod::Cash,
            'amount' => fake()->numberBetween(1, 1000).'000.00',
        ];
    }
}
