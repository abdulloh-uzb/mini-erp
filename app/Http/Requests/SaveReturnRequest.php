<?php

namespace App\Http\Requests;

use App\Actions\Documents\ReturnedQuantities;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Support\Quantity;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * A sales return or supplier return draft. Its source is the executed sale or purchase it is
 * created from (fixed once the draft exists); each line names a source line and the blocks +
 * pieces given back, never more than is still returnable. Product, block size and price come
 * from the source line.
 */
class SaveReturnRequest extends FormRequest
{
    /**
     * The source document, once resolved.
     */
    private ?Document $source = null;

    /**
     * The source document's lines by id, once loaded.
     *
     * @var Collection<int, DocumentLine>|null
     */
    private ?Collection $sourceLines = null;

    /**
     * Get the validation rules that apply to the request: the source (new drafts only), a
     * location of the store, the refund method, the lines and whether to execute right after
     * saving.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $storeId = $this->user()->team_id;

        return [
            ...($this->draft() === null ? ['source_document_id' => [
                'required',
                'integer',
                Rule::exists(Document::class, 'id')
                    ->where('team_id', $storeId)
                    ->where('kind', $this->kind()->returnSource()->value)
                    ->where('status', DocumentStatus::Executed->value),
            ]] : []),
            'location_id' => ['required', 'integer', Rule::exists(Location::class, 'id')->where('team_id', $storeId)->whereNull('archived_at')],
            'refund_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'max:1000'],
            'lines.*.source_line_id' => ['required', 'integer', 'distinct'],
            'lines.*.blocks' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'lines.*.pieces' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'execute' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Get the "after" validation callables: every line belongs to the source, gives back no
     * more than is still returnable, and at least one line gives something back.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $sourceLines = $this->sourceLines();
            $returned = app(ReturnedQuantities::class)->handle($this->source());
            $total = 0;

            foreach ((array) $this->input('lines') as $index => $line) {
                $sourceLine = $sourceLines->get((int) $line['source_line_id']);

                if ($sourceLine === null) {
                    $validator->errors()->add("lines.$index.source_line_id", __("Bu mahsulot manba hujjatda yo'q."));

                    continue;
                }

                $quantity = $this->pieces($line, $sourceLine->pieces_per_block);
                $returnable = $sourceLine->quantity - ($returned[$sourceLine->id] ?? 0);
                $total += $quantity;

                if ($quantity > $returnable) {
                    $validator->errors()->add("lines.$index.pieces", __("Ko'pi bilan :quantity qaytarish mumkin", [
                        'quantity' => Quantity::format($returnable, $sourceLine->pieces_per_block),
                    ]));
                }
            }

            if ($total === 0) {
                $validator->errors()->add('lines', __('Qaytariladigan miqdorni kiriting.'));
            }
        }];
    }

    /**
     * Get the executed sale or purchase the return is created from.
     */
    public function source(): Document
    {
        return $this->source ??= $this->draft()->source
            ?? Document::query()->findOrFail((int) $this->input('source_document_id'));
    }

    /**
     * Get the document header fields: the counterparty is the source's.
     *
     * @return array{source_document_id: int, counterparty_id: int|null, location_id: int, refund_method: string|null, note: string|null}
     */
    public function documentHeader(): array
    {
        return [
            'source_document_id' => $this->source()->id,
            'counterparty_id' => $this->source()->counterparty_id,
            'location_id' => (int) $this->validated('location_id'),
            'refund_method' => $this->validated('refund_method'),
            'note' => $this->validated('note'),
        ];
    }

    /**
     * Get the lines in the source's order, product, block size and price copied from each
     * source line.
     *
     * @return array<int, array{product_id: int, location_id: null, source_line_id: int, quantity: int, pieces_per_block: int, price: string|null}>
     */
    public function documentLines(): array
    {
        /** @var array<int, array{source_line_id: int, blocks?: int|null, pieces?: int|null}> $lines */
        $lines = $this->validated('lines');
        $sourceLines = $this->sourceLines();

        return collect($lines)
            ->sortBy(fn (array $line) => (int) $line['source_line_id'])
            ->map(function (array $line) use ($sourceLines) {
                $sourceLine = $sourceLines->get((int) $line['source_line_id']);

                return [
                    'product_id' => $sourceLine->product_id,
                    'location_id' => null,
                    'source_line_id' => $sourceLine->id,
                    'quantity' => $this->pieces($line, $sourceLine->pieces_per_block),
                    'pieces_per_block' => $sourceLine->pieces_per_block,
                    'price' => $sourceLine->price,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Get the custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_document_id.required' => __('Qaytariladigan hujjatni tanlang.'),
            'source_document_id.exists' => __('Qaytariladigan hujjatni tanlang.'),
            'lines.required' => __('Qaytariladigan miqdorni kiriting.'),
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
            'refund_method' => "to'lov usuli",
            'note' => 'izoh',
            'lines.*.blocks' => 'blok',
            'lines.*.pieces' => 'dona',
        ];
    }

    /**
     * Get the return being edited, if any.
     */
    private function draft(): ?Document
    {
        $document = $this->route('document');

        return $document instanceof Document ? $document : null;
    }

    /**
     * Get the kind of return the route saves.
     */
    private function kind(): DocumentKind
    {
        return $this->routeIs('supplier-returns.*') ? DocumentKind::SupplierReturn : DocumentKind::SalesReturn;
    }

    /**
     * Get the source's lines by id.
     *
     * @return Collection<int, DocumentLine>
     */
    private function sourceLines(): Collection
    {
        return $this->sourceLines ??= $this->source()->lines()->get()->keyBy('id');
    }

    /**
     * Get a line's blocks + pieces in pieces.
     *
     * @param  array{blocks?: int|string|null, pieces?: int|string|null}  $line
     */
    private function pieces(array $line, int $piecesPerBlock): int
    {
        return (int) ($line['blocks'] ?? 0) * $piecesPerBlock + (int) ($line['pieces'] ?? 0);
    }
}
