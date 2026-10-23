<?php

namespace App\Http\Controllers;

use App\Actions\Reports\StockReport;
use App\Enums\StorePermission;
use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StockReportController extends Controller
{
    /**
     * Show current stock balances (Qoldiqlar). Average cost and value are sent only to users
     * allowed to see costs.
     */
    public function __invoke(Request $request, StockReport $report): Response
    {
        $search = $request->string('search')->trim()->value();
        $location = $request->filled('location_id')
            ? Location::query()->find($request->integer('location_id'))
            : null;

        return Inertia::render('stock/index', [
            ...$report->handle(
                $request->user()->team,
                $location,
                $search,
                $request->user()->can(StorePermission::CostsView->value),
            ),
            'filters' => ['search' => $search, 'location_id' => $location?->id],
            'locations' => Location::pickerOptions(Location::query()),
        ]);
    }
}
