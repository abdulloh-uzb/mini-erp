<?php

namespace App\Actions\Reports;

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * The sales report for a date range (Q39). Revenue, pieces and profit are net of executed sales
 * returns, each counted on its own execution date; a return is credited to the employee who
 * executed the sale it gives back. Cost and profit are included only when asked for
 * (cost-permitted users, D4).
 */
class SalesReport
{
    /**
     * Build the report for the days from `$from` to `$to` inclusive ("Y-m-d").
     *
     * @return array{
     *     totals: array{revenue: numeric-string, sales_count: int, cost?: numeric-string, gross_profit?: numeric-string},
     *     by_product: array<int, array{product_id: int, product_name: string, pieces_per_block: int, quantity: int, revenue: numeric-string, profit?: numeric-string}>,
     *     by_employee: array<int, array{user_id: int, name: string, sales_count: int, revenue: numeric-string, profit?: numeric-string}>,
     *     sales: array<int, array{id: int, number: int, date: string, employee: string, customer: string|null, total: numeric-string, paid: numeric-string, debt: numeric-string}>
     * }
     */
    public function handle(Team $store, string $from, string $to, bool $withCosts): array
    {
        $sales = $this->sales($store, $from, $to);
        $lines = $this->lines($store, $from, $to);

        $revenue = $this->sum($lines, 'revenue');
        $cost = $this->sum($lines, 'cost');

        $totals = ['revenue' => $revenue, 'sales_count' => count($sales)];

        if ($withCosts) {
            $totals += ['cost' => $cost, 'gross_profit' => bcsub($revenue, $cost, 2)];
        }

        return [
            'totals' => $totals,
            'by_product' => $this->byProduct($lines, $withCosts),
            'by_employee' => $this->byEmployee($lines, $sales, $withCosts),
            'sales' => array_map(fn (stdClass $sale) => [
                'id' => (int) $sale->id,
                'number' => (int) $sale->number,
                'date' => (string) $sale->executed_at,
                'employee' => (string) $sale->employee,
                'customer' => $sale->customer,
                'total' => $this->money($sale->total),
                'paid' => $this->money($sale->paid),
                'debt' => bcsub($this->money($sale->total), $this->money($sale->paid), 2),
            ], $sales),
        ];
    }

    /**
     * Get the executed sales of the range with their total, what was paid and who sold them, newest first.
     *
     * @return array<int, stdClass>
     */
    private function sales(Team $store, string $from, string $to): array
    {
        return DB::table('documents')
            ->join('users', 'users.id', '=', 'documents.executor_id')
            ->leftJoin('counterparties', 'counterparties.id', '=', 'documents.counterparty_id')
            ->where('documents.team_id', $store->id)
            ->where('documents.kind', DocumentKind::Sale->value)
            ->where('documents.status', DocumentStatus::Executed->value)
            ->whereDate('documents.executed_at', '>=', $from)
            ->whereDate('documents.executed_at', '<=', $to)
            ->select([
                'documents.id', 'documents.number', 'documents.executed_at', 'documents.executor_id',
                'users.name as employee', 'counterparties.name as customer',
            ])
            ->selectRaw('(select coalesce(sum(quantity * price), 0) from document_lines where document_lines.document_id = documents.id) as total')
            ->selectRaw('(select coalesce(sum(amount), 0) from document_payments where document_payments.document_id = documents.id) as paid')
            ->orderByDesc('documents.executed_at')
            ->orderByDesc('documents.id')
            ->get()
            ->all();
    }

