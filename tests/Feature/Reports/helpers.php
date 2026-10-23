<?php

use App\Actions\Documents\ExecuteDocument;
use App\Enums\DocumentKind;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\StockBalance;
use App\Models\User;

/**
 * Shared setup for tests that need executed sales: a store with an owner, an employee and stocked products.
 */
function reportStore(): array
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);
    $employee = User::factory()->employeeOf($owner->team)->create();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create(['name' => 'Cola', 'pieces_per_block' => 6]);
    $water = Product::factory()->for($owner->team)->create(['name' => 'Suv', 'pieces_per_block' => 1]);

    foreach ([[$cola, '5000'], [$water, '1000']] as [$product, $cost]) {
        StockBalance::factory()->for($location)->create(['product_id' => $product->id, 'quantity' => 1000]);
        ProductCost::factory()->create(['product_id' => $product->id, 'average_cost' => $cost]);
    }

    return [$owner, $employee, $location, $cola, $water];
}

/**
 * @param  array<int, array{0: Product, 1: int, 2: string}>  $lines
 * @param  array<string, string>  $payments
 */
function executeSaleBy(User $user, Location $location, array $lines, array $payments, ?Counterparty $customer = null): Document
{
    test()->actingAs($user);
    $sale = Document::factory()->by($user)->kind(DocumentKind::Sale)->create(['location_id' => $location->id, 'counterparty_id' => $customer?->id]);

    foreach ($lines as [$product, $quantity, $price]) {
        DocumentLine::factory()->for($sale)->create(['product_id' => $product->id, 'quantity' => $quantity, 'price' => $price]);
    }

    foreach ($payments as $method => $amount) {
        $sale->payments()->create(['payment_method' => $method, 'amount' => $amount]);
    }

    app(ExecuteDocument::class)->handle($sale, $user);

    return $sale;
}
