<?php

namespace App\Actions\Documents;

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\DocumentLine;
use Illuminate\Support\Facades\DB;

/**
 * The previous price (Q18, ADR-0004): for each product, the price on the store's most recently
 * executed document line of the same kind. New lines pre-fill from it (sale lines only when the
 * product has no sale price, ADR-0007); a product never bought or sold has none.
 */
class PreviousPrices
{
    /**
     * Get the previous price per product id.
     *
     * @return array<int, numeric-string>
     */
    public function handle(int $storeId, DocumentKind $kind): array
    {
        $ranked = DocumentLine::query()
            ->join('documents', 'documents.id', '=', 'document_lines.document_id')
            ->where('documents.team_id', $storeId)
            ->where('documents.kind', $kind)
            ->where('documents.status', DocumentStatus::Executed)
            ->whereNotNull('document_lines.price')
            ->select(['document_lines.product_id', 'document_lines.price'])
            ->selectRaw('row_number() over (partition by document_lines.product_id order by documents.executed_at desc, documents.id desc, document_lines.id desc) as position');

        return DB::query()
            ->fromSub($ranked, 'ranked')
            ->where('position', 1)
            ->pluck('price', 'product_id')
            ->map(fn ($price) => bcadd((string) $price, '0', 2))
            ->all();
    }
}
