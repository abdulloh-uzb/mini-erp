<?php

use App\Actions\Documents\ExecuteDocument;
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
 * The sale screen over HTTP (spec: Seam 1): employees park and execute sales; costs stay
 * with users who may view them. The helper gives a settled store whose location holds
 * 2 blocks of a 12-piece product bought at 4 000 a piece.
 *
 * @return array{0: User, 1: Product, 2: Location}
 */
function storeWithStock(): array
{
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);
    $location = Location::factory()->for($owner->team)->create();
    $product = Product::factory()->for($owner->team)->create(['pieces_per_block' => 12]);

    $purchase = Document::factory()->by($owner)->kind(DocumentKind::Purchase)->create([
        'location_id' => $location->id,
        'counterparty_id' => Counterparty::factory()->for($owner->team)->create()->id,
    ]);
    DocumentLine::factory()->for($purchase)->create(['product_id' => $product->id, 'quantity' => 24, 'price' => '4000']);
    test()->actingAs($owner);
    app(ExecuteDocument::class)->handle($purchase, $owner);

    return [$owner, $product, $location];
}

/**
 * @param  array<int, array<string, mixed>>  $lines
 * @param  array<string, string|null>  $payments
 * @return array<string, mixed>
 */
function saleInput(Location $location, array $lines, array $payments = [], ?Counterparty $customer = null, bool $execute = false): array
{
    return [
        'counterparty_id' => $customer?->id,
        'location_id' => $location->id,
        'lines' => $lines,
        'payments' => $payments,
        'execute' => $execute,
    ];
}

test('an employee executes a sale and gets a fresh screen with its receipt', function () {
    [$owner, $cola, $location] = storeWithStock();
    $employee = User::factory()->employeeOf($owner->team)->create();

    $response = $this->actingAs($employee)->post(route('sales.store'), saleInput($location, [
        ['product_id' => $cola->id, 'blocks' => 1, 'pieces' => 2, 'price' => '6000'],
    ], ['cash' => '84000'], execute: true));

    $sale = Document::query()->where('kind', DocumentKind::Sale)->sole();
    $response->assertRedirect(route('sales.create'))
        ->assertInertiaFlash('toast.message', "Sotuv №{$sale->number} bajarildi.")
        ->assertInertiaFlash('receipt.number', $sale->number);
    expect($sale)
        ->status->toBe(DocumentStatus::Executed)
        ->executor_id->toBe($employee->id)
        ->and(StockBalance::query()->where('product_id', $cola->id)->value('quantity'))->toBe(10);
    $this->get(route('sales.show', $sale))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/show')
            ->where('receipt.total', '84000.00')
            ->where('receipt.debt', '0.00'));
});

test('the screen pre-fills the last sale price and only users who may view costs get average costs', function () {
    [$owner, $cola, $location] = storeWithStock();
    $water = Product::factory()->for($owner->team)->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    foreach (['6000', '6500'] as $price) {
        $this->actingAs($owner)->post(route('sales.store'), saleInput($location, [
            ['product_id' => $cola->id, 'pieces' => 1, 'price' => $price],
        ], ['cash' => $price], execute: true));
    }
    $this->post(route('sales.store'), saleInput($location, [['product_id' => $cola->id, 'pieces' => 1, 'price' => '9999']]));

    $this->actingAs($employee)->get(route('sales.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/create')
            ->where('previous_prices', [$cola->id => '6500.00'])
            ->missing("previous_prices.{$water->id}")
            ->missing('average_costs'));
    $this->actingAs($owner)->get(route('sales.create'))
        ->assertInertia(fn (Assert $page) => $page->where('average_costs', [$cola->id => '4000.00']));
});

test('parking a sale clears the screen and lists it under the drafts, at the location last sold from', function () {
    [$owner, $cola, $location] = storeWithStock();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $shop = Location::factory()->for($owner->team)->create();
    $customer = Counterparty::factory()->for($owner->team)->create();

    $this->actingAs($employee)->post(route('sales.store'), saleInput($shop, [
        ['product_id' => $cola->id, 'blocks' => 1, 'pieces' => 0, 'price' => '5000'],
    ], customer: $customer))
        ->assertRedirect(route('sales.create'))
        ->assertInertiaFlash('toast.message', 'Qoralama saqlandi.');

    $sale = Document::query()->where('kind', DocumentKind::Sale)->sole();
    expect($sale->status)->toBe(DocumentStatus::Draft);
    $this->get(route('sales.create'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('default_location_id', $shop->id)
            ->where('drafts', [['id' => $sale->id, 'number' => $sale->number, 'counterparty' => $customer->name, 'total' => '60000.00']]));
    $this->get(route('sales.show', $sale))
        ->assertInertia(fn (Assert $page) => $page
            ->component('sales/create')
            ->where('document.id', $sale->id)
            ->where('drafts', []));
});

test('a refused execution keeps the sale parked and reopens it with the errors', function () {
    [$owner, $cola, $location] = storeWithStock();
    $employee = User::factory()->employeeOf($owner->team)->create();

    $response = $this->actingAs($employee)->post(route('sales.store'), saleInput($location, [
        ['product_id' => $cola->id, 'pieces' => 1, 'price' => '6000'],
    ], ['cash' => '1000'], execute: true));

    $sale = Document::query()->where('kind', DocumentKind::Sale)->sole();
    $response->assertRedirect(route('sales.show', $sale))
        ->assertSessionHasErrors(['counterparty_id' => 'Qarzga sotish uchun mijozni tanlang.']);
    expect($sale->status)->toBe(DocumentStatus::Draft);
});
