<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveProductRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Product|null $product */
        $product = $this->route('product');

        return [
            'name' => ['required', 'string', 'max:255'],
            'barcode' => [
                'nullable',
                'string',
                'max:64',
                Rule::unique(Product::class)
                    ->where('team_id', $this->user()->team_id)
                    ->ignore($product),
            ],
            'pieces_per_block' => ['required', 'integer', 'min:1', 'max:10000'],
            'sale_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999999'],
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
            'barcode' => 'shtrix-kod',
            'pieces_per_block' => 'blokdagi dona soni',
            'sale_price' => 'sotuv narxi',
        ];
    }
}
