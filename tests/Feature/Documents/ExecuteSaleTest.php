<?php

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\DebtLedger;
use App\Actions\Documents\ExecuteDocument;
use App\Enums\CashOperationType;
use App\Enums\DebtSide;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\CashOperation;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Domain tests on executing a sale through the document execution module (spec: Seam 2).
 */
function ownerReadyToSell(): User
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);

    return $owner;
}

/**
 * Bring pieces into a location at a cost per piece by executing a purchase.
 */
function stockUp(User $owner, Location $location, Product $product, int $quantity, string $cost): void
{
    $purchase = Document::factory()->by($owner)->kind(DocumentKind::Purchase)->create([
        'location_id' => $location->id,
        'counterparty_id' => Counterparty::factory()->for($owner->team)->create()->id,
    ]);
    DocumentLine::factory()->for($purchase)->create(['product_id' => $product->id, 'quantity' => $quantity, 'price' => $cost]);

    app(ExecuteDocument::class)->handle($purchase, $owner);
}

/**
 * @param  array<int, array{0: Product, 1: int, 2: string|null}>  $lines  product, pieces, price per piece
 * @param  array<string, string>  $payments  amount per payment method value
 */
function sale(User $author, Location $location, array $lines, array $payments = [], ?Counterparty $customer = null): Document
{
    $document = Document::factory()->by($author)->kind(DocumentKind::Sale)->create([
        'location_id' => $location->id,
        'counterparty_id' => $customer?->id,
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

test('a sale takes its pieces out of its location and keeps the average cost on each line', function () {
    $owner = ownerReadyToSell();
    $warehouse = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    stockUp($owner, $warehouse, $cola, 10, '1000');
    stockUp($owner, $warehouse, $cola, 10, '1500');

    $document = sale($owner, $warehouse, [[$cola, 4, '2000']], ['cash' => '8000']);
    app(ExecuteDocument::class)->handle($document, $owner);

    expect(StockBalance::query()->where('product_id', $cola->id)->value('quantity'))->toBe(16)
        ->and($document->lines()->sole()->unit_cost)->toBe('1250.000000');
});

test('the paid part enters the register per method and the rest becomes the customer\'s debt', function () {
    $owner = ownerReadyToSell();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $customer = Counterparty::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    stockUp($owner, $location, $cola, 24, '5000');

    $document = sale($owner, $location, [[$cola, 12, '7000']], ['cash' => '50000', 'card' => '4000'], $customer);
    app(ExecuteDocument::class)->handle($document, $owner);

    expect(app(CashRegister::class)->balances($owner->team))->toBe(['cash' => '50000.00', 'card' => '4000.00', 'bank_transfer' => '0.00'])
        ->and(CashOperation::query()->where('type', CashOperationType::SalePayment)->get(['counterparty_id', 'document_id'])->toArray())
        ->toBe(array_fill(0, 2, ['counterparty_id' => $customer->id, 'document_id' => $document->id]))
        ->and(app(DebtLedger::class)->balance($customer, DebtSide::Customer))->toBe('30000.00');
});

test('payments above the sale total are refused and change nothing', function () {
    $owner = ownerReadyToSell();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    stockUp($owner, $location, $cola, 10, '1000');
    $document = sale($owner, $location, [[$cola, 5, '2000']], ['cash' => '9000', 'card' => '1001']);

    expect(fn () => app(ExecuteDocument::class)->handle($document, $owner))
        ->toThrow(ValidationException::class, "To'lov summasi jamidan oshib ketdi.");
    expect($document->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(StockBalance::query()->where('product_id', $cola->id)->value('quantity'))->toBe(10)
        ->and(CashOperation::query()->where('type', CashOperationType::SalePayment)->count())->toBe(0);
});

test('a sale left partly unpaid without a customer is refused', function () {
    $owner = ownerReadyToSell();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    stockUp($owner, $location, $cola, 10, '1000');
    $document = sale($owner, $location, [[$cola, 5, '2000']], ['cash' => '6000']);

    try {
        app(ExecuteDocument::class)->handle($document, $owner);
        $this->fail('Execution should have been refused.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['counterparty_id' => ['Qarzga sotish uchun mijozni tanlang.']]);
    }

    expect($document->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(StockBalance::query()->where('product_id', $cola->id)->value('quantity'))->toBe(10);
});

test('a sale with a missing price or a line beyond the location\'s stock is refused per line', function () {
    $owner = ownerReadyToSell();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create(['pieces_per_block' => 6]);
    $fanta = Product::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    stockUp($owner, $location, $cola, 10, '1000');
    stockUp($owner, $location, $fanta, 10, '1000');
    $document = sale($owner, $location, [[$cola, 11, '2000'], [$fanta, 1, null]]);

    try {
        app(ExecuteDocument::class)->handle($document, $owner);
        $this->fail('Execution should have been refused.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe([
            'lines.0.pieces' => ['Joyda yetarli emas. Qoldiq: 1 blok + 4 dona.'],
            'lines.1.price' => ['Narxni kiriting.'],
        ]);
    }

    expect(StockBalance::query()->pluck('quantity', 'product_id')->all())->toEqual([$cola->id => 10, $fanta->id => 10]);
});

test('two sales of the same stock executed one after the other never oversell it', function () {
    $owner = ownerReadyToSell();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create(['pieces_per_block' => 1]);
    $this->actingAs($owner);
    stockUp($owner, $location, $cola, 10, '1000');
    $first = sale($owner, $location, [[$cola, 6, '2000']], ['cash' => '12000']);
    $second = sale($employee, $location, [[$cola, 6, '2000']], ['cash' => '12000']);

    app(ExecuteDocument::class)->handle($first, $owner);

    expect(fn () => app(ExecuteDocument::class)->handle($second, $employee))
        ->toThrow(ValidationException::class, 'Joyda yetarli emas. Qoldiq: 4 dona.');
    expect(StockBalance::query()->where('product_id', $cola->id)->value('quantity'))->toBe(4)
        ->and($second->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(app(CashRegister::class)->balances($owner->team)['cash'])->toBe('12000.00');
});
