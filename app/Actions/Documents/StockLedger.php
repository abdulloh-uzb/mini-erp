<?php

namespace App\Actions\Documents;

use App\Models\DocumentLine;
use App\Models\ProductCost;
use App\Models\StockBalance;
use App\Models\StockMovement;

/**
 * The stock ledger: pieces on hand per (store, location, product) plus an append-only movement
 * history linked to document lines, and each product's moving average cost (ADR-0002). Used
 * only inside the execution module's transaction. Every change locks the product's cost row
 * first and then its balance row, so concurrent executions touching a product serialise.
 */
class StockLedger
{
    /**
     * Digits kept for average cost; money itself has two.
     */
    private const int COST_SCALE = 6;

    /**
     * Lock the cost rows of the given products in id order, before any line touches them, so
     * that two executions sharing products wait for each other instead of deadlocking.
     *
     * @param  array<int, int>  $productIds
     */
    public function lockProducts(int $storeId, array $productIds): void
    {
        $productIds = array_values(array_unique($productIds));
        sort($productIds);

        foreach ($productIds as $productId) {
            $this->lockedCost($storeId, $productId);
        }
    }

    /**
     * Add pieces of the line's product to a location. Pieces arriving at a known cost blend
     * into the average: (on hand × average + quantity × cost) / (on hand + quantity), with
     * on hand counted across all locations.
     *
     * @param  numeric-string|null  $unitCost
     */
    public function receive(DocumentLine $line, int $locationId, int $quantity, ?string $unitCost = null): void
    {
        $cost = $this->lockedCost($line->document->team_id, $line->product_id);

        if ($unitCost !== null) {
            $onHand = (string) $this->onHand($line);
            $value = bcadd(bcmul($onHand, $cost->average_cost, self::COST_SCALE), bcmul((string) $quantity, $unitCost, self::COST_SCALE), self::COST_SCALE);

            $cost->update(['average_cost' => $this->round(bcdiv($value, bcadd($onHand, (string) $quantity), self::COST_SCALE + 2))]);
        }

        $balance = $this->lockedBalance($line, $locationId);
        $balance->quantity += $quantity;
        $balance->save();

        $this->recordMovement($line, $locationId, $quantity);
    }

    /**
     * Take pieces of the line's product out of a location. The average cost is unchanged,
     * unless the pieces leave at a known cost (a supplier return): then they are taken out of
     * it, (on hand × average − quantity × cost) / (on hand − quantity), never below zero, and
     * the average stays as it was when nothing is left on hand.
     *
     * @param  numeric-string|null  $unitCost
     *
     * @throws InsufficientStock when the location holds fewer pieces; nothing is written.
     */
    public function issue(DocumentLine $line, int $locationId, int $quantity, ?string $unitCost = null): void
    {
        $cost = $this->lockedCost($line->document->team_id, $line->product_id);
        $balance = $this->lockedBalance($line, $locationId);

        if ($balance->quantity < $quantity) {
            throw new InsufficientStock($balance->quantity, $quantity);
        }

        $onHand = $this->onHand($line);

        if ($unitCost !== null && $onHand > $quantity) {
            $value = bcsub(bcmul((string) $onHand, $cost->average_cost, self::COST_SCALE), bcmul((string) $quantity, $unitCost, self::COST_SCALE), self::COST_SCALE);
            $average = bcdiv($value, (string) ($onHand - $quantity), self::COST_SCALE + 2);

            $cost->update(['average_cost' => bccomp($average, '0', self::COST_SCALE + 2) > 0 ? $this->round($average) : '0']);
        }

        $balance->quantity -= $quantity;
        $balance->save();

        $this->recordMovement($line, $locationId, -$quantity);
    }

    /**
     * Set the pieces of the line's product at a location to what was counted, moving only the
     * difference: a surplus enters at the current average (unchanged), a shortage leaves it.
     *
     * @return int the pieces on hand before the count (expected)
     */
    public function recount(DocumentLine $line, int $locationId, int $counted): int
    {
        $this->lockedCost($line->document->team_id, $line->product_id);
        $balance = $this->lockedBalance($line, $locationId);
        $expected = $balance->quantity;

        if ($counted !== $expected) {
            $balance->quantity = $counted;
            $balance->save();

            $this->recordMovement($line, $locationId, $counted - $expected);
        }

        return $expected;
    }

    /**
     * Get the current average cost of the line's product.
     *
     * @return numeric-string
     */
    public function averageCost(DocumentLine $line): string
    {
        return $this->lockedCost($line->document->team_id, $line->product_id)->average_cost;
    }

    /**
     * Get the cost row of a product, locked for this transaction.
     */
    private function lockedCost(int $storeId, int $productId): ProductCost
    {
        ProductCost::withoutGlobalScopes()->insertOrIgnore([
            'team_id' => $storeId,
            'product_id' => $productId,
            'average_cost' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ProductCost::withoutGlobalScopes()
            ->where('product_id', $productId)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Get the pieces of the line's product on hand across all of the store's locations.
     */
    private function onHand(DocumentLine $line): int
    {
        return (int) StockBalance::withoutGlobalScopes()
            ->where('product_id', $line->product_id)
            ->sum('quantity');
    }

    /**
     * Round a positive cost half up to the kept digits.
     *
     * @param  numeric-string  $cost
     * @return numeric-string
     */
    private function round(string $cost): string
    {
        return bcadd($cost, '0.'.str_repeat('0', self::COST_SCALE).'5', self::COST_SCALE);
    }

    /**
     * Get the balance row of the line's product at a location, locked for this transaction.
     */
    private function lockedBalance(DocumentLine $line, int $locationId): StockBalance
    {
        $teamId = $line->document->team_id;

        StockBalance::withoutGlobalScopes()->insertOrIgnore([
            'team_id' => $teamId,
            'location_id' => $locationId,
            'product_id' => $line->product_id,
            'quantity' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StockBalance::withoutGlobalScopes()
            ->where('location_id', $locationId)
            ->where('product_id', $line->product_id)
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Append a movement of the line's product at a location.
     */
    private function recordMovement(DocumentLine $line, int $locationId, int $quantity): void
    {
        StockMovement::create([
            'team_id' => $line->document->team_id,
            'location_id' => $locationId,
            'product_id' => $line->product_id,
            'document_line_id' => $line->id,
            'quantity' => $quantity,
        ]);
    }
}
