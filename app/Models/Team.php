<?php

namespace App\Models;

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\StoreRole;
use Database\Factories\TeamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * A store (UI: do'kon). ADR-0003: the starter kit's Team is the tenant.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $blocked_at
 * @property Carbon|null $opening_balance_skipped_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, User> $users
 * @property-read User|null $owner
 * @property-read Collection<int, User> $employees
 */
#[Fillable(['name', 'blocked_at', 'opening_balance_skipped_at'])]
class Team extends Model
{
    /** @use HasFactory<TeamFactory> */
    use HasFactory;

    /**
     * Get everyone working in the store.
     *
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the store's owner.
     *
     * @return HasOne<User, $this>
     */
    public function owner(): HasOne
    {
        return $this->hasOne(User::class)->withStoreRole(StoreRole::Owner);
    }

    /**
     * Get the store's employees.
     *
     * @return HasMany<User, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(User::class)->withStoreRole(StoreRole::Employee);
    }

    /**
     * Determine if the super-admin has blocked the store.
     */
    public function isBlocked(): bool
    {
        return $this->blocked_at !== null;
    }

    /**
     * Determine if documents may be executed: the opening balance has been executed, or the
     * owner said the store has none ("Boshlang'ich qoldiq yo'q"). D13.
     */
    public function hasSettledOpeningBalance(): bool
    {
        return $this->hasSkippedOpeningBalance() || $this->hasExecutedOpeningBalance();
    }

    /**
     * Determine if the owner said the store starts with no opening balance.
     */
    public function hasSkippedOpeningBalance(): bool
    {
        return $this->opening_balance_skipped_at !== null;
    }

    /**
     * Determine if the store's opening balance has been executed.
     */
    public function hasExecutedOpeningBalance(): bool
    {
        return Document::withoutGlobalScopes()
            ->where('team_id', $this->id)
            ->ofKind(DocumentKind::OpeningBalance)
            ->where('status', DocumentStatus::Executed)
            ->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocked_at' => 'datetime',
            'opening_balance_skipped_at' => 'datetime',
        ];
    }
}
