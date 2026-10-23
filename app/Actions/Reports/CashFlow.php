<?php

namespace App\Actions\Reports;

use App\Enums\CashDirection;
use App\Enums\CashOperationType;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The Pul oqimi report for a date range (Q48a): every cash operation, loans, debt payments and
 * reversals included, added up as money in and out per type, per expense or income category
 * and per day.
 */
class CashFlow
{
    /**
     * Build the report for the days from `$from` to `$to` inclusive ("Y-m-d").
     *
     * @return array{
     *     totals: array{in: numeric-string, out: numeric-string, net: numeric-string},
     *     by_type: array<int, array{type: string, label: string, in: numeric-string, out: numeric-string}>,
     *     by_category: array<int, array{name: string, in: numeric-string, out: numeric-string}>,
     *     by_day: array<int, array{date: string, in: numeric-string, out: numeric-string, net: numeric-string}>
     * }
     */
    public function handle(Team $store, string $from, string $to): array
    {
        $groups = $this->groups($store, $from, $to);

        $byType = [];

        foreach (CashOperationType::cases() as $type) {
            $rows = array_filter($groups, fn (stdClass $group) => $group->type === $type->value);

            if ($rows !== []) {
                $byType[] = ['type' => $type->value, 'label' => $type->label(), ...$this->inOut($rows)];
            }
        }

        $byCategory = [];

        foreach (array_filter($groups, fn (stdClass $group) => $group->category !== null) as $group) {
            $byCategory[$group->category_order] ??= ['name' => (string) $group->category, 'rows' => []];
            $byCategory[$group->category_order]['rows'][] = $group;
        }

        ksort($byCategory);

        $byDay = [];

        foreach ($groups as $group) {
            $byDay[$group->day][] = $group;
        }

        ksort($byDay);

        return [
            'totals' => $this->withNet($this->inOut($groups)),
            'by_type' => $byType,
            'by_category' => array_values(array_map(fn (array $category) => ['name' => $category['name'], ...$this->inOut($category['rows'])], $byCategory)),
            'by_day' => array_map(fn (string $day, array $rows) => ['date' => $day, ...$this->withNet($this->inOut($rows))], array_keys($byDay), $byDay),
        ];
    }

    /**
     * Get the range's operations summed per type, direction, category and day.
     *
     * @return array<int, stdClass>
     */
    private function groups(Team $store, string $from, string $to): array
    {
        return DB::table('cash_operations')
            ->leftJoin('expense_categories', 'expense_categories.id', '=', 'cash_operations.expense_category_id')
            ->where('cash_operations.team_id', $store->id)
            ->whereDate('cash_operations.created_at', '>=', $from)
            ->whereDate('cash_operations.created_at', '<=', $to)
            ->groupBy('cash_operations.type', 'cash_operations.direction', 'expense_categories.name', 'expense_categories.sort_order')
            ->groupByRaw('date(cash_operations.created_at)')
            ->selectRaw('cash_operations.type, cash_operations.direction, date(cash_operations.created_at) as day')
            ->selectRaw('expense_categories.name as category, expense_categories.sort_order as category_order')
            ->selectRaw('sum(cash_operations.amount) as amount')
            ->get()
            ->all();
    }

    /**
     * Add up groups as money in and money out.
     *
     * @param  array<int, stdClass>  $groups
     * @return array{in: numeric-string, out: numeric-string}
     */
    private function inOut(array $groups): array
    {
        $totals = ['in' => '0.00', 'out' => '0.00'];

        foreach ($groups as $group) {
            $key = $group->direction === CashDirection::In->value ? 'in' : 'out';
            $totals[$key] = bcadd($totals[$key], number_format((float) $group->amount, 2, '.', ''), 2);
        }

        return $totals;
    }

    /**
     * Add what stayed in the register: in minus out.
     *
     * @param  array{in: numeric-string, out: numeric-string}  $totals
     * @return array{in: numeric-string, out: numeric-string, net: numeric-string}
     */
    private function withNet(array $totals): array
    {
        return [...$totals, 'net' => bcsub($totals['in'], $totals['out'], 2)];
    }
}
