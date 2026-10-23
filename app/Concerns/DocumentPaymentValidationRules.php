<?php

namespace App\Concerns;

use App\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;

/**
 * The shared payment panel: an optional amount per payment method, planned on the document
 * and moved through the register only when it is executed.
 *
 * @mixin FormRequest
 */
trait DocumentPaymentValidationRules
{
    /**
     * Get the validation rules for the `payments` object.
     *
     * @return array<string, array<mixed>>
     */
    protected function documentPaymentRules(): array
    {
        return [
            'payments' => ['sometimes', 'array:'.implode(',', array_column(PaymentMethod::cases(), 'value'))],
            'payments.*' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999999'],
        ];
    }

    /**
     * Get the amount per payment method, leaving out empty amounts.
     *
     * @return array<string, numeric-string>
     */
    public function payments(): array
    {
        return array_map(strval(...), array_filter((array) $this->validated('payments', []), fn ($amount) => $amount !== null));
    }
}
