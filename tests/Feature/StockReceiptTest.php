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
 * The Qoldiqlar quick receipt (+ Kirim): an executed one-line purchase made from the stock page.
 *
 * @return array{0: User, 1: Counterparty, 2: Location, 3: Product}
 */
function receiptStore(): array
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);

    return [
        $owner,
        Counterparty::factory()->for($owner->team)->create(),
        Location::factory()->for($owner->team)->create(),
        Product::factory()->for($owner->team)->create(['pieces_per_block' => 12, 'sale_price' => '1500']),
    ];
}

/**
 * @return array<string, mixed>
 */
function receiptInput(Counterparty $supplier, Location $location, Product $product, array $overrides = []): array
{
    return [
        'product_id' => $product->id,
        'location_id' => $location->id,
        'counterparty_id' => $supplier->id,
        'blocks' => 2,
        'pieces' => 1,
        'price' => '1000',
        'sale_price' => '1800',
        'paid_amount' => '20000',
        'payment_method' => 'cash',
        ...$overrides,
    ];
}

test('a receipt executes a purchase that pays, leaves the rest as debt and sets the sale price', function () {
    [$owner, $supplier, $location, $product] = receiptStore();
    $this->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '50000');

    $this->from(route('stock.index'))
        ->post(route('stock.receipts.store'), receiptInput($supplier, $location, $product))
        ->assertRedirect(route('stock.index'))
        ->assertSessionHasNoErrors();

    $purchase = Document::query()->sole();
    expect($purchase)
        ->kind->toBe(DocumentKind::Purchase)
        ->status->toBe(DocumentStatus::Executed)
        ->counterparty_id->toBe($supplier->id)
        ->location_id->toBe($location->id)
        ->and($purchase->lines->sole()->only(['product_id', 'quantity', 'price']))->toBe(['product_id' => $product->id, 'quantity' => 25, 'price' => '1000.00'])
        ->and(StockBalance::query()->where('location_id', $location->id)->value('quantity'))->toBe(25)
        ->and(app(CashRegister::class)->balances($owner->team)['cash'])->toBe('30000.00')
        ->and(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('5000.00')
        ->and($product->fresh()->sale_price)->toBe('1800.00');
});

test('a refused receipt saves nothing and reports why', function () {
    [$owner, $supplier, $location, $product] = receiptStore();

    $this->actingAs($owner)->from(route('stock.index'))
        ->post(route('stock.receipts.store'), receiptInput($supplier, $location, $product))
        ->assertSessionHasErrors('paid_amount');

    $owner->team->update(['opening_balance_skipped_at' => null]);
    $this->actingAs($owner)->from(route('stock.index'))
        ->post(route('stock.receipts.store'), receiptInput($supplier, $location, $product, ['paid_amount' => null]))
        ->assertSessionHasErrors('receipt');

    expect(Document::query()->count())->toBe(0)
        ->and(StockBalance::query()->count())->toBe(0)
        ->and($product->fresh()->sale_price)->toBe('1500.00');
});

test('a receipt needs pieces and may not pay more than its total', function () {
    [$owner, $supplier, $location, $product] = receiptStore();

    $this->actingAs($owner)
        ->post(route('stock.receipts.store'), receiptInput($supplier, $location, $product, ['blocks' => 0, 'pieces' => 0]))
        ->assertSessionHasErrors('pieces');
    $this->actingAs($owner)
        ->post(route('stock.receipts.store'), receiptInput($supplier, $location, $product, ['paid_amount' => '25001']))
        ->assertSessionHasErrors('paid_amount');

    expect(Document::query()->count())->toBe(0);
});

test('only users who may see costs and execute purchases get the receipt', function () {
    [$owner, $supplier, $location, $product] = receiptStore();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $this->actingAs($owner)->post(route('stock.receipts.store'), receiptInput($supplier, $location, $product, ['paid_amount' => null]));

    $this->actingAs($owner)->get(route('stock.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('receipt.previous_prices', [$product->id => '1000.00'])
            ->where('receipt.previous_suppliers', [$product->id => $supplier->id])
            ->where('receipt.products.0.sale_price', '1800.00'),
        );
    $this->actingAs($employee)->get(route('stock.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('receipt'));
    $this->actingAs($employee)
        ->post(route('stock.receipts.store'), receiptInput($supplier, $location, $product))
        ->assertForbidden();
});
