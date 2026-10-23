<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use Database\Factories\StockBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Pieces of a product on hand at a location. Written only by the stock ledger, never negative.
 *
 * @property int $id
 * @property int $team_id
 * @property int $location_id
 * @property int $product_id
 * @property int $quantity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Location $location
 * @property-read Product $product
 */
#[Fillable(['team_id', 'location_id', 'product_id', 'quantity'])]
class StockBalance extends Model
{
    /** @use HasFactory<StockBalanceFactory> */
    use BelongsToStore, HasFactory;

    /**
     * Get the location holding the pieces.
     *
     * @return BelongsTo<Location, $this>
     */
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    /**
     * Get the product.
     *
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
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
        ];
    }
}
