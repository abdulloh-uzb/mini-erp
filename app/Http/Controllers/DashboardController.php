<?php

namespace App\Http\Controllers;

use App\Actions\Cash\DebtLedger;
use App\Actions\Documents\PendingDrafts;
use App\Actions\Reports\BalanceSheet;
use App\Actions\Reports\CashFlow;
use App\Actions\Reports\ProfitAndLoss;
use App\Actions\Reports\SalesReport;
use App\Enums\DebtSide;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\StorePermission;
use App\Http\Requests\DashboardRequest;
use App\Models\Document;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the home page. The owner sees the "Boshlang'ich qoldiqni kiriting" card until the
     * opening balance is executed or skipped, the drafts waiting for execution, the figures of
     * the chosen period (today by default), the cash per payment method and the debt totals.
     * Employees see their own drafts and today's sales, never costs.
     */
    public function __invoke(DashboardRequest $request): Response
    {
        $user = $request->user();
        [$from, $to] = $request->range();

        return Inertia::render('dashboard', [
            'openingBalancePending' => $user->can(StorePermission::OpeningBalanceManage->value)
                && ! $user->team->hasSettledOpeningBalance(),
            ...($user->can(StorePermission::CostsView->value)
                ? ['owner' => $this->ownerFigures($user, $from, $to)]
                : ['employee' => $this->employeeFigures($user)]),
            'filters' => ['period' => $request->period()->value, 'from' => $from, 'to' => $to],
        ]);
    }

    /**
     * Get what the owner's home shows. The period figures follow the reports: Yalpi savdo and
     * Yalpi daromad as in Sotuvlar hisoboti (net of returns), Kirim as money in on Pul oqimi,
     * Xarajat as the counted expenses on Foyda va zarar; Debit (customers owe) and Kredit (owed
     * to suppliers) stand as at the end of the period's last day.
     *
     * @return array<string, mixed>
     */
    private function ownerFigures(User $user, string $from, string $to): array
    {
        $sales = app(SalesReport::class)->handle($user->team, $from, $to, true);
        $balance = app(BalanceSheet::class)->handle($user->team);
        $debts = app(DebtLedger::class);

        return [
            'pendingDrafts' => app(PendingDrafts::class)->widget($user),
            'period' => [
                'gross_sales' => $sales['totals']['revenue'],
                'gross_profit' => $sales['totals']['gross_profit'],
                'cash_in' => app(CashFlow::class)->handle($user->team, $from, $to)['totals']['in'],
                'expenses' => app(ProfitAndLoss::class)->handle($user->team, $from, $to)['expenses_total'],
                'debit' => $debts->totalOn($user->team_id, DebtSide::Customer, $to),
                'credit' => $debts->totalOn($user->team_id, DebtSide::Supplier, $to),
                'pieces_sold' => array_sum(array_column($sales['by_product'], 'quantity')),
            ],
            'cashBalances' => $balance['cash'],
            'debts' => $balance['debts'],
        ];
    }

    /**
     * Get what the employee's home shows: their drafts and their sales today.
     *
     * @return array<string, mixed>
     */
    private function employeeFigures(User $user): array
    {
        $today = today()->toDateString();
        $mine = collect(app(SalesReport::class)->handle($user->team, $today, $today, false)['by_employee'])->firstWhere('user_id', $user->id);

        return [
            'myDrafts' => Document::query()
                ->where('author_id', $user->id)
                ->where('status', DocumentStatus::Draft)
                ->where('kind', '!=', DocumentKind::OpeningBalance->value)
                ->latest('updated_at')
                ->limit(20)
                ->get()
                ->map(fn (Document $draft) => [
                    'id' => $draft->id,
                    'kind' => $draft->kind->value,
                    'label' => $draft->kind->label(),
                    'number' => $draft->number,
                    'updated_at' => $draft->updated_at?->toIso8601String(),
                    'href' => route($draft->kind->routePrefix().'.show', $draft, false),
                ])
                ->all(),
            'mySalesToday' => [
                'sales_count' => $mine['sales_count'] ?? 0,
                'revenue' => $mine['revenue'] ?? '0.00',
            ],
        ];
    }
}
