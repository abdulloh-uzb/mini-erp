<?php

use App\Actions\Cash\DebtLedger;
use App\Actions\Documents\ExecuteDocument;
use App\Enums\DebtSide;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Sales returns and supplier returns over HTTP (spec: Seam 1): who may draft and execute each
 * kind, and the returnable limit on the form.
 */
function storeWithReturnables(): User
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);

    return $owner;
}

/**
 * Execute a purchase of 24 pieces (2 blocks) at 1000 on debt, and a sale of 12 of them at
 * 2000 paid in cash, at one location.
 *
 * @return array{purchase: Document, sale: Document, location: Location, product: Product}
 */
function purchasedAndSold(User $owner): array
{
    test()->actingAs($owner);
    $location = Location::factory()->for($owner->team)->create();
    $product = Product::factory()->for($owner->team)->create(['pieces_per_block' => 12]);

    $execute = function (DocumentKind $kind, int $quantity, string $price, array $payments) use ($owner, $location, $product) {
        $document = Document::factory()->by($owner)->kind($kind)->create([
            'location_id' => $location->id,
            'counterparty_id' => Counterparty::factory()->for($owner->team)->create()->id,
        ]);
        DocumentLine::factory()->for($document)->create(['product_id' => $product->id, 'quantity' => $quantity, 'price' => $price]);

        foreach ($payments as $method => $amount) {
            $document->payments()->create(['payment_method' => $method, 'amount' => $amount]);
        }

        app(ExecuteDocument::class)->handle($document, $owner);

        return $document;
    };

    return [
        'purchase' => $execute(DocumentKind::Purchase, 24, '1000', []),
        'sale' => $execute(DocumentKind::Sale, 12, '2000', ['cash' => '24000']),
        'location' => $location,
        'product' => $product,
    ];
}

/**
 * @return array<string, mixed>
 */
function returnInput(Document $source, int $blocks, int $pieces, bool $execute = false): array
{
    return [
        'source_document_id' => $source->id,
        'location_id' => $source->location_id,
        'refund_method' => 'cash',
        'note' => null,
        'lines' => [['source_line_id' => $source->lines()->sole()->id, 'blocks' => $blocks, 'pieces' => $pieces]],
        'execute' => $execute,
    ];
}

test('an employee opens a return of an executed sale with its lines at 0 beside sold, returned and returnable', function () {
    $owner = storeWithReturnables();
    $employee = User::factory()->employeeOf($owner->team)->create();
    ['sale' => $sale] = purchasedAndSold($owner);

    $this->actingAs($employee)->get(route('sales-returns.create', ['source' => $sale->id]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('returns/show')
            ->where('document', null)
            ->where('source.number', $sale->number)
            ->where('lines.0.price', '2000.00')
            ->where('lines.0.sold', 12)
            ->where('lines.0.returned', 0)
            ->where('lines.0.returnable', 12)
            ->where('lines.0.quantity', 0)
            ->where('default_refund_method', 'cash'));
});

test('an employee drafts a sales return but only the owner executes it', function () {
    $owner = storeWithReturnables();
    $employee = User::factory()->employeeOf($owner->team)->create();
    ['sale' => $sale, 'product' => $product] = purchasedAndSold($owner);

    $this->actingAs($employee)->post(route('sales-returns.store'), returnInput($sale, 0, 5, execute: true))->assertForbidden();

    $this->actingAs($employee)->post(route('sales-returns.store'), returnInput($sale, 0, 5));
    $return = Document::query()->ofKind(DocumentKind::SalesReturn)->sole();

    expect($return)
        ->status->toBe(DocumentStatus::Draft)
        ->source_document_id->toBe($sale->id)
        ->counterparty_id->toBe($sale->counterparty_id)
        ->and($return->lines()->sole()->only(['quantity', 'price']))->toBe(['quantity' => 5, 'price' => '2000.00']);

    $this->actingAs($owner)->put(route('sales-returns.update', $return), returnInput($sale, 0, 5, execute: true))
        ->assertRedirect(route('sales-returns.show', $return));

    expect($return->fresh()->status)->toBe(DocumentStatus::Executed)
        ->and(StockBalance::query()->where('product_id', $product->id)->value('quantity'))->toBe(17);
});

test('a return above what is still returnable is refused on the line', function () {
    $owner = storeWithReturnables();
    ['sale' => $sale] = purchasedAndSold($owner);
    $this->actingAs($owner)->post(route('sales-returns.store'), returnInput($sale, 0, 4, execute: true));

    $this->actingAs($owner)->post(route('sales-returns.store'), returnInput($sale, 0, 9))
        ->assertSessionHasErrors(['lines.0.pieces' => "Ko'pi bilan 0 blok + 8 dona qaytarish mumkin"]);
});

test('supplier returns are hidden from employees', function () {
    $owner = storeWithReturnables();
    $employee = User::factory()->employeeOf($owner->team)->create();
    ['purchase' => $purchase] = purchasedAndSold($owner);
    $this->actingAs($owner)->post(route('supplier-returns.store'), returnInput($purchase, 0, 1));
    $draft = Document::query()->ofKind(DocumentKind::SupplierReturn)->sole();

    $this->actingAs($employee)->get(route('supplier-returns.index'))->assertForbidden();
    $this->actingAs($employee)->get(route('supplier-returns.create', ['source' => $purchase->id]))->assertForbidden();
    $this->actingAs($employee)->post(route('supplier-returns.store'), returnInput($purchase, 0, 1))->assertForbidden();
    $this->actingAs($employee)->get(route('supplier-returns.show', $draft))->assertForbidden();
});

test('the owner executes a supplier return that reduces the supplier debt', function () {
    $owner = storeWithReturnables();
    ['purchase' => $purchase] = purchasedAndSold($owner);

    $this->actingAs($owner)->post(route('supplier-returns.store'), returnInput($purchase, 1, 0, execute: true))
        ->assertSessionHasNoErrors();

    expect(Document::query()->ofKind(DocumentKind::SupplierReturn)->sole()->status)->toBe(DocumentStatus::Executed)
        ->and(app(DebtLedger::class)->balance($purchase->counterparty, DebtSide::Supplier))->toBe('12000.00');
});

test('a return cannot be created from another store\'s sale', function () {
    $owner = storeWithReturnables();
    ['sale' => $sale] = purchasedAndSold(storeWithReturnables());

    $this->actingAs($owner)->get(route('sales-returns.create', ['source' => $sale->id]))->assertNotFound();
    $this->actingAs($owner)->post(route('sales-returns.store'), returnInput($sale, 0, 1))
        ->assertSessionHasErrors('source_document_id');
});
