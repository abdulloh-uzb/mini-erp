<?php

namespace App\Http\Controllers;

use App\Actions\Reports\StockReport;
use App\Enums\StockLevel;
use App\Enums\StorePermission;
use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockReportController extends Controller
{
    /**
     * Show current stock balances (Qoldiqlar), optionally only one stock level. Average cost
     * and value are sent only to users allowed to see costs.
     */
    public function __invoke(Request $request, StockReport $report): Response
    {
        $search = $request->string('search')->trim()->value();
        $location = $request->filled('location_id')
            ? Location::query()->find($request->integer('location_id'))
            : null;
        $level = $request->enum('level', StockLevel::class);

        return Inertia::render('stock/index', [
            ...$report->handle(
                $request->user()->team,
                $location,
                $search,
                $request->user()->can(StorePermission::CostsView->value),
                $level,
            ),
            'filters' => ['search' => $search, 'location_id' => $location?->id, 'level' => $level?->value],
            'locations' => Location::pickerOptions(Location::query()),
        ]);
    }
}
