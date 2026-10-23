<?php

namespace App\Http\Requests;

use App\Concerns\DocumentLineValidationRules;
use App\Concerns\DocumentPaymentValidationRules;
use App\Enums\DebtSide;
use App\Enums\LoanDirection;
use App\Models\Counterparty;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveOpeningBalanceRequest extends FormRequest
{
    use DocumentLineValidationRules, DocumentPaymentValidationRules;

    /**
     * Get the validation rules that apply to the request. The stock section's lines each carry
     * a location and a cost per piece, which may stay empty until execution. The Kassa section
     * has an optional amount per payment method; the Yetkazib beruvchi qarzlari section lists
     * what the store owes each supplier, once per supplier; the Qarzlar section lists the open
     * loans.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $money = ['numeric', 'decimal:0,2', 'max:9999999999999999'];

        return [
            'note' => ['nullable', 'string', 'max:1000'],
            ...$this->documentLineRules(withLocation: true),
            ...$this->documentPaymentRules(),
            'loans' => ['sometimes', 'array', 'max:500'],
            'loans.*.direction' => ['required', Rule::enum(LoanDirection::class)],
            'loans.*.counterparty_id' => ['required', 'integer', Rule::exists(Counterparty::class, 'id')->where('team_id', $this->user()->team_id)],
            'loans.*.principal' => ['required', ...$money, 'gt:0'],
            'loans.*.due_date' => ['nullable', 'date'],
            'loans.*.note' => ['nullable', 'string', 'max:1000'],
            'supplier_debts' => ['sometimes', 'array', 'max:500'],
            'supplier_debts.*.counterparty_id' => ['required', 'integer', 'distinct', Rule::exists(Counterparty::class, 'id')->where('team_id', $this->user()->team_id)],
            'supplier_debts.*.amount' => ['required', ...$money, 'gt:0'],
        ];
    }

    /**
     * Get the open loans as opening loan rows.
     *
     * @return array<int, array{direction: string, counterparty_id: int, principal: string, due_date: string|null, note: string|null}>
     */
    public function openingLoans(): array
    {
        return array_map(fn (array $loan) => [
            'direction' => $loan['direction'],
            'counterparty_id' => (int) $loan['counterparty_id'],
            'principal' => (string) $loan['principal'],
            'due_date' => $loan['due_date'] ?? null,
            'note' => $loan['note'] ?? null,
        ], array_values((array) $this->validated('loans', [])));
    }

    /**
     * Get the supplier debts as opening debt rows.
     *
     * @return array<int, array{side: DebtSide, counterparty_id: int, amount: string}>
     */
    public function openingSupplierDebts(): array
    {
        return array_map(fn (array $debt) => [
            'side' => DebtSide::Supplier,
            'counterparty_id' => (int) $debt['counterparty_id'],
            'amount' => (string) $debt['amount'],
        ], array_values((array) $this->validated('supplier_debts', [])));
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return $this->documentLineChecks();
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'note' => 'izoh',
            ...$this->documentLineAttributes(),
            'lines.*.price' => 'tannarx',
            'payments.*' => 'summa',
            'loans.*.direction' => 'turi',
            'loans.*.counterparty_id' => 'kontragent',
            'loans.*.principal' => 'asosiy summa',
            'loans.*.due_date' => 'qaytarish muddati',
            'loans.*.note' => 'izoh',
            'supplier_debts.*.counterparty_id' => 'yetkazib beruvchi',
            'supplier_debts.*.amount' => 'qarz summasi',
        ];
    }
}
