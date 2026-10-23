<?php

namespace App\Models;

use App\Enums\StoreRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $phone
 * @property string $password
 * @property bool $is_active
 * @property int|null $team_id
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team|null $team
 */
#[Fillable(['name', 'phone', 'password', 'team_id', 'is_active'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the store the user works in; null for a super-admin.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope the query to users holding the given role in their own store.
     *
     * @param  Builder<User>  $query
     */
    #[Scope]
    protected function withStoreRole(Builder $query, StoreRole $role): void
    {
        $query->whereExists(fn (QueryBuilder $roles) => $roles
            ->from('model_has_roles')
            ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
            ->whereColumn('model_has_roles.model_id', 'users.id')
            ->where(fn (QueryBuilder $team) => $team
                ->whereColumn('model_has_roles.team_id', 'users.team_id')
                ->orWhere(fn (QueryBuilder $global) => $global->whereNull('model_has_roles.team_id')->whereNull('users.team_id')))
            ->where('model_has_roles.model_type', $this->getMorphClass())
            ->where('roles.name', $role->value));
    }

    /**
     * Get the user's role in their store, or the global super-admin role.
     */
    public function storeRole(): ?StoreRole
    {
        $role = $this->getRoleNames()->first();

        return $role === null ? null : StoreRole::tryFrom($role);
    }

    /**
     * Replace the user's role, in their own store (or globally for a super-admin).
     */
    public function assignStoreRole(StoreRole $role): void
    {
        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId($this->team_id);
        $this->unsetRelation('roles');
        $this->syncRoles([$role->value]);

        setPermissionsTeamId($previousTeamId);
        $this->unsetRelation('roles');
        $this->unsetRelation('permissions');
    }

    /**
     * Determine if the user is a platform super-admin.
     */
    public function isSuperAdmin(): bool
    {
        return $this->team_id === null && $this->storeRole() === StoreRole::SuperAdmin;
    }

    /**
     * Get the user's store permissions, for the sidebar and the frontend.
     *
     * @return array<int, string>
     */
    public function storePermissions(): array
    {
        return $this->getAllPermissions()->pluck('name')->values()->all();
    }

    /**
     * Get the reason the user may not use the app right now, or null if they may.
     */
    public function accessDeniedReason(): ?string
    {
        if (! $this->is_active) {
            return __('Hisobingiz faol emas.');
        }

        if ($this->team?->isBlocked()) {
            return __("Do'kon bloklangan.");
        }

        return null;
    }

    /**
     * Get the username shown by authenticators for this user's passkeys.
     */
    public function getPasskeyUsername(): string
    {
        return $this->phone;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'two_factor_confirmed_at' => 'datetime',
        ];
    }
}
