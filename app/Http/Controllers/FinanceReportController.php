<?php

namespace App\Http\Controllers;

use App\Actions\Reports\BalanceSheet;
use App\Actions\Reports\CashFlow;
use App\Actions\Reports\ProfitAndLoss;
use App\Http\Requests\FinanceReportRequest;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The owner's finance reports (Q48a): Foyda va zarar, Pul oqimi and Balans.
 */
class FinanceReportController extends Controller
{
    /**
     * Show the Foyda va zarar report for a date range, with cash on hand and loans outstanding
     * beside net profit, as loan principal stays out of it (ADR-0001).
     */
    public function profitAndLoss(FinanceReportRequest $request, ProfitAndLoss $report, BalanceSheet $balance): Response
    {
        $position = $balance->handle($request->user()->team);

        return Inertia::render('reports/profit-and-loss', [
            'report' => $report->handle($request->user()->team, $request->from(), $request->to()),
            'position' => [
                'cash_total' => $position['cash_total'],
                'loans_given' => $position['debts']['loans_given'],
                'loans_received' => $position['debts']['loans_received'],
            ],
            'filters' => ['from' => $request->from(), 'to' => $request->to()],
        ]);
    }

    /**
     * Show the Pul oqimi report for a date range.
     */
    public function cashFlow(FinanceReportRequest $request, CashFlow $report): Response
    {
        return Inertia::render('reports/cash-flow', [
            'report' => $report->handle($request->user()->team, $request->from(), $request->to()),
            'filters' => ['from' => $request->from(), 'to' => $request->to()],
        ]);
    }

    /**
     * Show the Balans page: the store's position now.
     */
    public function balance(Request $request, BalanceSheet $report): Response
    {
        return Inertia::render('reports/balance', [
            'report' => $report->handle($request->user()->team),
        ]);
    }
}
