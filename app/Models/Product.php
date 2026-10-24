<?php

namespace App\Models;

use App\Concerns\Archivable;
use App\Concerns\BelongsToStore;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A product (UI: mahsulot). Stock is held in pieces; blocks are entry and display only.
 * The optional sale price is per piece and pre-fills new sale lines (ADR-0007).
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $barcode
 * @property int $pieces_per_block
 * @property numeric-string|null $sale_price
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'name', 'barcode', 'pieces_per_block', 'sale_price', 'archived_at'])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use Archivable, BelongsToStore, HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'pieces_per_block' => 'integer',
            'sale_price' => 'decimal:2',
            'archived_at' => 'datetime',
        ];
    }
}
