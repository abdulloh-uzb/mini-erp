<?php

namespace App\Http\Requests;

use App\Concerns\DocumentLineValidationRules;
use App\Concerns\DocumentPaymentValidationRules;
use App\Models\Counterparty;
use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePurchaseRequest extends FormRequest
{
    use DocumentLineValidationRules, DocumentPaymentValidationRules;

    /**
     * Get the validation rules that apply to the request: a supplier and a location of the
     * store, at least one line, prices that may stay empty until execution, the planned
     * payment per method, and whether to execute right after saving.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $storeId = $this->user()->team_id;

        return [
            'counterparty_id' => ['required', 'integer', Rule::exists(Counterparty::class, 'id')->where('team_id', $storeId)],
            'location_id' => ['required', 'integer', Rule::exists(Location::class, 'id')->where('team_id', $storeId)->whereNull('archived_at')],
            'note' => ['nullable', 'string', 'max:1000'],
            ...$this->documentLineRules(),
            'lines' => ['required', 'array', 'min:1', 'max:1000'],
            ...$this->documentPaymentRules(),
            'execute' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the document header fields.
     *
     * @return array{counterparty_id: int, location_id: int, note: string|null}
     */
    public function documentHeader(): array
    {
        return [
            'counterparty_id' => (int) $this->validated('counterparty_id'),
            'location_id' => (int) $this->validated('location_id'),
            'note' => $this->validated('note'),
        ];
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
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'counterparty_id.required' => __('Yetkazib beruvchini tanlang.'),
            'lines.required' => __("Kamida bitta mahsulot qo'shing."),
            'lines.min' => __("Kamida bitta mahsulot qo'shing."),
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
            'counterparty_id' => 'yetkazib beruvchi',
            'location_id' => 'joy',
            'note' => 'izoh',
            ...$this->documentLineAttributes(),
            'payments.*' => 'summa',
        ];
    }
}
