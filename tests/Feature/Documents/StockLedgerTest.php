<?php

use App\Actions\Documents\InsufficientStock;
use App\Actions\Documents\StockLedger;
use App\Enums\DocumentKind;
use App\Models\Document;
use App\Models\DocumentLine;
use App\Models\Location;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;

/**
 * The ledger's own invariant, ahead of the outgoing document kinds (sale, transfer) that
 * will reach it through the execution module.
 */
test('issuing more pieces than a location holds is refused and changes nothing', function () {
    $owner = User::factory()->create();
    $location = Location::factory()->for($owner->team)->create();
    $product = Product::factory()->for($owner->team)->create();
    $incoming = DocumentLine::factory()->for(Document::factory()->by($owner))->create(['product_id' => $product->id]);
    $outgoing = DocumentLine::factory()->for(Document::factory()->by($owner)->kind(DocumentKind::Sale))->create(['product_id' => $product->id]);
    $ledger = app(StockLedger::class);
    $ledger->receive($incoming, $location->id, 7);

    expect(fn () => $ledger->issue($outgoing, $location->id, 8))->toThrow(InsufficientStock::class);

    expect(StockBalance::query()->value('quantity'))->toBe(7)
        ->and(StockMovement::query()->count())->toBe(1);
});

test('issuing takes pieces out of the location and records the movement', function () {
    $owner = User::factory()->create();
    $location = Location::factory()->for($owner->team)->create();
    $product = Product::factory()->for($owner->team)->create();
    $incoming = DocumentLine::factory()->for(Document::factory()->by($owner))->create(['product_id' => $product->id]);
    $outgoing = DocumentLine::factory()->for(Document::factory()->by($owner)->kind(DocumentKind::Sale))->create(['product_id' => $product->id]);
    $ledger = app(StockLedger::class);
    $ledger->receive($incoming, $location->id, 7);

    $ledger->issue($outgoing, $location->id, 7);

    expect(StockBalance::query()->value('quantity'))->toBe(0)
        ->and(StockMovement::query()->where('document_line_id', $outgoing->id)->value('quantity'))->toBe(-7);
});
