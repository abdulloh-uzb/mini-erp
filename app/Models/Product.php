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
 * No price fields (ADR-0004).
 *
 * @property int $id
 * @property int $team_id
 * @property string $name
 * @property string|null $barcode
 * @property int $pieces_per_block
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['team_id', 'name', 'barcode', 'pieces_per_block', 'archived_at'])]
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
            'archived_at' => 'datetime',
        ];
    }
}
