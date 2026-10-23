<?php

namespace App\Actions\Reports;

use App\Enums\CashOperationType;
use App\Enums\CategoryDirection;
use App\Enums\DebtEntryType;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\LoanDirection;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The Foyda va zarar report for a date range (Q48a): revenue − cost of goods sold = gross
 * profit − each counted expense category − shortages + surpluses ± interest = net profit.
 * Only sales, returns, counts, counted expense and income categories and write-offs reach it;
 * supplier payments, debt payments, loan principal, owner withdrawals (Q25, ADR-0001) and the
 * opening balance never do.
 */
class ProfitAndLoss
{
    public function __construct(private SalesReport $sales) {}

    /**
     * Build the report for the days from `$from` to `$to` inclusive ("Y-m-d"). Interest received
     * is every counted income category, as Olingan foiz is the only one (ADR-0005).
     *
     * @return array{
     *     revenue: numeric-string,
     *     cost_of_goods_sold: numeric-string,
     *     gross_profit: numeric-string,
     *     expenses: array<int, array{name: string, amount: numeric-string}>,
     *     expenses_total: numeric-string,
     *     shortages: numeric-string,
     *     surpluses: numeric-string,
     *     interest: array{received: numeric-string, paid: numeric-string, net: numeric-string},
     *     net_profit: numeric-string
     * }
     */
    public function handle(Team $store, string $from, string $to): array
    {
        $sales = $this->sales->handle($store, $from, $to, true)['totals'];
        $categories = $this->categories($store, $from, $to);
        [$shortages, $surpluses] = $this->countDifferences($store, $from, $to);

        $interestPaidCode = LoanDirection::Received->interestCategoryCode();
        $expenses = array_values(array_filter(
            $categories,
            fn (array $category) => $category['direction'] === CategoryDirection::Expense->value && $category['code'] !== $interestPaidCode,
        ));
        $expensesTotal = $this->sum(array_column($expenses, 'amount'));

        $interestReceived = $this->sum(array_column(array_filter($categories, fn (array $category) => $category['direction'] === CategoryDirection::Income->value), 'amount'));
        $interestPaid = $this->sum(array_column(array_filter($categories, fn (array $category) => $category['code'] === $interestPaidCode), 'amount'));
        $netInterest = bcsub($interestReceived, $interestPaid, 2);
        $afterExpenses = bcsub(bcsub($sales['gross_profit'], $expensesTotal, 2), $shortages, 2);

        return [
            'revenue' => $sales['revenue'],
            'cost_of_goods_sold' => $sales['cost'],
            'gross_profit' => $sales['gross_profit'],
            'expenses' => array_map(fn (array $expense) => ['name' => $expense['name'], 'amount' => $expense['amount']], $expenses),
            'expenses_total' => $expensesTotal,
            'shortages' => $shortages,
            'surpluses' => $surpluses,
            'interest' => ['received' => $interestReceived, 'paid' => $interestPaid, 'net' => $netInterest],
            'net_profit' => bcadd($afterExpenses, bcadd($surpluses, $netInterest, 2), 2),
        ];
    }

    /**
     * Get the range's amount per counted category, in display order, leaving out those that
     * net to zero: expense and income operations net of their reversals, plus customer debt
     * written off (Umidsiz qarz).
     *
     * @return array<int, array{code: string, name: string, direction: string, amount: numeric-string}>
     */
    private function categories(Team $store, string $from, string $to): array
    {
        $operations = DB::table('cash_operations')
            ->where('team_id', $store->id)
            ->whereIn('type', [CashOperationType::Expense->value, CashOperationType::Income->value])
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('expense_category_id, case when reversal_of_id is null then amount else -amount end as amount');

        $writeOffs = DB::table('debt_entries')
            ->where('team_id', $store->id)
            ->where('type', DebtEntryType::WriteOff->value)
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to)
            ->selectRaw('expense_category_id, -amount as amount');

        return DB::table('expense_categories')
            ->joinSub($operations->unionAll($writeOffs), 'amounts', 'amounts.expense_category_id', '=', 'expense_categories.id')
            ->where('expense_categories.counts_in_net_profit', true)
            ->groupBy('expense_categories.id', 'expense_categories.code', 'expense_categories.name', 'expense_categories.direction', 'expense_categories.sort_order')
            ->havingRaw('sum(amounts.amount) <> 0')
            ->orderBy('expense_categories.sort_order')
            ->selectRaw('expense_categories.code, expense_categories.name, expense_categories.direction, sum(amounts.amount) as amount')
            ->get()
            ->map(fn (stdClass $row) => [
                'code' => (string) $row->code,
                'name' => (string) $row->name,
                'direction' => (string) $row->direction,
                'amount' => $this->money($row->amount),
            ])
            ->all();
    }

    /**
     * Get the range's count shortages and surpluses, each as a positive amount at the average
     * cost stored on execution.
     *
     * @return array{0: numeric-string, 1: numeric-string}
     */
    private function countDifferences(Team $store, string $from, string $to): array
    {
        $row = DB::table('document_lines')
            ->join('documents', 'documents.id', '=', 'document_lines.document_id')
            ->where('documents.team_id', $store->id)
            ->where('documents.kind', DocumentKind::InventoryCount->value)
            ->where('documents.status', DocumentStatus::Executed->value)
            ->whereDate('documents.executed_at', '>=', $from)
            ->whereDate('documents.executed_at', '<=', $to)
            ->selectRaw('coalesce(sum(case when difference_value < 0 then -difference_value else 0 end), 0) as shortages')
            ->selectRaw('coalesce(sum(case when difference_value > 0 then difference_value else 0 end), 0) as surpluses')
            ->first();

        return [$this->money($row->shortages), $this->money($row->surpluses)];
    }

    /**
     * Add up amounts.
     *
     * @param  array<int, numeric-string>  $amounts
     * @return numeric-string
     */
    private function sum(array $amounts): string
    {
        return array_reduce($amounts, fn (string $sum, string $amount) => bcadd($sum, $amount, 2), '0.00');
    }

    /**
     * Round a database number to whole tiyin.
     *
     * @return numeric-string
     */
    private function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }
}
