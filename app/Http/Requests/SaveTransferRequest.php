<?php

namespace App\Http\Requests;

use App\Concerns\DocumentLineValidationRules;
use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveTransferRequest extends FormRequest
{
    use DocumentLineValidationRules;

    /**
     * Get the validation rules that apply to the request: two different locations of the store,
     * at least one line without prices, and whether to execute right after saving.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $location = Rule::exists(Location::class, 'id')->where('team_id', $this->user()->team_id)->whereNull('archived_at');

        return [
            'location_id' => ['required', 'integer', $location],
            'destination_location_id' => ['required', 'integer', $location, 'different:location_id'],
            'note' => ['nullable', 'string', 'max:1000'],
            ...$this->documentLineRules(withPrice: false),
            'lines' => ['required', 'array', 'min:1', 'max:1000'],
            'execute' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the document header fields.
     *
     * @return array{location_id: int, destination_location_id: int, note: string|null}
     */
    public function documentHeader(): array
    {
        return [
            'location_id' => (int) $this->validated('location_id'),
            'destination_location_id' => (int) $this->validated('destination_location_id'),
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
            'destination_location_id.different' => __('Bir xil joy tanlangan'),
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
            'location_id' => 'qayerdan',
            'destination_location_id' => 'qayerga',
            'note' => 'izoh',
            ...$this->documentLineAttributes(),
        ];
    }
}
