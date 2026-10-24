<?php

namespace App\Actions\Reports;

use App\Enums\StockLevel;
use App\Models\Location;
use App\Models\StockBalance;
use App\Models\Team;
use Illuminate\Database\Query\JoinClause;
use stdClass;

/**
 * Current stock balances per location and product (Q49). Sub-warehouses roll up into their
 * warehouse; filtering by a warehouse includes its sub-warehouses, by a sub-warehouse only
 * itself. Each row carries its stock level; sold-out balances show as "out", and archived
 * products only while they still have stock. Narrowing to one level leaves the per-level
 * counts covering every row. Average cost and value are included only when asked for
 * (cost-permitted users).
 */
class StockReport
{
    /**
     * Build the report.
     *
     * @return array{rows: array<int, array{location_id: int, location_name: string, product_id: int, product_name: string, pieces_per_block: int, quantity: int, level: string, average_cost?: numeric-string, value?: numeric-string}>, level_counts: array<string, int>, total_value?: numeric-string}
     */
    public function handle(Team $store, ?Location $location, string $search, bool $withCosts, ?StockLevel $level = null): array
    {
        $balances = StockBalance::withoutGlobalScopes()
            ->join('locations', 'locations.id', '=', 'stock_balances.location_id')
            ->join('products', 'products.id', '=', 'stock_balances.product_id')
            ->leftJoin('product_costs', fn (JoinClause $join) => $join->on('product_costs.product_id', '=', 'stock_balances.product_id'))
            ->where('stock_balances.team_id', $store->id)
            ->when($location, fn ($query, Location $location) => $query->where(fn ($query) => $query
                ->where('locations.id', $location->id)
                ->when($location->parent_id === null, fn ($query) => $query->orWhere('locations.parent_id', $location->id))))
            ->when($search !== '', fn ($query) => $query->where(fn ($query) => $query
                ->where('products.name', 'like', '%'.$search.'%')
                ->orWhere('products.barcode', 'like', '%'.$search.'%')))
            ->when(
                $location,
                fn ($query, Location $location) => $query->selectRaw('? as group_location_id', [$location->id]),
                fn ($query) => $query
                    ->selectRaw('coalesce(locations.parent_id, locations.id) as group_location_id')
                    ->groupByRaw('coalesce(locations.parent_id, locations.id)'),
            )
            ->groupBy('products.id', 'products.name', 'products.pieces_per_block', 'products.archived_at', 'product_costs.average_cost')
            ->addSelect(['products.id as product_id', 'products.name as product_name', 'products.pieces_per_block', 'product_costs.average_cost'])
            ->selectRaw('sum(stock_balances.quantity) as quantity')
            ->havingRaw('sum(stock_balances.quantity) > 0 or products.archived_at is null')
            ->toBase()
            ->get();

        $locationNames = Location::withoutGlobalScopes()
            ->with('parent')
            ->whereIn('id', $balances->pluck('group_location_id')->unique())
            ->get()
            ->mapWithKeys(fn (Location $location) => [$location->id => $location->fullName()]);

        $rows = [];
        $levelCounts = array_fill_keys(array_column(StockLevel::cases(), 'value'), 0);
        $totalValue = '0.00';

        foreach ($balances as $balance) {
            $row = $this->row($balance, (string) $locationNames[$balance->group_location_id], $withCosts);
            $levelCounts[$row['level']]++;

            if ($level !== null && $row['level'] !== $level->value) {
                continue;
            }

            $totalValue = bcadd($totalValue, $row['value'] ?? '0', 2);
            $rows[] = $row;
        }

        usort($rows, fn (array $a, array $b) => [$a['location_name'], $a['product_name']] <=> [$b['location_name'], $b['product_name']]);

        $report = ['rows' => $rows, 'level_counts' => $levelCounts];

        return $withCosts ? [...$report, 'total_value' => $totalValue] : $report;
    }

    /**
     * Turn one grouped balance into a report row.
     *
     * @return array{location_id: int, location_name: string, product_id: int, product_name: string, pieces_per_block: int, quantity: int, level: string, average_cost?: numeric-string, value?: numeric-string}
     */
    private function row(stdClass $balance, string $locationName, bool $withCosts): array
    {
        $row = [
            'location_id' => (int) $balance->group_location_id,
            'location_name' => $locationName,
            'product_id' => (int) $balance->product_id,
            'product_name' => (string) $balance->product_name,
            'pieces_per_block' => (int) $balance->pieces_per_block,
            'quantity' => (int) $balance->quantity,
            'level' => StockLevel::forQuantity((int) $balance->quantity)->value,
        ];

        if (! $withCosts) {
            return $row;
        }

        $averageCost = $this->decimal($balance->average_cost ?? 0);

        return [
            ...$row,
            'average_cost' => $this->money($averageCost),
            'value' => $this->money(bcmul($averageCost, (string) $row['quantity'], 6)),
        ];
    }

    /**
     * Normalise a database number to a numeric string with six digits.
     *
     * @return numeric-string
     */
    private function decimal(mixed $value): string
    {
        return number_format((float) $value, 6, '.', '');
    }

    /**
     * Round a positive amount half up to whole tiyin.
     *
     * @param  numeric-string  $amount
     * @return numeric-string
     */
    private function money(string $amount): string
    {
        return bcadd($amount, '0.005', 2);
    }
}
