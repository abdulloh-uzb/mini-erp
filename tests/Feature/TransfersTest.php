<?php

use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Transfers over HTTP (spec: Seam 1): employees and the owner move pieces between locations.
 */
function storeForTransfers(): User
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);

    return $owner;
}

/**
 * @param  array<int, array<string, mixed>>  $lines
 * @return array<string, mixed>
 */
function transferInput(Location $from, Location $to, array $lines, bool $execute = false): array
{
    return [
        'location_id' => $from->id,
        'destination_location_id' => $to->id,
        'note' => null,
        'lines' => $lines,
        'execute' => $execute,
    ];
}

test('an employee saves and executes a transfer in blocks and pieces', function () {
    $owner = storeForTransfers();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $warehouse = Location::factory()->for($owner->team)->create();
    $shelf = Location::factory()->inside($warehouse)->create();
    $cola = Product::factory()->for($owner->team)->create(['pieces_per_block' => 12]);
    StockBalance::factory()->create(['location_id' => $warehouse->id, 'product_id' => $cola->id, 'quantity' => 40]);

    $response = $this->actingAs($employee)->post(route('transfers.store'), transferInput($warehouse, $shelf, [
        ['product_id' => $cola->id, 'blocks' => 2, 'pieces' => 3],
    ], execute: true));

    $document = Document::query()->sole();
    $response->assertRedirect(route('transfers.show', $document))->assertSessionHasNoErrors();
    expect($document)
        ->kind->toBe(DocumentKind::Transfer)
        ->status->toBe(DocumentStatus::Executed)
        ->executor_id->toBe($employee->id)
        ->and(StockBalance::query()->pluck('quantity', 'location_id')->all())->toEqual([$warehouse->id => 13, $shelf->id => 27]);
});

test('the same from and to location is refused', function () {
    $owner = storeForTransfers();
    $warehouse = Location::factory()->for($owner->team)->create();

    $this->actingAs($owner)->post(route('transfers.store'), transferInput($warehouse, $warehouse, [
        ['product_id' => Product::factory()->for($owner->team)->create()->id, 'pieces' => 1],
    ]))->assertSessionHasErrors(['destination_location_id' => 'Bir xil joy tanlangan']);

    expect(Document::query()->count())->toBe(0);
});

test('the form shows the stock of every location so lines can show the source stock', function () {
    $owner = storeForTransfers();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $warehouse = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    StockBalance::factory()->create(['location_id' => $warehouse->id, 'product_id' => $cola->id, 'quantity' => 7]);

    $this->actingAs($employee)->get(route('transfers.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('transfers/show')
            ->where("stock.{$warehouse->id}.{$cola->id}", 7),
        );
});

test('reversing an executed transfer opens a new form with the locations swapped', function () {
    $owner = storeForTransfers();
    $warehouse = Location::factory()->for($owner->team)->create();
    $shelf = Location::factory()->inside($warehouse)->create();
    $cola = Product::factory()->for($owner->team)->create(['pieces_per_block' => 12]);
    StockBalance::factory()->create(['location_id' => $warehouse->id, 'product_id' => $cola->id, 'quantity' => 40]);
    $this->actingAs($owner)->post(route('transfers.store'), transferInput($warehouse, $shelf, [
        ['product_id' => $cola->id, 'blocks' => 2, 'pieces' => 3],
    ], execute: true));

    $this->get(route('transfers.create', ['reverse' => Document::query()->sole()->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->component('transfers/show')
            ->where('document', null)
            ->where('prefill.location_id', $shelf->id)
            ->where('prefill.destination_location_id', $warehouse->id)
            ->where('prefill.lines', [['product_id' => $cola->id, 'quantity' => 27, 'pieces_per_block' => 12]]),
        );
});
