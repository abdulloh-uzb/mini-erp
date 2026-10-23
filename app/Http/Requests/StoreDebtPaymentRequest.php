<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Counterparty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDebtPaymentRequest extends FormRequest
{
    /**
     * Get the validation rules for a debt payment: a counterparty of the store, an amount above
     * zero, a method and an optional note. The debt ledger checks the amount against the debt.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'counterparty_id' => ['required', 'integer', Rule::exists(Counterparty::class, 'id')->where('team_id', $this->user()->team_id)],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999999'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get the chosen counterparty.
     */
    public function counterparty(): Counterparty
    {
        return Counterparty::query()->findOrFail($this->integer('counterparty_id'));
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'counterparty_id' => 'kontragent',
            'amount' => 'summa',
            'payment_method' => "to'lov usuli",
            'note' => 'izoh',
        ];
    }
}
