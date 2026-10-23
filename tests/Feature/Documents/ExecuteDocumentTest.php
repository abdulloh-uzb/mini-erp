<?php

use App\Actions\Documents\ExecuteDocument;
use App\Enums\DocumentKind;
use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\ProductCost;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * Domain tests on the document execution module (spec: Seam 2), called directly without HTTP.
 */
function openingBalanceLine(Document $document, Location $location, Product $product, int $quantity, ?string $price): DocumentLine
{
    return DocumentLine::factory()->for($document)->create([
        'location_id' => $location->id,
        'product_id' => $product->id,
        'quantity' => $quantity,
        'pieces_per_block' => $product->pieces_per_block,
        'price' => $price,
    ]);
}

test('executing the opening balance puts its stock in place and stamps the executor', function () {
    $owner = User::factory()->create();
    $warehouse = Location::factory()->for($owner->team)->create();
    $shelf = Location::factory()->inside($warehouse)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $document = Document::factory()->by($owner)->create();
    openingBalanceLine($document, $warehouse, $cola, 24, '9000');
    openingBalanceLine($document, $shelf, $cola, 5, '9000');
    $this->actingAs($owner);
    $this->freezeSecond();

    app(ExecuteDocument::class)->handle($document, $owner);

    expect($document->fresh())
        ->status->toBe(DocumentStatus::Executed)
        ->executor_id->toBe($owner->id)
        ->executed_at->toEqual(now())
        ->and(StockBalance::query()->where('product_id', $cola->id)->pluck('quantity', 'location_id')->all())
        ->toEqual([$warehouse->id => 24, $shelf->id => 5]);
});

test('opening stock sets each product\'s first average cost, blended across locations', function () {
    $owner = User::factory()->create();
    $warehouse = Location::factory()->for($owner->team)->create();
    $shelf = Location::factory()->inside($warehouse)->create();
    $cola = Product::factory()->for($owner->team)->create();
    $water = Product::factory()->for($owner->team)->create();
    $document = Document::factory()->by($owner)->create();
    openingBalanceLine($document, $warehouse, $cola, 3, '1000');
    openingBalanceLine($document, $shelf, $cola, 4, '2000');
    openingBalanceLine($document, $warehouse, $water, 12, '2500.50');
    $this->actingAs($owner);

    app(ExecuteDocument::class)->handle($document, $owner);

    expect(ProductCost::query()->pluck('average_cost', 'product_id')->all())->toBe([
        $cola->id => '1571.428571',
        $water->id => '2500.500000',
    ]);
});

test('an executed document cannot be executed again', function () {
    $owner = User::factory()->create();
    $location = Location::factory()->for($owner->team)->create();
    $document = Document::factory()->by($owner)->create();
    $line = openingBalanceLine($document, $location, Product::factory()->for($owner->team)->create(), 10, '1000');
    $this->actingAs($owner);
    app(ExecuteDocument::class)->handle($document, $owner);

    expect(fn () => app(ExecuteDocument::class)->handle($document->fresh(), $owner))
        ->toThrow(ValidationException::class, 'Hujjat allaqachon bajarilgan.');
    expect(StockBalance::query()->where('product_id', $line->product_id)->value('quantity'))->toBe(10)
        ->and(StockMovement::query()->count())->toBe(1);
});

test('a user without the execute permission for the kind is refused', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $location = Location::factory()->for($owner->team)->create();
    $document = Document::factory()->by($owner)->create();
    openingBalanceLine($document, $location, Product::factory()->for($owner->team)->create(), 10, '1000');
    $this->actingAs($employee);

    expect(fn () => app(ExecuteDocument::class)->handle($document, $employee))->toThrow(AuthorizationException::class);
    expect($document->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(StockBalance::query()->count())->toBe(0);
});

test('no other document executes before the opening balance is settled', function () {
    $owner = User::factory()->create();
    $transfer = Document::factory()->by($owner)->kind(DocumentKind::Transfer)->create();
    $this->actingAs($owner);

    expect(fn () => app(ExecuteDocument::class)->handle($transfer, $owner))
        ->toThrow(ValidationException::class, "Avval boshlang'ich qoldiqni kiriting yoki «Boshlang'ich qoldiq yo'q» ni tanlang.");
    expect($transfer->fresh()->status)->toBe(DocumentStatus::Draft);
});

test('the opening balance cannot be executed once the owner said there is none', function () {
    $owner = User::factory()->create();
    $owner->team->update(['opening_balance_skipped_at' => now()]);
    $location = Location::factory()->for($owner->team)->create();
    $document = Document::factory()->by($owner)->create();
    openingBalanceLine($document, $location, Product::factory()->for($owner->team)->create(), 10, '1000');
    $this->actingAs($owner);

    expect(fn () => app(ExecuteDocument::class)->handle($document, $owner))
        ->toThrow(ValidationException::class, "Boshlang'ich qoldiq yo'q deb belgilangan.");
    expect(StockBalance::query()->count())->toBe(0);
});

test('a failing line leaves no stock, cost or status change and names the line', function () {
    $owner = User::factory()->create();
    $location = Location::factory()->for($owner->team)->create();
    $document = Document::factory()->by($owner)->create();
    openingBalanceLine($document, $location, Product::factory()->for($owner->team)->create(), 10, '1000');
    openingBalanceLine($document, $location, Product::factory()->for($owner->team)->create(), 5, null);
    $this->actingAs($owner);

    try {
        app(ExecuteDocument::class)->handle($document, $owner);
        $this->fail('Execution should have been refused.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toBe(['lines.1.price' => ['Tannarxni kiriting.']]);
    }

    expect($document->fresh()->status)->toBe(DocumentStatus::Draft)
        ->and(StockBalance::query()->count())->toBe(0)
        ->and(StockMovement::query()->count())->toBe(0)
        ->and(ProductCost::query()->count())->toBe(0);
});

test('a second opening balance cannot be executed once one has been', function () {
    $owner = User::factory()->create();
    $location = Location::factory()->for($owner->team)->create();
    $product = Product::factory()->for($owner->team)->create();
    Document::factory()->by($owner)->executed()->create();
    $second = Document::factory()->by($owner)->create();
    openingBalanceLine($second, $location, $product, 10, '1000');
    $this->actingAs($owner);

    expect(fn () => app(ExecuteDocument::class)->handle($second, $owner))
        ->toThrow(ValidationException::class, "Boshlang'ich qoldiq allaqachon bajarilgan.");
    expect(StockBalance::query()->count())->toBe(0);
});
