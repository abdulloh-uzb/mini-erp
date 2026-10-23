<?php

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\DebtLedger;
use App\Enums\CashOperationType;
use App\Enums\DebtEntryType;
use App\Enums\DebtSide;
use App\Enums\PaymentMethod;
use App\Models\CashOperation;
use App\Models\Counterparty;
use App\Models\DebtEntry;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Domain tests on the debt ledger (spec: Seam 2), called directly without HTTP.
 */
function supplierOwed(User $owner, string $debt): Counterparty
{
    $supplier = Counterparty::factory()->for($owner->team)->create();
    app(DebtLedger::class)->add($owner, $supplier->id, DebtSide::Supplier, DebtEntryType::OpeningBalance, $debt);

    return $supplier;
}

test('paying a supplier records the cash-out and lowers the debt together', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Card, '500000');
    $supplier = supplierOwed($owner, '300000');

    $operation = app(DebtLedger::class)->pay($owner, $supplier, DebtSide::Supplier, '120000', PaymentMethod::Card, 'Faktura 17');

    expect($operation)
        ->type->toBe(CashOperationType::SupplierPayment)
        ->counterparty_id->toBe($supplier->id)
        ->amount->toBe('120000.00')
        ->note->toBe('Faktura 17')
        ->and(DebtEntry::query()->latest('id')->first())
        ->type->toBe(DebtEntryType::Payment)
        ->amount->toBe('-120000.00')
        ->cash_operation_id->toBe($operation->id)
        ->and(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('180000.00')
        ->and(app(CashRegister::class)->balances($owner->team)['card'])->toBe('380000.00');
});

test('a payment above the debt is refused', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '500000');
    $supplier = supplierOwed($owner, '70000');

    expect(fn () => app(DebtLedger::class)->pay($owner, $supplier, DebtSide::Supplier, '70000.01', PaymentMethod::Cash))
        ->toThrow(ValidationException::class, "Summa qarzdan oshmasligi kerak (qarz: 70 000 so'm)");
    expect(CashOperation::query()->where('type', CashOperationType::SupplierPayment)->count())->toBe(0)
        ->and(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('70000.00');
});

test('a payment above the method\'s register balance is refused and leaves the debt', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '10000');
    $supplier = supplierOwed($owner, '70000');

    expect(fn () => app(DebtLedger::class)->pay($owner, $supplier, DebtSide::Supplier, '20000', PaymentMethod::Cash))
        ->toThrow(ValidationException::class, 'Kassada naqd yetarli emas');
    expect(DebtEntry::query()->count())->toBe(1)
        ->and(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('70000.00');
});

function customerOwing(User $owner, string ...$debts): Counterparty
{
    $customer = Counterparty::factory()->for($owner->team)->create();

    foreach ($debts as $debt) {
        app(DebtLedger::class)->add($owner, $customer->id, DebtSide::Customer, DebtEntryType::Sale, $debt);
    }

    return $customer;
}

test('a customer payment records the cash-in and lowers the debt together', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $customer = customerOwing($owner, '300000');

    $operation = app(DebtLedger::class)->pay($owner, $customer, DebtSide::Customer, '100000', PaymentMethod::Card);

    expect($operation)
        ->type->toBe(CashOperationType::CustomerDebtPayment)
        ->counterparty_id->toBe($customer->id)
        ->and(app(DebtLedger::class)->balance($customer, DebtSide::Customer))->toBe('200000.00')
        ->and(app(CashRegister::class)->balances($owner->team)['card'])->toBe('100000.00');
});

test('a customer payment above the debt is refused', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $customer = customerOwing($owner, '70000');

    expect(fn () => app(DebtLedger::class)->pay($owner, $customer, DebtSide::Customer, '70000.01', PaymentMethod::Cash))
        ->toThrow(ValidationException::class, "Summa qarzdan oshmasligi kerak (qarz: 70 000 so'm)");
});

test('payments pay off the oldest debt first', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $customer = customerOwing($owner, '100000', '50000', '70000');
    app(DebtLedger::class)->pay($owner, $customer, DebtSide::Customer, '120000', PaymentMethod::Cash);

    $allocation = app(DebtLedger::class)->allocation($customer, DebtSide::Customer);

    expect(array_column($allocation, 'amount'))->toBe(['100000.00', '50000.00', '70000.00'])
        ->and(array_column($allocation, 'paid'))->toBe(['100000.00', '20000.00', '0.00'])
        ->and(array_column($allocation, 'debt'))->toBe(['0.00', '30000.00', '70000.00']);
});

test('allocation counts write-offs and ignores debts with nothing owed', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $customer = customerOwing($owner, '100000', '50000');
    app(DebtLedger::class)->writeOff($owner, $customer, '120000', 'Yopilgan do\'kon');

    $allocation = app(DebtLedger::class)->allocation($customer, DebtSide::Customer);

    expect(array_column($allocation, 'debt'))->toBe(['0.00', '30000.00'])
        ->and(app(DebtLedger::class)->balance($customer, DebtSide::Customer))->toBe('30000.00');
});

test('a write-off lowers the debt under Umidsiz qarz and moves no money', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $customer = customerOwing($owner, '300000');

    $entry = app(DebtLedger::class)->writeOff($owner, $customer, '100000', 'Topib bo\'lmadi');

    expect($entry)
        ->type->toBe(DebtEntryType::WriteOff)
        ->amount->toBe('-100000.00')
        ->note->toBe('Topib bo\'lmadi')
        ->expenseCategory->code->toBe('bad-debt')
        ->and(app(DebtLedger::class)->balance($customer, DebtSide::Customer))->toBe('200000.00')
        ->and(CashOperation::query()->count())->toBe(0);
});

test('a write-off above the debt is refused', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $customer = customerOwing($owner, '70000');

    expect(fn () => app(DebtLedger::class)->writeOff($owner, $customer, '70000.01'))
        ->toThrow(ValidationException::class, "Summa qarzdan oshmasligi kerak (qarz: 70 000 so'm)");
    expect(DebtEntry::query()->count())->toBe(1);
});
