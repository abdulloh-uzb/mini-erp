<?php

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\DebtLedger;
use App\Actions\Cash\LoanLedger;
use App\Actions\Documents\ExecuteDocument;
use App\Actions\Reports\BalanceSheet;
use App\Actions\Reports\CashFlow;
use App\Actions\Reports\ProfitAndLoss;
use App\Enums\CashOperationType;
use App\Enums\DebtSide;
use App\Enums\DocumentKind;
use App\Enums\LoanDirection;
use App\Enums\PaymentMethod;
use App\Models\CashOperation;
use App\Models\Counterparty;
use App\Models\Document;
use App\Models\ExpenseCategory;
use App\Models\Location;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

require_once __DIR__.'/helpers.php';

function financeCategory(string $code): ExpenseCategory
{
    return ExpenseCategory::query()->where('code', $code)->sole();
}

/**
 * Run the scenario: opening balance, a purchase, two sales (paid and on debt), a return, a
 * shortage and a surplus, expenses (counted, owner withdrawal, reversed), loans with interest,
 * debt payments both ways and a write-off.
 *
 * @return array{0: User, 1: Counterparty, 2: Counterparty}
 */
function financeScenario(): array
{
    $owner = User::factory()->create();
    $location = Location::factory()->for($owner->team)->create();
    $cola = Product::factory()->for($owner->team)->create(['pieces_per_block' => 1]);
    $customer = Counterparty::factory()->for($owner->team)->create();
    $supplier = Counterparty::factory()->for($owner->team)->create();
    $bank = Counterparty::factory()->for($owner->team)->create();

    // Opening: 100 cola at 5 000, 1 000 000 cash, the customer already owes 50 000
    test()->actingAs($owner)->post(route('opening-balance.execute'), [
        'note' => null,
        'lines' => [['location_id' => $location->id, 'product_id' => $cola->id, 'blocks' => 100, 'pieces' => 0, 'price' => '5000']],
        'payments' => ['cash' => '1000000'],
        'loans' => [],
        'supplier_debts' => [],
        'customer_debts' => [['counterparty_id' => $customer->id, 'amount' => '50000']],
    ])->assertSessionHasNoErrors();

    // Purchase 100 at 7 000, 300 000 paid: average cost 6 000, supplier owed 400 000
    test()->post(route('purchases.store'), [
        'counterparty_id' => $supplier->id, 'location_id' => $location->id, 'note' => null,
        'lines' => [['product_id' => $cola->id, 'blocks' => 100, 'price' => '7000']],
        'payments' => ['cash' => '300000'], 'execute' => true,
    ])->assertSessionHasNoErrors();

    $debts = app(DebtLedger::class);
    $debts->pay($owner, $supplier, DebtSide::Supplier, '100000', PaymentMethod::Cash);

    // Sales: 50 x 10 000 paid; 20 x 10 000 with 50 000 on debt. Cost 6 000 each
    $paidSale = executeSaleBy($owner, $location, [[$cola, 50, '10000']], ['cash' => '500000']);
    executeSaleBy($owner, $location, [[$cola, 20, '10000']], ['cash' => '150000'], $customer);

    // Return 5 of the paid sale: 50 000 back in cash, 30 000 cost back
    $return = Document::factory()->by($owner)->kind(DocumentKind::SalesReturn)->create([
        'source_document_id' => $paidSale->id, 'location_id' => $location->id, 'refund_method' => 'cash',
    ]);
    $return->lines()->create(['product_id' => $cola->id, 'source_line_id' => $paidSale->lines()->value('id'), 'quantity' => 5, 'pieces_per_block' => 1, 'price' => '10000']);
    app(ExecuteDocument::class)->handle($return, $owner);

    $debts->pay($owner, $customer, DebtSide::Customer, '30000', PaymentMethod::Cash);

    // 135 expected: count 133 (shortage 12 000), then 134 (surplus 6 000)
    foreach ([133, 134] as $pieces) {
        test()->post(route('inventory-counts.store'), [
            'location_id' => $location->id, 'note' => null, 'execute' => true,
            'lines' => [['product_id' => $cola->id, 'pieces' => $pieces]],
        ])->assertSessionHasNoErrors();
    }

    $register = app(CashRegister::class);
    $register->record($owner, CashOperationType::Expense, PaymentMethod::Cash, '200000', expenseCategoryId: financeCategory('rent')->id);
    $register->record($owner, CashOperationType::Expense, PaymentMethod::Cash, '100000', expenseCategoryId: financeCategory('owner-withdrawal')->id);
    $register->reverse(
        $register->record($owner, CashOperationType::Expense, PaymentMethod::Cash, '30000', expenseCategoryId: financeCategory('transport')->id),
        $owner,
    );

    $loans = app(LoanLedger::class);
    $received = $loans->open($owner, LoanDirection::Received, $bank->id, '1000000', PaymentMethod::Card);
    $loans->recordInterest($received, $owner, '40000', PaymentMethod::Card);
    $given = $loans->open($owner, LoanDirection::Given, $bank->id, '200000', PaymentMethod::Cash);
    $loans->repay($given, $owner, '50000', PaymentMethod::Cash);
    $loans->recordInterest($given, $owner, '10000', PaymentMethod::Cash);

    $debts->writeOff($owner, $customer, '20000');

    return [$owner, $customer, $supplier];
}

/**
 * Domain tests on the finance reports (spec: 108–111, Q25, Q48a, ADR-0001), fed by one
 * scenario of real documents and ledger calls, all today.
 */
