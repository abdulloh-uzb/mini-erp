<?php

namespace App\Http\Requests;

use App\Support\Phone;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveCounterpartyRequest extends FormRequest
{
    /**
     * Prepare the data for validation. The phone is optional: the input mask's bare "+998"
     * prefix counts as no phone.
     */
    protected function prepareForValidation(): void
    {
        $phone = $this->string('phone')->value();

        $this->merge(['phone' => Phone::isBlank($phone) ? null : Phone::normalize($phone)]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'phone' => Phone::optionalRules(),
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
            'name' => 'nomi',
            'note' => 'izoh',
        ];
    }
}
