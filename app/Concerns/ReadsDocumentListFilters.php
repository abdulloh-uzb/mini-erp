<?php

namespace App\Concerns;

use App\Enums\DocumentStatus;
use Illuminate\Http\Request;

/**
 * Reads the shared document-list filters (story 79) from the query string, in the shape
 * `Document::filtered()` takes.
 */
trait ReadsDocumentListFilters
{
    /**
     * Get the status tab, date range, location, counterparty, author and search filters.
     *
     * @return array{status: DocumentStatus|null, from: string|null, to: string|null, location_id: int|null, counterparty_id: int|null, author_id: int|null, search: string}
     */
    protected function documentListFilters(Request $request): array
    {
        return [
            'status' => $request->enum('status', DocumentStatus::class),
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'location_id' => $request->filled('location_id') ? $request->integer('location_id') : null,
            'counterparty_id' => $request->filled('counterparty_id') ? $request->integer('counterparty_id') : null,
            'author_id' => $request->filled('author_id') ? $request->integer('author_id') : null,
            'search' => $request->string('search')->trim()->value(),
        ];
    }
}
