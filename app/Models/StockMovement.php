<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One append-only change of a stock balance (positive in, negative out), caused by a document line.
 *
 * @property int $id
 * @property int $team_id
 * @property int $location_id
 * @property int $product_id
 * @property int $document_line_id
 * @property int $quantity
 * @property Carbon|null $created_at
 */
#[Fillable(['team_id', 'location_id', 'product_id', 'document_line_id', 'quantity'])]
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use BelongsToStore, HasFactory;

    /**
     * Movements are never updated, so they carry only a creation time.
     */
    public const null UPDATED_AT = null;

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
