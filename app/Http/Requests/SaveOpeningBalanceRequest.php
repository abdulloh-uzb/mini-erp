<?php

namespace App\Http\Requests;

use App\Concerns\DocumentLineValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveOpeningBalanceRequest extends FormRequest
{
    use DocumentLineValidationRules;

    /**
     * Get the validation rules that apply to the request. The stock section's lines each carry
     * a location and a cost per piece, which may stay empty until execution.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'note' => ['nullable', 'string', 'max:1000'],
            ...$this->documentLineRules(withLocation: true),
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
        ];
    }
}
