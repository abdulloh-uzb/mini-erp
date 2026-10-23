<?php

namespace App\Actions\Documents;

use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveDraft
{
    /**
     * Create a draft of the given kind, or update the given draft, replacing its lines. The
     * store row is locked while a document is created, so concurrent drafts get distinct numbers.
     *
     * @param  array<string, mixed>  $header
     * @param  array<int, array{product_id: int, location_id: int|null, quantity: int, pieces_per_block: int, price: string|null}>  $lines
     */
    public function handle(?Document $document, DocumentKind $kind, User $author, array $header, array $lines): Document
    {
        return DB::transaction(function () use ($document, $kind, $author, $header, $lines) {
            if ($document === null) {
                Team::query()->whereKey($author->team_id)->lockForUpdate()->firstOrFail();

                $document = Document::create([...$header, 'team_id' => $author->team_id, 'kind' => $kind, 'author_id' => $author->id]);
            } else {
                $document->update($header);
                $document->lines()->delete();
            }

            $document->lines()->createMany($lines);

            return $document;
        });
    }
}
