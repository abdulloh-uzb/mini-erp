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
use Illuminate\Validation\ValidationException;

/**
 * Domain tests on executing a transfer through the document execution module (spec: Seam 2).
 */
function storeReadyToTransfer(): User
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);

    return $owner;
}

/**
 * Put pieces of a product at a location at the given cost, as an executed purchase would.
 */
function stockForTransfer(User $owner, Location $location, Product $product, int $quantity, string $cost = '1000'): void
{
    $line = DocumentLine::factory()
        ->for(Document::factory()->by($owner)->kind(DocumentKind::Purchase)->executed())
        ->create(['product_id' => $product->id, 'quantity' => $quantity, 'price' => $cost]);

    app(StockLedger::class)->receive($line, $location->id, $quantity, $cost);
}

/**
 * @param  array<int, array{0: Product, 1: int}>  $lines  product, pieces
 */
function transfer(User $author, Location $from, Location $to, array $lines): Document
{
    $document = Document::factory()->by($author)->kind(DocumentKind::Transfer)->create([
        'location_id' => $from->id,
        'destination_location_id' => $to->id,
    ]);

    foreach ($lines as [$product, $quantity]) {
        DocumentLine::factory()->for($document)->create(['product_id' => $product->id, 'quantity' => $quantity, 'price' => null]);
    }

    return $document;
}

test('a transfer moves pieces between locations without touching the average cost', function () {
    $owner = storeReadyToTransfer();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $warehouse = Location::factory()->for($owner->team)->create();
    $shelf = Location::factory()->inside($warehouse)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $this->actingAs($employee);
    stockForTransfer($owner, $warehouse, $cola, 30, '1200');

    $document = transfer($employee, $warehouse, $shelf, [[$cola, 12]]);
    app(ExecuteDocument::class)->handle($document, $employee);

    expect($document->status)->toBe(DocumentStatus::Executed)
        ->and($document->executor_id)->toBe($employee->id)
        ->and(StockBalance::query()->where('product_id', $cola->id)->pluck('quantity', 'location_id')->all())
        ->toEqual([$warehouse->id => 18, $shelf->id => 12])
        ->and(ProductCost::query()->where('product_id', $cola->id)->value('average_cost'))->toBe('1200.000000');
});

test('a transfer above the source stock is refused per line and changes nothing', function () {
    $owner = storeReadyToTransfer();
    $warehouse = Location::factory()->for($owner->team)->create();
    $shelf = Location::factory()->inside($warehouse)->create();
    $cola = Product::factory()->for($owner->team)->create(['pieces_per_block' => 12]);
    $water = Product::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    stockForTransfer($owner, $warehouse, $cola, 16);
    stockForTransfer($owner, $warehouse, $water, 10);
    $document = transfer($owner, $warehouse, $shelf, [[$water, 10], [$cola, 17]]);

    try {
        app(ExecuteDocument::class)->handle($document, $owner);
        $this->fail('Execution should have been refused.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'lines.1.pieces' => ['Joyda yetarli emas. Qoldiq: 1 blok + 4 dona.'],
        ]);
    }

    expect($document->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(StockBalance::query()->where('location_id', $warehouse->id)->pluck('quantity', 'product_id')->all())
        ->toEqual([$cola->id => 16, $water->id => 10])
        ->and(StockBalance::query()->where('location_id', $shelf->id)->sum('quantity'))->toEqual(0);
});

test('a transfer to the same location is refused', function () {
    $owner = storeReadyToTransfer();
    $warehouse = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    stockForTransfer($owner, $warehouse, $cola, 5);

    expect(fn () => app(ExecuteDocument::class)->handle(transfer($owner, $warehouse, $warehouse, [[$cola, 5]]), $owner))
        ->toThrow(ValidationException::class, 'Bir xil joy tanlangan');
});
