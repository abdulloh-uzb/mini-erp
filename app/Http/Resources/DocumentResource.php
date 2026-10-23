<?php

namespace App\Http\Resources;

use App\Enums\DebtSide;
use App\Enums\DocumentKind;
use App\Enums\StorePermission;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\DocumentPayment;
use App\Models\OpeningDebt;
use App\Models\OpeningLoan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

/**
 * A document as the shared document form shows it: header, audit (created by / executed by)
 * and lines. Load `lines.product`, `lines.location.parent`, `author` and `executor` first;
 * `location.parent`, `counterparty`, `payments`, `openingLoans.counterparty` and
 * `openingDebts.counterparty` are included
 * when loaded, as is `destinationLocation.parent` (transfers). Returns carry their source
 * document, refund method and source lines. Where prices are costs
 * (purchases), users who may not view costs get neither prices nor payments. Inventory count
 * lines carry expected quantities only for users who may execute counts (blind count, D6),
 * and their average cost and difference value only when those users may also view costs.
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
        $canViewCosts = $request->user()->can(StorePermission::CostsView->value);
        $showPrices = ! $this->kind->pricesAreCosts() || $canViewCosts;
        $showExpected = $this->kind === DocumentKind::InventoryCount && $request->user()->can(StorePermission::InventoryCountExecute->value);

        return [
            'id' => $this->id,
            'kind' => $this->kind,
            'number' => $this->number,
            'status' => $this->status,
            'location_id' => $this->location_id,
            'location_name' => $this->whenLoaded('location', fn () => $this->location?->fullName()),
            'destination_location_id' => $this->destination_location_id,
            'destination_location_name' => $this->whenLoaded('destinationLocation', fn () => $this->destinationLocation?->fullName()),
            'counterparty_id' => $this->counterparty_id,
            'counterparty_name' => $this->whenLoaded('counterparty', fn () => $this->counterparty?->name),
            'source_document_id' => $this->source_document_id,
            'refund_method' => $this->refund_method,
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
                'source_line_id' => $line->source_line_id,
                'quantity' => $line->quantity,
                'pieces_per_block' => $line->pieces_per_block,
                ...($showPrices ? ['price' => $line->price] : []),
                ...($showExpected ? ['expected_quantity' => $line->expected_quantity] : []),
                ...($showExpected && $canViewCosts ? ['unit_cost' => $line->unit_cost, 'difference_value' => $line->difference_value] : []),
            ])->all(),
            'payments' => $this->when($showPrices && $this->relationLoaded('payments'), fn () => $this->payments
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
            'customer_debts' => $this->openingDebtRows(DebtSide::Customer),
            'supplier_debts' => $this->openingDebtRows(DebtSide::Supplier),
        ];
    }

    /**
     * Get the opening balance's debts on one side, once the debts are loaded.
     *
     * @return MissingValue|array<int, array{id: int, counterparty_id: int, counterparty_name: string, amount: numeric-string}>
     */
    private function openingDebtRows(DebtSide $side): MissingValue|array
    {
        return $this->whenLoaded('openingDebts', fn () => $this->openingDebts->where('side', $side)->map(fn (OpeningDebt $debt) => [
            'id' => $debt->id,
            'counterparty_id' => $debt->counterparty_id,
            'counterparty_name' => $debt->counterparty->name,
            'amount' => $debt->amount,
        ])->values()->all());
    }
}
