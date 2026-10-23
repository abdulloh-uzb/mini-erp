<?php

namespace App\Models;

use App\Concerns\BelongsToStore;
use Database\Factories\LocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A warehouse, or a sub-warehouse when it has a parent.
 *
 * @property int $id
 * @property int $team_id
 * @property int|null $parent_id
 * @property string $name
 * @property Carbon|null $archived_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Location|null $parent
 */
#[Fillable(['team_id', 'parent_id', 'name', 'archived_at'])]
class Location extends Model
{
    /** @use HasFactory<LocationFactory> */
    use BelongsToStore, HasFactory;

    /**
     * The name of the warehouse every new store starts with.
     */
    public const string MAIN_WAREHOUSE_NAME = 'Asosiy ombor';

    /**
     * Get the warehouse this sub-warehouse sits in.
     *
     * @return BelongsTo<Location, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'parent_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'archived_at' => 'datetime',
        ];
    }
}
