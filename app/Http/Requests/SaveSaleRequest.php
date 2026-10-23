<?php

namespace App\Http\Requests;

use App\Concerns\DocumentLineValidationRules;
use App\Concerns\DocumentPaymentValidationRules;
use App\Models\Counterparty;
use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveSaleRequest extends FormRequest
{
    use DocumentLineValidationRules, DocumentPaymentValidationRules;

    /**
     * Get the validation rules that apply to the request: a location of the store, an optional
     * customer (execution requires one only for a debt part), at least one line, prices that
     * may stay empty while parked, the payment per method, and whether to execute right away.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $storeId = $this->user()->team_id;

        return [
            'counterparty_id' => ['nullable', 'integer', Rule::exists(Counterparty::class, 'id')->where('team_id', $storeId)],
            'location_id' => ['required', 'integer', Rule::exists(Location::class, 'id')->where('team_id', $storeId)->whereNull('archived_at')],
            ...$this->documentLineRules(),
            'lines' => ['required', 'array', 'min:1', 'max:1000'],
            ...$this->documentPaymentRules(),
            'execute' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the document header fields.
     *
     * @return array{counterparty_id: int|null, location_id: int}
     */
    public function documentHeader(): array
    {
        return [
            'counterparty_id' => $this->validated('counterparty_id') === null ? null : (int) $this->validated('counterparty_id'),
            'location_id' => (int) $this->validated('location_id'),
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
            'counterparty_id' => 'mijoz',
            'location_id' => 'joy',
            ...$this->documentLineAttributes(),
            'payments.*' => 'summa',
        ];
    }
}
