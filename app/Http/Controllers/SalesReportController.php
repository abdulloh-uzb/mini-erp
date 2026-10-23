<?php

namespace App\Http\Controllers;

use App\Actions\Reports\SalesReport;
use App\Enums\StorePermission;
use App\Http\Requests\SalesReportRequest;
use Inertia\Inertia;
use Inertia\Response;

class SalesReportController extends Controller
{
    /**
     * Show the sales report (Sotuvlar hisoboti) for a date range, today by default. Cost and
     * profit are sent only to users allowed to see costs.
     */
    public function __invoke(SalesReportRequest $request, SalesReport $report): Response
    {
        return Inertia::render('reports/sales', [
            'report' => $report->handle(
                $request->user()->team,
                $request->from(),
                $request->to(),
                $request->user()->can(StorePermission::CostsView->value),
            ),
            'filters' => ['from' => $request->from(), 'to' => $request->to()],
        ]);
    }
}
