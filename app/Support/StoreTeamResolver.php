<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Spatie\Permission\Contracts\PermissionsTeamResolver;

/**
 * Resolves the spatie permission team (the store) from the signed-in user's single store,
 * unless a store has been set explicitly (e.g. while assigning roles outside a request).
 */
class StoreTeamResolver implements PermissionsTeamResolver
{
    protected int|string|null $teamId = null;

    public function setPermissionsTeamId(int|string|Model|null $id): void
    {
        $this->teamId = $id instanceof Model ? $id->getKey() : $id;
    }

    public function getPermissionsTeamId(): int|string|null
    {
        return $this->teamId ?? Auth::user()?->team_id;
    }
}
