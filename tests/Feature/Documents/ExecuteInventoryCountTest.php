<?php

use App\Actions\Documents\ExecuteDocument;
use App\Actions\Documents\StockLedger;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * Domain tests on executing an inventory count through the document execution module
 * (spec: Seam 2).
 */
function storeReadyToCount(): User
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);

    return $owner;
}

/**
 * Put pieces of a product at a location at the given cost, as an executed purchase would.
 */
function stockForCount(User $owner, Location $location, Product $product, int $quantity, string $cost): void
{
    $line = DocumentLine::factory()
        ->for(Document::factory()->by($owner)->kind(DocumentKind::Purchase)->executed())
        ->create(['product_id' => $product->id, 'quantity' => $quantity, 'price' => $cost]);

    app(StockLedger::class)->receive($line, $location->id, $quantity, $cost);
}

/**
 * @param  array<int, array{0: Product, 1: int}>  $lines  product, counted pieces
 */
function inventoryCount(User $author, Location $location, array $lines): Document
{
    $document = Document::factory()->by($author)->kind(DocumentKind::InventoryCount)->create(['location_id' => $location->id]);

    foreach ($lines as [$product, $counted]) {
        DocumentLine::factory()->for($document)->create(['product_id' => $product->id, 'quantity' => $counted, 'price' => null]);
    }

    return $document;
}

test('a count sets the listed products to what was counted and values each difference at average cost', function () {
    $owner = storeReadyToCount();
    $warehouse = Location::factory()->for($owner->team)->create();
    $shelf = Location::factory()->inside($warehouse)->create();
    [$cola, $water, $juice, $uncounted] = Product::factory()->for($owner->team)->count(4)->create();
    $this->actingAs($owner);
    stockForCount($owner, $warehouse, $cola, 10, '1000');
    stockForCount($owner, $warehouse, $cola, 20, '1300');
    stockForCount($owner, $shelf, $cola, 6, '1200');
    stockForCount($owner, $warehouse, $water, 10, '500.25');
    stockForCount($owner, $warehouse, $juice, 4, '2000');
    stockForCount($owner, $warehouse, $uncounted, 9, '700');

    $document = inventoryCount($owner, $warehouse, [[$cola, 27], [$water, 13], [$juice, 0]]);
    app(ExecuteDocument::class)->handle($document, $owner);

    expect($document->status)->toBe(DocumentStatus::Executed)
        ->and(StockBalance::query()->where('location_id', $warehouse->id)->pluck('quantity', 'product_id')->all())
        ->toEqual([$cola->id => 27, $water->id => 13, $juice->id => 0, $uncounted->id => 9])
        ->and(StockBalance::query()->where('location_id', $shelf->id)->value('quantity'))->toBe(6)
        ->and($document->lines()->get()->map->only(['product_id', 'quantity', 'expected_quantity', 'unit_cost', 'difference_value'])->all())
        ->toBe([
            ['product_id' => $cola->id, 'quantity' => 27, 'expected_quantity' => 30, 'unit_cost' => '1200.000000', 'difference_value' => '-3600.00'],
            ['product_id' => $water->id, 'quantity' => 13, 'expected_quantity' => 10, 'unit_cost' => '500.250000', 'difference_value' => '1500.75'],
            ['product_id' => $juice->id, 'quantity' => 0, 'expected_quantity' => 4, 'unit_cost' => '2000.000000', 'difference_value' => '-8000.00'],
        ])
        ->and(ProductCost::query()->where('product_id', $cola->id)->value('average_cost'))->toBe('1200.000000');
});

test('expected is recomputed at execution, so stock moved while counting is no difference', function () {
    $owner = storeReadyToCount();
    $warehouse = Location::factory()->for($owner->team)->create();
    $shelf = Location::factory()->inside($warehouse)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    stockForCount($owner, $warehouse, $cola, 20, '1000');
    $count = inventoryCount($owner, $warehouse, [[$cola, 15]]);

    $move = Document::factory()->by($owner)->kind(DocumentKind::Transfer)->create(['location_id' => $warehouse->id, 'destination_location_id' => $shelf->id]);
    DocumentLine::factory()->for($move)->create(['product_id' => $cola->id, 'quantity' => 5, 'price' => null]);
    app(ExecuteDocument::class)->handle($move, $owner);
    app(ExecuteDocument::class)->handle($count, $owner);

    expect($count->lines()->sole()->only(['expected_quantity', 'difference_value']))->toBe(['expected_quantity' => 15, 'difference_value' => '0.00'])
        ->and(StockBalance::query()->where('location_id', $warehouse->id)->value('quantity'))->toBe(15);
});

test('employees cannot execute a count', function () {
    $owner = storeReadyToCount();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $warehouse = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $this->actingAs($employee);
    $document = inventoryCount($employee, $warehouse, [[$cola, 3]]);

    expect(fn () => app(ExecuteDocument::class)->handle($document, $employee))->toThrow(AuthorizationException::class);
    expect($document->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(StockBalance::query()->count())->toBe(0);
});
