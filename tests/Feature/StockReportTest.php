<?php

use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\StockBalance;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('balances roll sub-warehouses up into their warehouse', function () {
    $owner = User::factory()->create();
    $main = Location::factory()->for($owner->team)->create(['name' => 'Asosiy ombor']);
    $fridge = Location::factory()->inside($main)->create(['name' => 'Muzlatgich']);
    $yard = Location::factory()->for($owner->team)->create(['name' => 'Hovli']);
    $cola = Product::factory()->for($owner->team)->create(['name' => 'Coca-Cola 1.5 L', 'pieces_per_block' => 6]);
    $water = Product::factory()->for($owner->team)->create(['name' => 'Suv 0.5 L']);
    StockBalance::factory()->for($main)->create(['product_id' => $cola, 'quantity' => 10]);
    StockBalance::factory()->for($fridge)->create(['product_id' => $cola, 'quantity' => 4]);
    StockBalance::factory()->for($yard)->create(['product_id' => $cola, 'quantity' => 6]);
    StockBalance::factory()->for($yard)->create(['product_id' => $water, 'quantity' => 0]);

    $this->actingAs($owner)->get(route('stock.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('stock/index')
            ->has('rows', 2)
            ->where('rows.0.location_name', 'Asosiy ombor')
            ->where('rows.0.product_name', 'Coca-Cola 1.5 L')
            ->where('rows.0.quantity', 14)
            ->where('rows.0.pieces_per_block', 6)
            ->where('rows.1.location_name', 'Hovli')
            ->where('rows.1.quantity', 6),
        );
});

test('filtering by a warehouse includes its sub-warehouses, by a sub-warehouse only itself', function () {
    $owner = User::factory()->create();
    $main = Location::factory()->for($owner->team)->create(['name' => 'Asosiy ombor']);
    $fridge = Location::factory()->inside($main)->create(['name' => 'Muzlatgich']);
    $yard = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    StockBalance::factory()->for($main)->create(['product_id' => $cola, 'quantity' => 10]);
    StockBalance::factory()->for($fridge)->create(['product_id' => $cola, 'quantity' => 4]);
    StockBalance::factory()->for($yard)->create(['product_id' => $cola, 'quantity' => 6]);

    $this->actingAs($owner)->get(route('stock.index', ['location_id' => $main->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.location_name', 'Asosiy ombor')
            ->where('rows.0.quantity', 14),
        );
    $this->actingAs($owner)->get(route('stock.index', ['location_id' => $fridge->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.location_name', 'Asosiy ombor / Muzlatgich')
            ->where('rows.0.quantity', 4),
        );
});

test('the search filters by product name or barcode', function (string $search) {
    $owner = User::factory()->create();
    $location = Location::factory()->for($owner->team)->create();
    StockBalance::factory()->for($location)->create(['product_id' => Product::factory()->for($owner->team)->create(['name' => 'Coca-Cola 1.5 L', 'barcode' => '4780000000017']), 'quantity' => 10]);
    StockBalance::factory()->for($location)->create(['product_id' => Product::factory()->for($owner->team)->create(['name' => 'Fanta 1 L', 'barcode' => '4780000000024']), 'quantity' => 10]);

    $this->actingAs($owner)->get(route('stock.index', ['search' => $search]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows', 1)
            ->where('rows.0.product_name', 'Fanta 1 L'),
        );
})->with([
    'part of the name, any case' => 'fanta',
    'part of the barcode' => '00024',
]);

test('the owner sees stock value at average cost', function () {
    $owner = User::factory()->create();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create(['name' => 'Coca-Cola 1.5 L']);
    $water = Product::factory()->for($owner->team)->create(['name' => 'Suv 0.5 L']);
    StockBalance::factory()->for($location)->create(['product_id' => $cola, 'quantity' => 7]);
    StockBalance::factory()->for($location)->create(['product_id' => $water, 'quantity' => 3]);
    ProductCost::factory()->for($cola)->create(['average_cost' => '1571.428571']);
    ProductCost::factory()->for($water)->create(['average_cost' => '1000']);

    $this->actingAs($owner)->get(route('stock.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.average_cost', '1571.43')
            ->where('rows.0.value', '11000.00')
            ->where('rows.1.value', '3000.00')
            ->where('total_value', '14000.00'),
        );
});

test('an employee sees balances without average cost or value', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    StockBalance::factory()->for($location)->create(['product_id' => $cola, 'quantity' => 7]);
    ProductCost::factory()->for($cola)->create(['average_cost' => '1000']);

    $this->actingAs($employee)->get(route('stock.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.0.quantity', 7)
            ->missing('rows.0.average_cost')
            ->missing('rows.0.value')
            ->missing('total_value'),
        );
});

test('each store sees only its own stock', function () {
    $owner = User::factory()->create();
    $foreign = Location::factory()->create();
    StockBalance::factory()->for($foreign)->create(['quantity' => 5]);

    $this->actingAs($owner)->get(route('stock.index'))
        ->assertInertia(fn (Assert $page) => $page->has('rows', 0));
});

test('a super-admin cannot open the stock report', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('stock.index'))
        ->assertForbidden();
});
