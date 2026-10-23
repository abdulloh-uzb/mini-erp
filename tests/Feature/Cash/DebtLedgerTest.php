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
