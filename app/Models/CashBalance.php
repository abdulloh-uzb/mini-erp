<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use App\Enums\PaymentMethod;
use Database\Factories\CashBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Money in the register for one payment method. Written only by the cash register module,
 * which locks the row for every change, so a cash-out can never take it below zero (D11).
 *
 * @property int $id
 * @property int $team_id
 * @property PaymentMethod $payment_method
 * @property numeric-string $amount
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'payment_method', 'amount'])]
class CashBalance extends Model
{
    /** @use HasFactory<CashBalanceFactory> */
    use BelongsToStore, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payment_method' => PaymentMethod::class,
            'amount' => 'decimal:2',
        ];
    }
}
