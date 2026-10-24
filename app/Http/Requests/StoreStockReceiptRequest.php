<?php

namespace App\Http\Requests;

use App\Enums\PaymentMethod;
use App\Models\Counterparty;
use App\Models\Location;
use App\Models\Product;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The Qoldiqlar quick receipt: one product arriving at one location from a supplier, with its
 * cost per piece, an optional new sale price and an optional payment made right away.
 */
class StoreStockReceiptRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $storeId = $this->user()->team_id;

        return [
            'product_id' => ['required', 'integer', Rule::exists(Product::class, 'id')->where('team_id', $storeId)->whereNull('archived_at')],
            'location_id' => ['required', 'integer', Rule::exists(Location::class, 'id')->where('team_id', $storeId)->whereNull('archived_at')],
            'counterparty_id' => ['required', 'integer', Rule::exists(Counterparty::class, 'id')->where('team_id', $storeId)],
            'blocks' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'pieces' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999999'],
            'sale_price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999999'],
            'paid_amount' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999999'],
            'payment_method' => ['required_with:paid_amount', Rule::enum(PaymentMethod::class)],
        ];
    }

    /**
     * Get the extra checks: at least one piece arrives and the payment does not exceed the total.
     *
     * @return array<int, callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            if ($this->quantity() === 0) {
                $validator->errors()->add('pieces', __('Miqdorni kiriting.'));

                return;
            }

            if (bccomp($this->paidAmount(), bcmul((string) $this->quantity(), (string) $this->validated('price'), 2), 2) > 0) {
                $validator->errors()->add('paid_amount', __("To'lov summasi jamidan oshib ketdi."));
            }
        }];
    }

    /**
     * Get the pieces arriving: blocks at the product's pieces per block plus loose pieces.
     */
    public function quantity(): int
    {
        $piecesPerBlock = (int) Product::query()->whereKey($this->input('product_id'))->value('pieces_per_block');

        return (int) $this->input('blocks', 0) * $piecesPerBlock + (int) $this->input('pieces', 0);
    }

    /**
     * Get the amount paid right away, zero when none.
     *
     * @return numeric-string
     */
    public function paidAmount(): string
    {
        return bcadd((string) ($this->validated('paid_amount') ?? '0'), '0', 2);
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
            'price.required' => __('Tannarxni kiriting.'),
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
            'product_id' => 'mahsulot',
            'location_id' => 'joy',
            'counterparty_id' => 'yetkazib beruvchi',
            'blocks' => 'blok',
            'pieces' => 'dona',
            'price' => 'tannarx',
            'sale_price' => 'sotuv narxi',
            'paid_amount' => "to'langan summa",
            'payment_method' => "to'lov usuli",
        ];
    }
}
