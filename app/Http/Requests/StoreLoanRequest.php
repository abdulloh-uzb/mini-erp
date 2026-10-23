<?php

namespace App\Http\Requests;

use App\Enums\LoanDirection;
use App\Enums\PaymentMethod;
use App\Models\Counterparty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLoanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request: a loan given or received, with a
     * counterparty of the store, a principal above zero, its method and an optional due date.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::enum(LoanDirection::class)],
            'counterparty_id' => ['required', 'integer', Rule::exists(Counterparty::class, 'id')->where('team_id', $this->user()->team_id)],
            'principal' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999999'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'due_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'direction' => 'turi',
            'counterparty_id' => 'kontragent',
            'principal' => 'asosiy summa',
            'payment_method' => "to'lov usuli",
            'due_date' => 'qaytarish muddati',
            'note' => 'izoh',
        ];
    }
}
