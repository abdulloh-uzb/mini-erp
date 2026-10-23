<?php

namespace App\Http\Controllers;

use App\Enums\StorePermission;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the home page. The owner sees the "Boshlang'ich qoldiqni kiriting" card until the
     * opening balance is executed or skipped.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('dashboard', [
            'openingBalancePending' => $user->can(StorePermission::OpeningBalanceManage->value)
                && ! $user->team->hasSettledOpeningBalance(),
        ]);
    }
}
