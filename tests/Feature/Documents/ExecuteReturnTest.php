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
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\StockBalance;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Domain tests on executing sales returns and supplier returns through the document execution
 * module (spec: Seam 2).
 */
function storeReadyForReturns(): User
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);

    return $owner;
}

/**
 * Execute a document of the given kind with lines and planned payments.
 *
 * @param  array<int, array{0: Product, 1: int, 2: string}>  $lines  product, pieces, price per piece
 * @param  array<string, string>  $payments  amount per payment method value
 */
function executedSource(User $owner, DocumentKind $kind, Location $location, array $lines, array $payments = [], ?Counterparty $counterparty = null): Document
{
    $document = Document::factory()->by($owner)->kind($kind)->create([
        'location_id' => $location->id,
        'counterparty_id' => $counterparty?->id,
    ]);

    foreach ($lines as [$product, $quantity, $price]) {
        DocumentLine::factory()->for($document)->create(['product_id' => $product->id, 'quantity' => $quantity, 'price' => $price]);
    }

    foreach ($payments as $method => $amount) {
        $document->payments()->create(['payment_method' => $method, 'amount' => $amount]);
    }

    app(ExecuteDocument::class)->handle($document, $owner);

    return $document;
}

/**
 * Draft a return of the source with one line per source line, in its order.
 *
 * @param  array<int, int>  $quantities  pieces per source line
 */
function draftReturn(Document $source, User $author, array $quantities, ?Location $location = null, ?PaymentMethod $refundMethod = PaymentMethod::Cash): Document
{
    $return = Document::factory()->by($author)->kind($source->kind === DocumentKind::Sale ? DocumentKind::SalesReturn : DocumentKind::SupplierReturn)->create([
        'source_document_id' => $source->id,
        'location_id' => $location->id ?? $source->location_id,
        'counterparty_id' => $source->counterparty_id,
        'refund_method' => $refundMethod,
    ]);

    foreach ($source->lines()->get()->values() as $index => $line) {
        $return->lines()->create([
            'product_id' => $line->product_id,
            'source_line_id' => $line->id,
            'quantity' => $quantities[$index] ?? 0,
            'pieces_per_block' => $line->pieces_per_block,
            'price' => $line->price,
        ]);
    }

    return $return;
}

test('a sale can be returned across several returns only up to what was sold', function () {
    $owner = storeReadyForReturns();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create(['pieces_per_block' => 6]);
    $this->actingAs($owner);
    executedSource($owner, DocumentKind::Purchase, $location, [[$cola, 20, '1000']], counterparty: Counterparty::factory()->for($owner->team)->create());
    $sale = executedSource($owner, DocumentKind::Sale, $location, [[$cola, 15, '2000']], ['cash' => '30000']);

    app(ExecuteDocument::class)->handle(draftReturn($sale, $owner, [10]), $owner);
    draftReturn($sale, $owner, [4]);
    $tooMuch = draftReturn($sale, $owner, [6]);

    expect(fn () => app(ExecuteDocument::class)->handle($tooMuch, $owner))
        ->toThrow(ValidationException::class, "Ko'pi bilan 0 blok + 5 dona qaytarish mumkin");
    expect($tooMuch->fresh()->status)->toBe(DocumentStatus::Draft);

    app(ExecuteDocument::class)->handle(draftReturn($sale, $owner, [5]), $owner);

    expect(StockBalance::query()->where('product_id', $cola->id)->value('quantity'))->toBe(20);
});

test('a sales return brings pieces back at the sale line\'s stored cost, blending into the average', function () {
    $owner = storeReadyForReturns();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create(['pieces_per_block' => 1]);
    $supplier = Counterparty::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    executedSource($owner, DocumentKind::Purchase, $location, [[$cola, 10, '1000']], counterparty: $supplier);
    $sale = executedSource($owner, DocumentKind::Sale, $location, [[$cola, 10, '2000']], ['cash' => '20000']);
    executedSource($owner, DocumentKind::Purchase, $location, [[$cola, 10, '1600']], counterparty: $supplier);

    $return = draftReturn($sale, $owner, [5]);
    app(ExecuteDocument::class)->handle($return, $owner);

    expect(ProductCost::query()->where('product_id', $cola->id)->value('average_cost'))->toBe('1400.000000')
        ->and($return->lines()->sole()->only(['quantity', 'price', 'unit_cost']))->toBe(['quantity' => 5, 'price' => '2000.00', 'unit_cost' => '1000.000000']);
});

