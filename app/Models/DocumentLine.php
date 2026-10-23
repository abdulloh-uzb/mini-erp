<?php

namespace App\Models;

use Database\Factories\DocumentLineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One product on a document, in pieces. Pieces per block is a snapshot taken when the line
 * was entered (Q28). The location is set only where it varies per line (opening balance);
 * otherwise the document's location applies.
 *
 * @property int $id
 * @property int $document_id
 * @property int $product_id
 * @property int|null $location_id
 * @property int $quantity
 * @property int $pieces_per_block
 * @property numeric-string|null $price
 * @property numeric-string|null $unit_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Document $document
 * @property-read Product $product
 * @property-read Location|null $location
 */
#[Fillable(['document_id', 'product_id', 'location_id', 'quantity', 'pieces_per_block', 'price', 'unit_cost'])]
class DocumentLine extends Model
{
    /** @use HasFactory<DocumentLineFactory> */
    use HasFactory;

    /**
     * Get the document the line belongs to.
     *
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    /**
     * Get the product on the line.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the line's own location, where it differs per line.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'pieces_per_block' => 'integer',
            'price' => 'decimal:2',
            'unit_cost' => 'decimal:6',
        ];
    }
}