test('net profit follows every line of the formula and leaves out money movements and the opening balance', function () {
    [$owner] = financeScenario();
    $today = today()->toDateString();

    $report = app(ProfitAndLoss::class)->handle($owner->team, $today, $today);

    expect($report)->toBe([
        'revenue' => '650000.00',
        'cost_of_goods_sold' => '390000.00',
        'gross_profit' => '260000.00',
        'expenses' => [
            ['name' => 'Ijara', 'amount' => '200000.00'],
            ['name' => 'Umidsiz qarz', 'amount' => '20000.00'],
        ],
        'expenses_total' => '220000.00',
        'shortages' => '12000.00',
        'surpluses' => '6000.00',
        'interest' => ['received' => '10000.00', 'paid' => '40000.00', 'net' => '-30000.00'],
        'net_profit' => '4000.00',
    ]);
});

test('cash flow totals every operation in and out per type, category and day, loans and debt payments included', function () {
    [$owner] = financeScenario();
    $today = today()->toDateString();

    $report = app(CashFlow::class)->handle($owner->team, $today, $today);

    expect($report['totals'])->toBe(['in' => '2770000.00', 'out' => '1020000.00', 'net' => '1750000.00'])
        ->and($report['by_day'])->toBe([['date' => $today, 'in' => '2770000.00', 'out' => '1020000.00', 'net' => '1750000.00']]);

    $byType = collect($report['by_type'])->keyBy('type');
    expect($byType['expense'])->toMatchArray(['in' => '30000.00', 'out' => '370000.00'])
        ->and($byType['loan_received'])->toMatchArray(['in' => '1000000.00', 'out' => '0.00'])
        ->and($byType['loan_given'])->toMatchArray(['in' => '0.00', 'out' => '200000.00'])
        ->and($byType['customer_debt_payment'])->toMatchArray(['in' => '30000.00'])
        ->and($byType['supplier_payment'])->toMatchArray(['out' => '100000.00'])
        ->and($byType['sale_payment'])->toMatchArray(['in' => '650000.00'])
        ->and($byType['opening_balance'])->toMatchArray(['in' => '1000000.00']);

    expect(collect($report['by_category'])->mapWithKeys(fn (array $row) => [$row['name'] => [$row['in'], $row['out']]])->all())->toBe([
        'Transport xarajati' => ['30000.00', '30000.00'],
        'Ijara' => ['0.00', '200000.00'],
        'Qarz foizi' => ['0.00', '40000.00'],
        'Egasi olgan pul' => ['0.00', '100000.00'],
        'Olingan foiz' => ['10000.00', '0.00'],
    ]);
});

test('the balance shows cash per method, loans outstanding and debt totals now', function () {
    [$owner] = financeScenario();

    expect(app(BalanceSheet::class)->handle($owner->team))->toBe([
        'cash' => ['cash' => '790000.00', 'card' => '960000.00', 'bank_transfer' => '0.00'],
        'cash_total' => '1750000.00',
        'debts' => [
            'customer' => '50000.00',
            'supplier' => '300000.00',
            'loans_given' => '150000.00',
            'loans_received' => '1000000.00',
        ],
    ]);
});

test('another store\'s money and documents never reach the reports', function () {
    financeScenario();
    $owner = User::factory()->create();
    $today = today()->toDateString();

    expect(app(ProfitAndLoss::class)->handle($owner->team, $today, $today)['net_profit'])->toBe('0.00')
        ->and(app(CashFlow::class)->handle($owner->team, $today, $today)['by_day'])->toBe([])
        ->and(app(BalanceSheet::class)->handle($owner->team)['debts'])->toBe(['customer' => '0.00', 'supplier' => '0.00', 'loans_given' => '0.00', 'loans_received' => '0.00']);
});

test('only what happened inside the date range counts', function () {
    [$owner] = reportStore();
    test()->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '100000');
    $rent = app(CashRegister::class)->record($owner, CashOperationType::Expense, PaymentMethod::Cash, '50000', expenseCategoryId: financeCategory('rent')->id);
    CashOperation::query()->whereKey($rent->id)->update(['created_at' => Carbon::parse('2026-01-10 12:00')]);

    $january = app(ProfitAndLoss::class)->handle($owner->team, '2026-01-01', '2026-01-31');
    $today = today()->toDateString();

    expect($january['expenses'])->toBe([['name' => 'Ijara', 'amount' => '50000.00']])
        ->and($january['net_profit'])->toBe('-50000.00')
        ->and(app(ProfitAndLoss::class)->handle($owner->team, $today, $today)['expenses'])->toBe([])
        ->and(app(CashFlow::class)->handle($owner->team, '2026-01-01', '2026-01-31')['totals'])->toBe(['in' => '0.00', 'out' => '50000.00', 'net' => '-50000.00']);
});

test('the owner opens the three pages, the month so far by default', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->get(route('reports.profit-and-loss'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('reports/profit-and-loss')
            ->where('filters', ['from' => today()->startOfMonth()->toDateString(), 'to' => today()->toDateString()])
            ->where('report.net_profit', '0.00')
            ->where('position', ['cash_total' => '0.00', 'loans_given' => '0.00', 'loans_received' => '0.00']),
        );
    $this->get(route('reports.cash-flow', ['from' => '2026-01-01', 'to' => '2026-01-31']))
        ->assertInertia(fn (Assert $page) => $page->component('reports/cash-flow')->where('filters.from', '2026-01-01'));
    $this->get(route('reports.balance'))
        ->assertInertia(fn (Assert $page) => $page->component('reports/balance')->where('report.cash_total', '0.00'));
});

test('employees cannot open the finance reports', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();

    foreach (['reports.profit-and-loss', 'reports.cash-flow', 'reports.balance'] as $route) {
        $this->actingAs($employee)->get(route($route))->assertForbidden();
    }
});
