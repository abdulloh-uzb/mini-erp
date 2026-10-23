<?php

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\DebtLedger;
use App\Enums\CashOperationType;
use App\Enums\DebtSide;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Purchases over HTTP (spec: Seam 1): employees draft without prices, the owner prices, pays
 * and executes.
 */
function settledStore(): User
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);

    return $owner;
}

/**
 * @param  array<int, array<string, mixed>>  $lines
 * @param  array<string, string|null>  $payments
 * @return array<string, mixed>
 */
function purchaseInput(Counterparty $supplier, Location $location, array $lines, array $payments = [], bool $execute = false): array
{
    return [
        'counterparty_id' => $supplier->id,
        'location_id' => $location->id,
        'note' => null,
        'lines' => $lines,
        'payments' => $payments,
        'execute' => $execute,
    ];
}

test('an employee saves a purchase draft whose prices and payments are never taken from them', function () {
    $owner = settledStore();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $supplier = Counterparty::factory()->for($owner->team)->create();
    $location = Location::factory()->for($owner->team)->create();
    $product = Product::factory()->for($owner->team)->create(['pieces_per_block' => 12]);

    $response = $this->actingAs($employee)->post(route('purchases.store'), purchaseInput($supplier, $location, [
        ['product_id' => $product->id, 'blocks' => 2, 'pieces' => 1, 'price' => '5000'],
    ], ['cash' => '10000']));

    $document = Document::query()->sole();
    $response->assertRedirect(route('purchases.show', $document));
    expect($document)
        ->kind->toBe(DocumentKind::Purchase)
        ->status->toBe(DocumentStatus::Draft)
        ->counterparty_id->toBe($supplier->id)
        ->location_id->toBe($location->id)
        ->author_id->toBe($employee->id)
        ->and($document->lines->sole()->only(['quantity', 'price']))->toBe(['quantity' => 25, 'price' => null])
        ->and($document->payments)->toBeEmpty();
});

test('an employee opening a purchase receives no prices, payments or previous prices', function () {
    $owner = settledStore();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $location = Location::factory()->for($owner->team)->create();
    $this->actingAs($owner)->post(route('purchases.store'), purchaseInput(
        Counterparty::factory()->for($owner->team)->create(),
        $location,
        [['product_id' => Product::factory()->for($owner->team)->create()->id, 'pieces' => 5, 'price' => '3000']],
        ['cash' => '0'],
    ));
    $document = Document::query()->sole();

    $this->actingAs($employee)->get(route('purchases.show', $document))
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/show')
            ->where('document.lines.0.quantity', 5)
            ->missing('document.lines.0.price')
            ->missing('document.payments')
            ->missing('previous_prices')
            ->missing('supplier_debts'),
        );
    $this->actingAs($employee)->get(route('purchases.create'))
        ->assertInertia(fn (Assert $page) => $page->component('purchases/show')->missing('previous_prices'));
});

test('an employee cannot execute a purchase', function () {
    $owner = settledStore();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $location = Location::factory()->for($owner->team)->create();
    $input = purchaseInput(Counterparty::factory()->for($owner->team)->create(), $location, [
        ['product_id' => Product::factory()->for($owner->team)->create()->id, 'pieces' => 5],
    ], execute: true);

    $this->actingAs($employee)->post(route('purchases.store'), $input)->assertForbidden();
    $this->actingAs($employee)->post(route('purchases.store'), [...$input, 'execute' => false]);
    $this->actingAs($employee)->put(route('purchases.update', Document::query()->sole()), $input)->assertForbidden();

    expect(Document::query()->sole()->status)->toBe(DocumentStatus::Draft)
        ->and(StockBalance::query()->count())->toBe(0);
});

test('the owner prices and pays an employee\'s draft, then executes it', function () {
    $owner = settledStore();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $supplier = Counterparty::factory()->for($owner->team)->create();
    $location = Location::factory()->for($owner->team)->create();
    $product = Product::factory()->for($owner->team)->create(['pieces_per_block' => 12]);
    $this->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '500000');
    $this->actingAs($employee)->post(route('purchases.store'), purchaseInput($supplier, $location, [
        ['product_id' => $product->id, 'blocks' => 10],
    ]));
    $document = Document::query()->sole();

    $this->actingAs($owner)->put(route('purchases.update', $document), purchaseInput($supplier, $location, [
        ['product_id' => $product->id, 'blocks' => 10, 'price' => '1000'],
    ], ['cash' => '20000', 'card' => null], execute: true))
        ->assertRedirect(route('purchases.show', $document))
        ->assertSessionHasNoErrors();

    expect($document->fresh())
        ->status->toBe(DocumentStatus::Executed)
        ->executor_id->toBe($owner->id)
        ->author_id->toBe($employee->id)
        ->and(StockBalance::query()->where('location_id', $location->id)->value('quantity'))->toBe(120)
        ->and(app(CashRegister::class)->balances($owner->team)['cash'])->toBe('480000.00')
        ->and(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('100000.00');
});

test('a refused execution keeps the draft saved and returns its errors', function () {
    $owner = settledStore();
    $location = Location::factory()->for($owner->team)->create();

    $this->actingAs($owner)->post(route('purchases.store'), purchaseInput(Counterparty::factory()->for($owner->team)->create(), $location, [
        ['product_id' => Product::factory()->for($owner->team)->create()->id, 'pieces' => 3],
    ], execute: true))
        ->assertRedirect(route('purchases.show', Document::query()->sole()))
        ->assertSessionHasErrors(['document' => 'Barcha narxlarni kiriting.', 'lines.0.price' => 'Narxni kiriting.']);

    expect(Document::query()->sole()->status)->toBe(DocumentStatus::Draft);
});

test('the owner\'s form pre-fills from the last executed purchase price of each product', function () {
    $owner = settledStore();
    $location = Location::factory()->for($owner->team)->create();
    $supplier = Counterparty::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $water = Product::factory()->for($owner->team)->create();
    $neverBought = Product::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    $buy = fn (string $price, bool $execute = true) => $this->post(route('purchases.store'), purchaseInput($supplier, $location, [
        ['product_id' => $cola->id, 'pieces' => 1, 'price' => $price],
        ['product_id' => $water->id, 'pieces' => 1, 'price' => '700'],
    ], execute: $execute));
    $buy('9000');
    $this->travel(1)->minutes();
    $buy('9500');
    $buy('9900', execute: false);

    $this->get(route('purchases.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('purchases/show')
            ->where('previous_prices', [$cola->id => '9500.00', $water->id => '700.00'])
            ->where('supplier_debts', [$supplier->id => '19900.00'])
            ->missing("previous_prices.{$neverBought->id}"),
        );
});
