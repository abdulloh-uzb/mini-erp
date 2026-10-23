<?php

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\DebtLedger;
use App\Actions\Documents\ExecuteDocument;
use App\Enums\CashOperationType;
use App\Enums\DebtSide;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Enums\PaymentMethod;
use App\Models\CashOperation;
use App\Models\Counterparty;
use App\Models\DebtEntry;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Domain tests on executing a purchase through the document execution module (spec: Seam 2).
 */
function ownerReadyToPurchase(): User
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);

    return $owner;
}

/**
 * @param  array<int, array{0: Product, 1: int, 2: string|null}>  $lines  product, pieces, price per piece
 * @param  array<string, string>  $payments  amount per payment method value
 */
function purchase(User $owner, Location $location, array $lines, array $payments = [], ?Counterparty $supplier = null): Document
{
    $document = Document::factory()->by($owner)->kind(DocumentKind::Purchase)->create([
        'location_id' => $location->id,
        'counterparty_id' => ($supplier ?? Counterparty::factory()->for($owner->team)->create())->id,
    ]);

    foreach ($lines as [$product, $quantity, $price]) {
        DocumentLine::factory()->for($document)->create([
            'product_id' => $product->id,
            'quantity' => $quantity,
            'price' => $price,
        ]);
    }

    foreach ($payments as $method => $amount) {
        $document->payments()->create(['payment_method' => $method, 'amount' => $amount]);
    }

    return $document;
}

test('purchases add stock at their location and blend the average cost store-wide', function () {
    $owner = ownerReadyToPurchase();
    $warehouse = Location::factory()->for($owner->team)->create();
    $shelf = Location::factory()->inside($warehouse)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    $execute = app(ExecuteDocument::class);

    $execute->handle(purchase($owner, $warehouse, [[$cola, 10, '1000']]), $owner);
    $execute->handle(purchase($owner, $warehouse, [[$cola, 20, '1300']]), $owner);
    $execute->handle(purchase($owner, $shelf, [[$cola, 30, '1500']]), $owner);

    expect(ProductCost::query()->where('product_id', $cola->id)->value('average_cost'))->toBe('1350.000000')
        ->and(StockBalance::query()->where('product_id', $cola->id)->pluck('quantity', 'location_id')->all())
        ->toEqual([$warehouse->id => 30, $shelf->id => 30]);
});

test('a purchase with any price missing is refused and changes nothing', function () {
    $owner = ownerReadyToPurchase();
    $location = Location::factory()->for($owner->team)->create();
    $document = purchase($owner, $location, [
        [Product::factory()->for($owner->team)->create(), 10, '1000'],
        [Product::factory()->for($owner->team)->create(), 5, null],
    ]);
    $this->actingAs($owner);

    try {
        app(ExecuteDocument::class)->handle($document, $owner);
        $this->fail('Execution should have been refused.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'document' => ['Barcha narxlarni kiriting.'],
            'lines.1.price' => ['Narxni kiriting.'],
        ]);
    }

    expect($document->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(StockBalance::query()->count())->toBe(0);
});

test('the paid part leaves the register per method and the rest becomes supplier debt', function () {
    $owner = ownerReadyToPurchase();
    $location = Location::factory()->for($owner->team)->create();
    $supplier = Counterparty::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    $register = app(CashRegister::class);
    $register->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '100000');
    $register->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Card, '50000');
    $document = purchase($owner, $location, [
        [Product::factory()->for($owner->team)->create(), 12, '5000'],
        [Product::factory()->for($owner->team)->create(), 6, '10000'],
    ], ['cash' => '30000', 'card' => '20000'], $supplier);

    app(ExecuteDocument::class)->handle($document, $owner);

    expect($register->balances($owner->team))->toBe(['cash' => '70000.00', 'card' => '30000.00', 'bank_transfer' => '0.00'])
        ->and(CashOperation::query()->where('type', CashOperationType::PurchasePayment)->get(['counterparty_id', 'document_id'])->toArray())
        ->toBe(array_fill(0, 2, ['counterparty_id' => $supplier->id, 'document_id' => $document->id]))
        ->and(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('70000.00')
        ->and(app(DebtLedger::class)->balance($supplier, DebtSide::Customer))->toBe('0.00');
});

test('a fully paid purchase leaves no supplier debt', function () {
    $owner = ownerReadyToPurchase();
    $supplier = Counterparty::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::BankTransfer, '60000');

    app(ExecuteDocument::class)->handle(purchase($owner, Location::factory()->for($owner->team)->create(), [
        [Product::factory()->for($owner->team)->create(), 6, '10000'],
    ], ['bank_transfer' => '60000'], $supplier), $owner);

    expect(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('0.00')
        ->and(DebtEntry::query()->count())->toBe(0);
});

test('payments above the purchase total are refused', function () {
    $owner = ownerReadyToPurchase();
    $this->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '100000');
    $document = purchase($owner, Location::factory()->for($owner->team)->create(), [
        [Product::factory()->for($owner->team)->create(), 5, '10000'],
    ], ['cash' => '40000', 'card' => '10001']);

    expect(fn () => app(ExecuteDocument::class)->handle($document, $owner))
        ->toThrow(ValidationException::class, "To'lov summasi jamidan oshib ketdi.");
    expect($document->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(CashOperation::query()->where('type', CashOperationType::PurchasePayment)->count())->toBe(0)
        ->and(StockBalance::query()->count())->toBe(0);
});

test('a payment above the method\'s register balance is refused and changes nothing', function () {
    $owner = ownerReadyToPurchase();
    $supplier = Counterparty::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '20000');
    $document = purchase($owner, Location::factory()->for($owner->team)->create(), [
        [Product::factory()->for($owner->team)->create(), 5, '10000'],
    ], ['cash' => '20000.01'], $supplier);

    try {
        app(ExecuteDocument::class)->handle($document, $owner);
        $this->fail('Execution should have been refused.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['payments.cash' => ['Kassada naqd yetarli emas']]);
    }

    expect($document->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(app(CashRegister::class)->balances($owner->team)['cash'])->toBe('20000.00')
        ->and(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('0.00')
        ->and(StockBalance::query()->count())->toBe(0)
        ->and(ProductCost::query()->value('average_cost'))->toBeNull();
});
