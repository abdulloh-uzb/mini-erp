<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ResetPasswordRequest;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The super-admin's store management. It never reads a store's business data.
 */
class StoreController extends Controller
{
    /**
     * List every store with its owner and status.
     */
    public function index(): Response
    {
        return Inertia::render('admin/stores/index', [
            'stores' => Team::query()
                ->with('owner:id,team_id,name,phone')
                ->orderBy('name')
                ->get()
                ->map(fn (Team $team) => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'owner_name' => $team->owner?->name,
                    'owner_phone' => $team->owner?->phone,
                    'is_blocked' => $team->isBlocked(),
                    'created_at' => $team->created_at?->toDateString(),
                ]),
        ]);
    }

    /**
     * Block the store: its users are logged out and cannot log in.
     */
    public function block(Team $team): RedirectResponse
    {
        $team->update(['blocked_at' => now()]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Do'kon bloklandi.")]);

        return to_route('admin.stores.index');
    }

    /**
     * Unblock the store, restoring access with its data intact.
     */
    public function unblock(Team $team): RedirectResponse
    {
        $team->update(['blocked_at' => null]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __("Do'kon blokdan chiqarildi.")]);

        return to_route('admin.stores.index');
    }

    /**
     * Set a new password for the store's owner.
     */
    public function updateOwnerPassword(ResetPasswordRequest $request, Team $team): RedirectResponse
    {
        abort_if($team->owner === null, 404);

        $team->owner->update(['password' => $request->validated('password')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Egasining paroli yangilandi.')]);

        return to_route('admin.stores.index');
    }
}
