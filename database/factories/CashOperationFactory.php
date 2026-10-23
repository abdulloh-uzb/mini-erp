<?php

namespace Database\Factories;

use App\Enums\CashOperationType;
use App\Enums\PaymentMethod;
use App\Models\CashOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * An operation row alone, without moving the stored balance. Use the cash register when the
 * balance should follow.
 *
 * @extends Factory<CashOperation>
 */
class CashOperationFactory extends Factory
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
            'type' => CashOperationType::Income,
            'direction' => fn (array $attributes) => $attributes['type']->direction(),
            'payment_method' => PaymentMethod::Cash,
            'amount' => fake()->numberBetween(1, 1000).'000.00',
        ];
    }
}
