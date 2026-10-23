<?php

namespace App\Concerns;

use App\Models\Scopes\StoreScope;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

/**
 * For business tables carrying `team_id`: every query is scoped to the current store and
 * new rows created by a signed-in user always land in that user's store.
 *
 * @mixin Model
 */
trait BelongsToStore
{
    /**
     * Boot the trait.
     */
    public static function bootBelongsToStore(): void
    {
        static::addGlobalScope(new StoreScope);

        static::creating(function (self $model) {
            if ($storeId = Auth::user()?->team_id) {
                $model->team_id = $storeId;
            }
        });
    }

    /**
     * Get the store this row belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
