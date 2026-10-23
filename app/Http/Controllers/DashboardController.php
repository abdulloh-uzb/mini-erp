<?php

namespace App\Http\Controllers;

use App\Actions\Documents\PendingDrafts;
use App\Actions\Reports\BalanceSheet;
use App\Actions\Reports\SalesReport;
use App\Enums\CashOperationType;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\StorePermission;
use App\Models\CashOperation;
use App\Models\Document;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the home page. The owner sees the "Boshlang'ich qoldiqni kiriting" card until the
     * opening balance is executed or skipped, the drafts waiting for execution, today's figures,
     * the cash per payment method and the debt totals. Employees see their own drafts and
     * today's sales, never costs.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('dashboard', [
            'openingBalancePending' => $user->can(StorePermission::OpeningBalanceManage->value)
                && ! $user->team->hasSettledOpeningBalance(),
            ...($user->can(StorePermission::CostsView->value)
                ? ['owner' => $this->ownerFigures($user)]
                : ['employee' => $this->employeeFigures($user)]),
        ]);
    }

    /**
     * Get what the owner's home shows.
     *
     * @return array<string, mixed>
     */
    private function ownerFigures(User $user): array
    {
        $today = today()->toDateString();
        $sales = app(SalesReport::class)->handle($user->team, $today, $today, true)['totals'];
        $balance = app(BalanceSheet::class)->handle($user->team);

        return [
            'pendingDrafts' => app(PendingDrafts::class)->widget($user),
            'today' => [
                'revenue' => $sales['revenue'],
                'sales_count' => $sales['sales_count'],
                'gross_profit' => $sales['gross_profit'],
                'cash_received' => $this->cashReceivedToday(),
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

    /**
     * Get the money that came in today from sales and customer debt payments, net of reversals.
     *
     * @return numeric-string
     */
    private function cashReceivedToday(): string
    {
        $sum = CashOperation::query()
            ->whereIn('type', [CashOperationType::SalePayment, CashOperationType::CustomerDebtPayment])
            ->whereDate('created_at', today())
            ->selectRaw('coalesce(sum(case when reversal_of_id is null then amount else -amount end), 0) as total')
            ->value('total');

        return number_format((float) $sum, 2, '.', '');
    }
}
