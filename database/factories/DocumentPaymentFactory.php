<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Document;
use App\Models\DocumentPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DocumentPayment>
 */
class DocumentPaymentFactory extends Factory
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
            'payment_method' => PaymentMethod::Cash,
            'amount' => fake()->numberBetween(1, 1000).'000.00',
        ];
    }
}
