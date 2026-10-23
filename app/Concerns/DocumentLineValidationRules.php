<?php

namespace App\Concerns;

use App\Models\Location;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * The shared document-form lines: a product, blocks + pieces, optionally a price per piece and,
 * where it varies per line, a location. Products and locations must be active and in the store.
 *
 * @mixin FormRequest
 */
trait DocumentLineValidationRules
{
    /**
     * Get the validation rules for the `lines` array.
     *
     * @return array<string, array<mixed>>
     */
    protected function documentLineRules(bool $withLocation = false, bool $withPrice = true): array
    {
        $storeId = $this->user()->team_id;

        return [
            'lines' => ['present', 'array', 'max:1000'],
            'lines.*.product_id' => [
                'required',
                'integer',
                Rule::exists(Product::class, 'id')->where('team_id', $storeId)->whereNull('archived_at'),
            ],
            ...($withLocation ? ['lines.*.location_id' => [
                'required',
                'integer',
                Rule::exists(Location::class, 'id')->where('team_id', $storeId)->whereNull('archived_at'),
            ]] : []),
            'lines.*.blocks' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'lines.*.pieces' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            ...($withPrice ? ['lines.*.price' => ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999999']] : []),
        ];
    }

    /**
     * Get the extra checks: every line has at least one piece.
     *
     * @return array<int, callable(Validator): void>
     */
    protected function documentLineChecks(): array
    {
        return [function (Validator $validator) {
            foreach ((array) $this->input('lines', []) as $index => $line) {
                if ((int) ($line['blocks'] ?? 0) + (int) ($line['pieces'] ?? 0) === 0) {
                    $validator->errors()->add("lines.$index.pieces", __('Miqdorni kiriting.'));
                }
            }
        }];
    }

    /**
     * Get the validated lines with blocks + pieces turned into pieces, using each product's
     * pieces per block, which the line keeps as its snapshot (Q28).
     *
     * @return array<int, array{product_id: int, location_id: int|null, quantity: int, pieces_per_block: int, price: string|null}>
     */
    public function documentLines(): array
    {
        /** @var array<int, array{product_id: int, location_id?: int, blocks?: int|null, pieces?: int|null, price?: string|null}> $lines */
        $lines = $this->validated('lines');
        $piecesPerBlock = Product::query()
            ->whereIn('id', array_column($lines, 'product_id'))
            ->pluck('pieces_per_block', 'id');

        return array_map(fn (array $line) => [
            'product_id' => (int) $line['product_id'],
            'location_id' => isset($line['location_id']) ? (int) $line['location_id'] : null,
            'quantity' => (int) ($line['blocks'] ?? 0) * $piecesPerBlock[$line['product_id']] + (int) ($line['pieces'] ?? 0),
            'pieces_per_block' => $piecesPerBlock[$line['product_id']],
            'price' => isset($line['price']) ? (string) $line['price'] : null,
        ], array_values($lines));
    }

    /**
     * Get the attribute names used in line errors.
     *
     * @return array<string, string>
     */
    protected function documentLineAttributes(): array
    {
        return [
            'lines.*.product_id' => 'mahsulot',
            'lines.*.location_id' => 'joy',
            'lines.*.blocks' => 'blok',
            'lines.*.pieces' => 'dona',
            'lines.*.price' => 'narx',
        ];
    }
}
