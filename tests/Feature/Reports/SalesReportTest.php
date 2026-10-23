<?php

use App\Actions\Documents\ExecuteDocument;
use App\Actions\Reports\SalesReport;
use App\Enums\DocumentKind;
use App\Models\Counterparty;
use App\Models\Document;
use Illuminate\Support\Carbon;

require_once __DIR__.'/helpers.php';

/**
 * Domain tests on the sales report query module (spec: Q39), fed by executing real documents.
 */
test('totals, products, employees and paid vs debt add up with costs for the owner', function () {
    [$owner, $employee, $location, $cola, $water] = reportStore();
    $customer = Counterparty::factory()->for($owner->team)->create();

    // 12 cola x 8000 = 96 000 (cost 60 000), 10 suv x 2000 = 20 000 (cost 10 000); 16 000 on debt
    executeSaleBy($employee, $location, [[$cola, 12, '8000'], [$water, 10, '2000']], ['cash' => '100000'], $customer);
    // 6 cola x 8000 = 48 000 (cost 30 000), fully paid
    executeSaleBy($owner, $location, [[$cola, 6, '8000']], ['card' => '48000']);

    $report = app(SalesReport::class)->handle($owner->team, today()->toDateString(), today()->toDateString(), true);

    expect($report['totals'])->toBe(['revenue' => '164000.00', 'sales_count' => 2, 'cost' => '100000.00', 'gross_profit' => '64000.00']);

    $cola = collect($report['by_product'])->firstWhere('product_name', 'Cola');
    expect($cola)->toMatchArray(['quantity' => 18, 'pieces_per_block' => 6, 'revenue' => '144000.00', 'profit' => '54000.00']);

    $byEmployee = collect($report['by_employee'])->keyBy('name');
    expect($byEmployee[$employee->name])->toMatchArray(['sales_count' => 1, 'revenue' => '116000.00', 'profit' => '46000.00'])
        ->and($byEmployee[$owner->name])->toMatchArray(['sales_count' => 1, 'revenue' => '48000.00']);

    $debtSale = collect($report['sales'])->firstWhere('total', '116000.00');
    expect($debtSale)->toMatchArray(['paid' => '100000.00', 'debt' => '16000.00']);
});

test('executed returns reduce revenue, pieces and profit', function () {
    [$owner, $employee, $location, $cola] = reportStore();
    $sale = executeSaleBy($employee, $location, [[$cola, 12, '8000']], ['cash' => '96000']);

    test()->actingAs($owner);
    $return = Document::factory()->by($owner)->kind(DocumentKind::SalesReturn)->create([
        'source_document_id' => $sale->id, 'location_id' => $location->id, 'refund_method' => 'cash',
    ]);
    $sourceLine = $sale->lines()->first();
    $return->lines()->create(['product_id' => $cola->id, 'source_line_id' => $sourceLine->id, 'quantity' => 2, 'pieces_per_block' => 6, 'price' => '8000']);
    app(ExecuteDocument::class)->handle($return, $owner);

    $report = app(SalesReport::class)->handle($owner->team, today()->toDateString(), today()->toDateString(), true);

    expect($report['totals'])->toBe(['revenue' => '80000.00', 'sales_count' => 1, 'cost' => '50000.00', 'gross_profit' => '30000.00'])
        ->and($report['by_product'][0]['quantity'])->toBe(10)
        ->and($report['by_employee'][0]['name'])->toBe($employee->name)
        ->and($report['by_employee'][0]['revenue'])->toBe('80000.00');
});

test('only sales executed inside the date range count', function () {
    [$owner, $employee, $location, $cola] = reportStore();
    $old = executeSaleBy($employee, $location, [[$cola, 1, '8000']], ['cash' => '8000']);
    $old->update(['executed_at' => Carbon::parse('2026-01-10 12:00')]);
    Document::factory()->by($employee)->kind(DocumentKind::Sale)->create(['location_id' => $location->id]);

    $january = app(SalesReport::class)->handle($owner->team, '2026-01-01', '2026-01-31', true);
    $today = app(SalesReport::class)->handle($owner->team, today()->toDateString(), today()->toDateString(), true);

    expect($january['totals']['sales_count'])->toBe(1)
        ->and($today['totals']['sales_count'])->toBe(0)
        ->and($today['sales'])->toBe([]);
});

test('cost and profit are left out when not asked for', function () {
    [$owner, $employee, $location, $cola] = reportStore();
    executeSaleBy($employee, $location, [[$cola, 6, '8000']], ['cash' => '48000']);

    $report = app(SalesReport::class)->handle($owner->team, today()->toDateString(), today()->toDateString(), false);

    expect($report['totals'])->toBe(['revenue' => '48000.00', 'sales_count' => 1])
        ->and($report['by_product'][0])->not->toHaveKeys(['profit', 'cost'])
        ->and($report['by_employee'][0])->not->toHaveKeys(['profit', 'cost']);
});
