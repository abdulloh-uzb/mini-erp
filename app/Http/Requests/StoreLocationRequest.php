<?php

namespace App\Http\Requests;

use App\Models\Location;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLocationRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request. A sub-warehouse's parent must be an
     * active warehouse of the same store, so locations nest one level only.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists(Location::class, 'id')
                    ->where('team_id', $this->user()->team_id)
                    ->whereNull('parent_id')
                    ->whereNull('archived_at'),
            ],
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'parent_id.exists' => __("Ichki ombor faqat omborning ichida bo'lishi mumkin."),
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
            'parent_id' => 'ombor',
        ];
    }
}
