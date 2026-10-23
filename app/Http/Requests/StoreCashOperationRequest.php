<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\ExpenseCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashOperationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request: an expense or income from the
     * categories the user may use (D8), an amount above zero, a method, and a note when the
     * category needs one (_Boshqa_).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'expense_category_id' => [
                'required',
                'integer',
                Rule::in(ExpenseCategory::query()->usableBy($this->user())->pluck('id')),
            ],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999999'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'note' => [
                Rule::requiredIf(fn () => (bool) $this->category()?->requires_note),
                'nullable',
                'string',
                'max:1000',
            ],
        ];
    }

    /**
     * Get the chosen category, once it is known to be one the user may use.
     */
    public function category(): ?ExpenseCategory
    {
        return ExpenseCategory::query()->usableBy($this->user())->find($this->integer('expense_category_id'));
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'expense_category_id.in' => __('Tanlangan xarajat turi topilmadi.'),
            'note.required' => __('Bu xarajat turi uchun izoh kiriting.'),
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
            'expense_category_id' => 'xarajat turi',
            'amount' => 'summa',
            'payment_method' => "to'lov usuli",
            'note' => 'izoh',
        ];
    }
}