test('a sales return reduces the customer\'s debt first and refunds the rest in the chosen method', function () {
    $owner = storeReadyForReturns();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $customer = Counterparty::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    executedSource($owner, DocumentKind::Purchase, $location, [[$cola, 10, '1000']], counterparty: Counterparty::factory()->for($owner->team)->create());
    $sale = executedSource($owner, DocumentKind::Sale, $location, [[$cola, 10, '2000']], ['cash' => '5000'], $customer);

    $return = draftReturn($sale, $owner, [8]);
    app(ExecuteDocument::class)->handle($return, $owner);

    expect(app(DebtLedger::class)->balance($customer, DebtSide::Customer))->toBe('0.00')
        ->and(app(CashRegister::class)->balances($owner->team)['cash'])->toBe('4000.00')
        ->and(CashOperation::query()->where('type', CashOperationType::SalesReturnRefund)->sole()->only(['amount', 'counterparty_id', 'document_id']))
        ->toBe(['amount' => '1000.00', 'counterparty_id' => $customer->id, 'document_id' => $return->id]);
});

test('a sales return refund the method cannot cover is refused and changes nothing', function () {
    $owner = storeReadyForReturns();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    executedSource($owner, DocumentKind::Purchase, $location, [[$cola, 10, '1000']], counterparty: Counterparty::factory()->for($owner->team)->create());
    $sale = executedSource($owner, DocumentKind::Sale, $location, [[$cola, 10, '2000']], ['cash' => '20000']);

    $return = draftReturn($sale, $owner, [3], refundMethod: PaymentMethod::Card);

    expect(fn () => app(ExecuteDocument::class)->handle($return, $owner))
        ->toThrow(ValidationException::class, "Kassada karta bo'yicha mablag' yetarli emas");
    expect($return->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(StockBalance::query()->where('product_id', $cola->id)->value('quantity'))->toBe(0);
});

test('a supplier return takes pieces out of the average at the purchase price and reduces supplier debt first', function () {
    $owner = storeReadyForReturns();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $supplier = Counterparty::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::Income, PaymentMethod::Cash, '17000');
    executedSource($owner, DocumentKind::Purchase, $location, [[$cola, 10, '1000']], ['cash' => '10000'], $supplier);
    $purchase = executedSource($owner, DocumentKind::Purchase, $location, [[$cola, 10, '2000']], ['cash' => '7000'], $supplier);

    $return = draftReturn($purchase, $owner, [4]);
    app(ExecuteDocument::class)->handle($return, $owner);

    expect(ProductCost::query()->where('product_id', $cola->id)->value('average_cost'))->toBe('1375.000000')
        ->and(StockBalance::query()->where('product_id', $cola->id)->value('quantity'))->toBe(16)
        ->and(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('5000.00')
        ->and(CashOperation::query()->where('type', CashOperationType::SupplierReturnRefund)->exists())->toBeFalse();

    app(ExecuteDocument::class)->handle(draftReturn($purchase, $owner, [3]), $owner);

    expect(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('0.00')
        ->and(app(CashRegister::class)->balances($owner->team)['cash'])->toBe('1000.00');
});

test('a supplier return that empties the stock keeps the average cost', function () {
    $owner = storeReadyForReturns();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    $purchase = executedSource($owner, DocumentKind::Purchase, $location, [[$cola, 5, '1200']], counterparty: Counterparty::factory()->for($owner->team)->create());

    app(ExecuteDocument::class)->handle(draftReturn($purchase, $owner, [5]), $owner);

    expect(ProductCost::query()->where('product_id', $cola->id)->value('average_cost'))->toBe('1200.000000')
        ->and(StockBalance::query()->where('product_id', $cola->id)->value('quantity'))->toBe(0);
});

test('a supplier return is refused when its location lacks the pieces', function () {
    $owner = storeReadyForReturns();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create(['pieces_per_block' => 1]);
    $supplier = Counterparty::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    $purchase = executedSource($owner, DocumentKind::Purchase, $location, [[$cola, 10, '1000']], counterparty: $supplier);
    executedSource($owner, DocumentKind::Sale, $location, [[$cola, 8, '2000']], ['cash' => '16000']);

    $return = draftReturn($purchase, $owner, [5]);

    expect(fn () => app(ExecuteDocument::class)->handle($return, $owner))
        ->toThrow(ValidationException::class, 'Joyda yetarli emas. Qoldiq: 2 dona.');
    expect($return->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('10000.00');
});
