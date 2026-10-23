<?php

namespace App\Http\Requests;

use App\Concerns\DocumentLineValidationRules;
use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveInventoryCountRequest extends FormRequest
{
    use DocumentLineValidationRules;

    /**
     * Get the validation rules that apply to the request: a location of the store, at least
     * one line, each product once, the counted blocks + pieces (zero when none were found),
     * and whether to execute right after saving.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = $this->documentLineRules(withPrice: false);

        return [
            'location_id' => ['required', 'integer', Rule::exists(Location::class, 'id')->where('team_id', $this->user()->team_id)->whereNull('archived_at')],
            'note' => ['nullable', 'string', 'max:1000'],
            ...$rules,
            'lines' => ['required', 'array', 'min:1', 'max:1000'],
            'lines.*.product_id' => [...$rules['lines.*.product_id'], 'distinct'],
            'execute' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the document header fields.
     *
     * @return array{location_id: int, note: string|null}
     */
    public function documentHeader(): array
    {
        return [
            'location_id' => (int) $this->validated('location_id'),
            'note' => $this->validated('note'),
        ];
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
            'lines.*.product_id.distinct' => __("Bu mahsulot allaqachon qo'shilgan."),
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
            'location_id' => 'joy',
            'note' => 'izoh',
            ...$this->documentLineAttributes(),
        ];
    }
}
