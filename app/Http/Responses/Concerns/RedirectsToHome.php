<?php

namespace App\Http\Responses\Concerns;

use Illuminate\Http\Request;

trait RedirectsToHome
{
    /**
     * Get the home page for the signed-in user: the store list for a super-admin, the dashboard otherwise.
     */
    protected function homePath(Request $request): string
    {
        return $request->user()?->isSuperAdmin()
            ? route('admin.stores.index', absolute: false)
            : route('dashboard', absolute: false);
    }
}
