<?php

use App\Actions\Cash\CashRegister;
use App\Enums\CashOperationType;
use App\Enums\DocumentKind;
use App\Enums\PaymentMethod;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\Loan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/Reports/helpers.php';

test('the owner home shows pending drafts of owner-executed kinds with their authors', function () {
    [$owner, $employee, $location] = reportStore();
    Document::factory()->by($employee)->kind(DocumentKind::Purchase)->create(['location_id' => $location->id]);
    Document::factory()->by($employee)->kind(DocumentKind::InventoryCount)->create(['location_id' => $location->id]);
    Document::factory()->by($employee)->kind(DocumentKind::InventoryCount)->executed()->create(['location_id' => $location->id]);
    Document::factory()->by($employee)->kind(DocumentKind::Transfer)->create(['location_id' => $location->id]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('owner.pendingDrafts', 2)
            ->where('owner.pendingDrafts.0.kind', 'purchase')
            ->where('owner.pendingDrafts.0.count', 1)
            ->where('owner.pendingDrafts.0.drafts.0.author', $employee->name)
            ->where('owner.pendingDrafts.0.href', route('purchases.index', ['status' => 'draft'], false))
            ->where('owner.pendingDrafts.1.kind', 'inventory_count')
            ->where('owner.pendingDrafts.1.count', 1)
            ->missing('employee'),
        );
});

test('the owner home shows the period figures, cash per method and debt totals', function () {
    [$owner, $employee, $location, $cola] = reportStore();
    $customer = Counterparty::factory()->for($owner->team)->create();
    $this->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::Income, PaymentMethod::BankTransfer, '1000');
    executeSaleBy($employee, $location, [[$cola, 6, '8000']], ['cash' => '40000'], $customer);
    Loan::factory()->for($owner->team)->create(['principal' => '500000', 'direction' => 'given']);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters', ['period' => 'day', 'from' => today()->toDateString(), 'to' => today()->toDateString()])
            ->where('owner.period', [
                'gross_sales' => '48000.00',
                'gross_profit' => '18000.00',
                'cash_in' => '41000.00',
                'expenses' => '0.00',
                'debit' => '8000.00',
                'credit' => '0.00',
                'pieces_sold' => 6,
            ])
            ->where('owner.cashBalances', ['cash' => '40000.00', 'card' => '0.00', 'bank_transfer' => '1000.00'])
            ->where('owner.debts.customer', '8000.00')
            ->where('owner.debts.supplier', '0.00')
            ->where('owner.debts.loans_given', '500000.00')
            ->where('owner.debts.loans_received', '0.00'),
        );
});

test('the owner home period filter picks the days its figures cover', function () {
    [$owner, $employee, $location, $cola] = reportStore();
    $customer = Counterparty::factory()->for($owner->team)->create();
    $this->travelTo('2026-03-10 12:00');
    executeSaleBy($employee, $location, [[$cola, 6, '8000']], ['cash' => '40000'], $customer);
    $this->travelTo('2026-03-20 12:00');

    $figures = fn (array $query) => $this->actingAs($owner)->get(route('dashboard', $query))->inertiaProps();

    expect($figures(['period' => 'day']))
        ->toHaveKey('owner.period.gross_sales', '0.00')
        ->toHaveKey('owner.period.debit', '8000.00')
        ->and($figures(['period' => 'week']))
        ->toHaveKey('filters', ['period' => 'week', 'from' => '2026-03-16', 'to' => '2026-03-20'])
        ->toHaveKey('owner.period.pieces_sold', 0)
        ->and($figures(['period' => 'month']))
        ->toHaveKey('owner.period.gross_sales', '48000.00')
        ->toHaveKey('owner.period.pieces_sold', 6)
        ->and($figures(['period' => 'custom', 'from' => '2026-03-01', 'to' => '2026-03-09']))
        ->toHaveKey('owner.period.gross_sales', '0.00')
        ->toHaveKey('owner.period.debit', '0.00');
});

test('a custom period needs both of its days in order', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->from(route('dashboard'))
        ->get(route('dashboard', ['period' => 'custom', 'from' => '2026-03-09', 'to' => '2026-03-01']))
        ->assertSessionHasErrors('to');
    $this->actingAs($owner)->from(route('dashboard'))
        ->get(route('dashboard', ['period' => 'custom']))
        ->assertSessionHasErrors(['from', 'to']);
});

test('the employee home shows only their own drafts and sales today, without costs', function () {
    [$owner, $employee, $location, $cola] = reportStore();
    $other = User::factory()->employeeOf($owner->team)->create();
    $mine = Document::factory()->by($employee)->kind(DocumentKind::Sale)->create(['location_id' => $location->id]);
    Document::factory()->by($other)->kind(DocumentKind::Sale)->create(['location_id' => $location->id]);
    executeSaleBy($employee, $location, [[$cola, 6, '8000']], ['cash' => '48000']);
    executeSaleBy($other, $location, [[$cola, 1, '8000']], ['cash' => '8000']);

    $this->actingAs($employee)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('employee.myDrafts', 1)
            ->where('employee.myDrafts.0.href', route('sales.show', $mine, false))
            ->where('employee.mySalesToday', ['sales_count' => 1, 'revenue' => '48000.00'])
            ->missing('owner'),
        );
});

test('the sidebar draft counts reach only users who execute those kinds', function () {
    [$owner, $employee, $location] = reportStore();
    Document::factory()->by($employee)->kind(DocumentKind::Purchase)->count(2)->create(['location_id' => $location->id]);
    Document::factory()->by($employee)->kind(DocumentKind::SalesReturn)->create(['location_id' => $location->id]);

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('draftCounts', ['purchase' => 2, 'sales_return' => 1, 'inventory_count' => 0]));
    $this->actingAs($employee)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('draftCounts', []));
});

test('the sales report sends costs and profit to the owner only', function () {
    [$owner, $employee, $location, $cola] = reportStore();
    executeSaleBy($employee, $location, [[$cola, 6, '8000']], ['cash' => '48000']);

    $this->actingAs($owner)->get(route('reports.sales'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/sales')
            ->where('report.totals.gross_profit', '18000.00')
            ->where('report.by_product.0.profit', '18000.00')
            ->where('filters', ['from' => today()->toDateString(), 'to' => today()->toDateString()]));

    $this->actingAs($employee)->get(route('reports.sales'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('report.totals', ['revenue' => '48000.00', 'sales_count' => 1])
            ->missing('report.by_product.0.profit')
            ->missing('report.by_employee.0.profit'));
});

test('the sales report takes a date range and rejects a reversed one', function () {
    [$owner] = reportStore();

    $this->actingAs($owner)->get(route('reports.sales', ['from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertInertia(fn (Assert $page) => $page->where('filters', ['from' => '2026-01-01', 'to' => '2026-01-31']));
    $this->actingAs($owner)->get(route('reports.sales', ['from' => '2026-02-01', 'to' => '2026-01-01']))
        ->assertSessionHasErrors('to');
});