    /**
     * Get the signed sums of the range's sale and sales-return lines per product and employee:
     * returns count negative.
     *
     * @return array<int, array{product_id: int, product_name: string, pieces_per_block: int, user_id: int, quantity: int, revenue: string, cost: string}>
     */
    private function lines(Team $store, string $from, string $to): array
    {
        return DB::table('document_lines')
            ->join('documents', 'documents.id', '=', 'document_lines.document_id')
            ->join('products', 'products.id', '=', 'document_lines.product_id')
            ->leftJoin('documents as source', 'source.id', '=', 'documents.source_document_id')
            ->where('documents.team_id', $store->id)
            ->whereIn('documents.kind', [DocumentKind::Sale->value, DocumentKind::SalesReturn->value])
            ->where('documents.status', DocumentStatus::Executed->value)
            ->whereDate('documents.executed_at', '>=', $from)
            ->whereDate('documents.executed_at', '<=', $to)
            ->groupBy('products.id', 'products.name', 'products.pieces_per_block')
            ->groupByRaw('coalesce(source.executor_id, documents.executor_id)')
            ->selectRaw('products.id as product_id, products.name as product_name, products.pieces_per_block')
            ->selectRaw('coalesce(source.executor_id, documents.executor_id) as user_id')
            ->selectRaw('sum(case when documents.kind = ? then 1 else -1 end * document_lines.quantity) as quantity', [DocumentKind::Sale->value])
            ->selectRaw('sum(case when documents.kind = ? then 1 else -1 end * document_lines.quantity * coalesce(document_lines.price, 0)) as revenue', [DocumentKind::Sale->value])
            ->selectRaw('sum(case when documents.kind = ? then 1 else -1 end * document_lines.quantity * coalesce(document_lines.unit_cost, 0)) as cost', [DocumentKind::Sale->value])
            ->get()
            ->map(fn (stdClass $row) => [
                'product_id' => (int) $row->product_id,
                'product_name' => (string) $row->product_name,
                'pieces_per_block' => (int) $row->pieces_per_block,
                'user_id' => (int) $row->user_id,
                'quantity' => (int) $row->quantity,
                'revenue' => $this->money($row->revenue),
                'cost' => $this->money($row->cost),
            ])
            ->all();
    }

    /**
     * Group the lines per product, biggest revenue first.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function byProduct(array $lines, bool $withCosts): array
    {
        $rows = [];

        foreach ($lines as $line) {
            $row = &$rows[$line['product_id']];
            $row ??= [
                'product_id' => $line['product_id'],
                'product_name' => $line['product_name'],
                'pieces_per_block' => $line['pieces_per_block'],
                'quantity' => 0,
                'revenue' => '0.00',
                'cost' => '0.00',
            ];
            $row['quantity'] += $line['quantity'];
            $row['revenue'] = bcadd($row['revenue'], $line['revenue'], 2);
            $row['cost'] = bcadd($row['cost'], $line['cost'], 2);
            unset($row);
        }

        return $this->ranked($rows, $withCosts);
    }

    /**
     * Group the lines and sales per employee, biggest revenue first.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @param  array<int, stdClass>  $sales
     * @return array<int, array<string, mixed>>
     */
    private function byEmployee(array $lines, array $sales, bool $withCosts): array
    {
        $rows = [];

        foreach ($sales as $sale) {
            $rows[$sale->executor_id] ??= $this->employeeRow((int) $sale->executor_id, (string) $sale->employee);
            $rows[$sale->executor_id]['sales_count']++;
        }

        $missing = array_diff(array_unique(array_column($lines, 'user_id')), array_keys($rows));
        $names = DB::table('users')->whereIn('id', $missing)->pluck('name', 'id');

        foreach ($lines as $line) {
            $row = &$rows[$line['user_id']];
            $row ??= $this->employeeRow($line['user_id'], (string) $names[$line['user_id']]);
            $row['revenue'] = bcadd($row['revenue'], $line['revenue'], 2);
            $row['cost'] = bcadd($row['cost'], $line['cost'], 2);
            unset($row);
        }

        return $this->ranked($rows, $withCosts);
    }

    /**
     * Start an employee's row.
     *
     * @return array<string, mixed>
     */
    private function employeeRow(int $userId, string $name): array
    {
        return ['user_id' => $userId, 'name' => $name, 'sales_count' => 0, 'revenue' => '0.00', 'cost' => '0.00'];
    }

    /**
     * Sort rows by revenue, turn cost into profit or drop it, and renumber them.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array<string, mixed>>
     */
    private function ranked(array $rows, bool $withCosts): array
    {
        usort($rows, fn (array $a, array $b) => bccomp($b['revenue'], $a['revenue'], 2));

        return array_map(function (array $row) use ($withCosts) {
            $cost = $row['cost'];
            unset($row['cost']);

            return $withCosts ? [...$row, 'profit' => bcsub($row['revenue'], $cost, 2)] : $row;
        }, $rows);
    }

    /**
     * Add up one column of the lines.
     *
     * @param  array<int, array<string, mixed>>  $lines
     * @return numeric-string
     */
    private function sum(array $lines, string $column): string
    {
        return array_reduce($lines, fn (string $sum, array $line) => bcadd($sum, $line[$column], 2), '0.00');
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
