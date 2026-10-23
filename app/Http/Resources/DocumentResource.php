<?php

namespace App\Http\Resources;

use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\DocumentPayment;
use App\Models\OpeningLoan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A document as the shared document form shows it: header, audit (created by / executed by)
 * and lines. Load `lines.product`, `lines.location.parent`, `author` and `executor` first;
 * `payments` and `openingLoans.counterparty` are included when loaded.
 *
 * @mixin Document
 */
class DocumentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'number' => $this->number,
            'status' => $this->status,
            'note' => $this->note,
            'author' => $this->author->name,
            'executor' => $this->executor?->name,
            'created_at' => $this->created_at?->toIso8601String(),
            'executed_at' => $this->executed_at?->toIso8601String(),
            'lines' => $this->lines->map(fn (DocumentLine $line) => [
                'id' => $line->id,
                'product_id' => $line->product_id,
                'product_name' => $line->product->name,
                'location_id' => $line->location_id,
                'location_name' => $line->location?->fullName(),
                'quantity' => $line->quantity,
                'pieces_per_block' => $line->pieces_per_block,
                'price' => $line->price,
            ])->all(),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments
                ->mapWithKeys(fn (DocumentPayment $payment) => [$payment->payment_method->value => $payment->amount])
                ->all()),
            'loans' => $this->whenLoaded('openingLoans', fn () => $this->openingLoans->map(fn (OpeningLoan $loan) => [
                'id' => $loan->id,
                'direction' => $loan->direction,
                'counterparty_id' => $loan->counterparty_id,
                'counterparty_name' => $loan->counterparty->name,
                'principal' => $loan->principal,
                'due_date' => $loan->due_date?->toDateString(),
                'note' => $loan->note,
            ])->all()),
        ];
    }
}
