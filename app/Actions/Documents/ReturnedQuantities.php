<?php

namespace App\Actions\Documents;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentLine;

class ReturnedQuantities
{
    /**
     * Get the pieces already given back of each line of an executed sale or purchase, counting
     * executed returns only; drafts return nothing yet.
     *
     * @return array<int, int> pieces per source line id
     */
    public function handle(Document $source): array
    {
        return DocumentLine::query()
            ->join('documents', 'documents.id', '=', 'document_lines.document_id')
            ->where('documents.source_document_id', $source->id)
            ->where('documents.status', DocumentStatus::Executed)
            ->groupBy('document_lines.source_line_id')
            ->selectRaw('document_lines.source_line_id, sum(document_lines.quantity) as returned')
            ->pluck('returned', 'source_line_id')
            ->map(fn ($returned) => (int) $returned)
            ->all();
    }
}
