<?php

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\StockBalance;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Inventory counts over HTTP (spec: Seam 1): employees count blind and save drafts; the owner
 * reviews expected vs counted and executes.
 */
function storeForCounts(): User
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);

    return $owner;
}

/**
 * @param  array<int, array<string, mixed>>  $lines
 * @return array<string, mixed>
 */
function countInput(Location $location, array $lines, bool $execute = false): array
{
    return [
        'location_id' => $location->id,
        'note' => null,
        'lines' => $lines,
        'execute' => $execute,
    ];
}

test('an employee saves a count, even of zero pieces, and never receives expected values', function () {
    $owner = storeForCounts();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $warehouse = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create(['pieces_per_block' => 12]);
    $water = Product::factory()->for($owner->team)->create();
    StockBalance::factory()->create(['location_id' => $warehouse->id, 'product_id' => $cola->id, 'quantity' => 30]);
    ProductCost::factory()->create(['product_id' => $cola->id, 'average_cost' => 1000]);

    $this->actingAs($employee)->post(route('inventory-counts.store'), countInput($warehouse, [
        ['product_id' => $cola->id, 'blocks' => 2, 'pieces' => 1],
        ['product_id' => $water->id, 'blocks' => null, 'pieces' => null],
    ]))->assertRedirect(route('inventory-counts.show', $document = Document::query()->sole()));

    expect($document)
        ->kind->toBe(DocumentKind::InventoryCount)
        ->status->toBe(DocumentStatus::Draft)
        ->and($document->lines->pluck('quantity')->all())->toBe([25, 0]);
    $this->get(route('inventory-counts.show', $document))
        ->assertInertia(fn (Assert $page) => $page
            ->component('inventory-counts/show')
            ->where('stocked_products', [$warehouse->id => [$cola->id]])
            ->missing('expected')
            ->missing('average_costs'),
        );
});

test('the owner sees expected stock and average costs while reviewing a draft', function () {
    $owner = storeForCounts();
    $warehouse = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    StockBalance::factory()->create(['location_id' => $warehouse->id, 'product_id' => $cola->id, 'quantity' => 30]);
    ProductCost::factory()->create(['product_id' => $cola->id, 'average_cost' => '1000.5']);
    $this->actingAs($owner)->post(route('inventory-counts.store'), countInput($warehouse, [['product_id' => $cola->id, 'pieces' => 28]]));

    $this->get(route('inventory-counts.show', Document::query()->sole()))
        ->assertInertia(fn (Assert $page) => $page
            ->where('expected', [$warehouse->id => [$cola->id => 30]])
            ->where('average_costs', [$cola->id => '1000.500000']),
        );
});

test('an employee cannot execute a count', function () {
    $owner = storeForCounts();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $warehouse = Location::factory()->for($owner->team)->create();
    $input = countInput($warehouse, [['product_id' => Product::factory()->for($owner->team)->create()->id, 'pieces' => 3]], execute: true);

    $this->actingAs($employee)->post(route('inventory-counts.store'), $input)->assertForbidden();
    $this->actingAs($employee)->post(route('inventory-counts.store'), [...$input, 'execute' => false]);
    $this->actingAs($employee)->put(route('inventory-counts.update', Document::query()->sole()), $input)->assertForbidden();

    expect(Document::query()->sole()->status)->toBe(DocumentStatus::Draft)
        ->and(StockBalance::query()->count())->toBe(0);
});

test('the owner executes an employee\'s count; employees see the executed count blind', function () {
    $owner = storeForCounts();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $warehouse = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    StockBalance::factory()->create(['location_id' => $warehouse->id, 'product_id' => $cola->id, 'quantity' => 30]);
    ProductCost::factory()->create(['product_id' => $cola->id, 'average_cost' => 1000]);
    $this->actingAs($employee)->post(route('inventory-counts.store'), countInput($warehouse, [['product_id' => $cola->id, 'pieces' => 28]]));
    $document = Document::query()->sole();

    $this->actingAs($owner)->put(route('inventory-counts.update', $document), countInput($warehouse, [['product_id' => $cola->id, 'pieces' => 28]], execute: true))
        ->assertRedirect(route('inventory-counts.show', $document))
        ->assertSessionHasNoErrors();

    expect($document->fresh()->status)->toBe(DocumentStatus::Executed)
        ->and(StockBalance::query()->value('quantity'))->toBe(28);
    $this->get(route('inventory-counts.show', $document))
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.lines.0.expected_quantity', 30)
            ->where('document.lines.0.difference_value', '-2000.00'),
        );
    $this->actingAs($employee)->get(route('inventory-counts.show', $document))
        ->assertInertia(fn (Assert $page) => $page
            ->where('document.lines.0.quantity', 28)
            ->missing('document.lines.0.expected_quantity')
            ->missing('document.lines.0.difference_value'),
        );
});

test('a product can be listed only once in a count', function () {
    $owner = storeForCounts();
    $warehouse = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();

    $this->actingAs($owner)->post(route('inventory-counts.store'), countInput($warehouse, [
        ['product_id' => $cola->id, 'pieces' => 1],
        ['product_id' => $cola->id, 'pieces' => 2],
    ]))->assertSessionHasErrors(['lines.1.product_id' => "Bu mahsulot allaqachon qo'shilgan."]);
});
