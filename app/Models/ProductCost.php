<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use Database\Factories\ProductCostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A product's moving average cost per piece, store-wide (ADR-0002). Kept apart from the
 * product so that it is locked during execution and never leaks into catalogue screens.
 *
 * @property int $id
 * @property int $team_id
 * @property int $product_id
 * @property numeric-string $average_cost
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'product_id', 'average_cost'])]
class ProductCost extends Model
{
    /** @use HasFactory<ProductCostFactory> */
    use BelongsToStore, HasFactory;

    /**
     * Get the product the cost belongs to.
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
            'average_cost' => 'decimal:6',
        ];
    }
}
