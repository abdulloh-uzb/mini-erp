<?php

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\LoanLedger;
use App\Enums\CashOperationType;
use App\Enums\LoanDirection;
use App\Enums\PaymentMethod;
use App\Models\Counterparty;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Domain tests on the loan ledger (spec: Seam 2), called directly without HTTP.
 */
test('a loan given pays its principal out of the register and owes it all back', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $counterparty = Counterparty::factory()->for($owner->team)->create();
    $register = app(CashRegister::class);
    $register->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '1000000');

    $loan = app(LoanLedger::class)->open($owner, LoanDirection::Given, $counterparty->id, '400000', PaymentMethod::Cash, '2026-12-01');

    expect($loan->fresh())
        ->direction->toBe(LoanDirection::Given)
        ->principal->toBe('400000.00')
        ->counterparty_id->toBe($counterparty->id)
        ->and($loan->fresh()->balance())->toBe('400000.00')
        ->and($loan->cashOperations->sole())
        ->type->toBe(CashOperationType::LoanGiven)
        ->counterparty_id->toBe($counterparty->id)
        ->and($register->balances($owner->team)['cash'])->toBe('600000.00');
});

test('partial repayments of a loan received go out of the register and lower its balance', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $counterparty = Counterparty::factory()->for($owner->team)->create();
    $ledger = app(LoanLedger::class);
    $loan = $ledger->open($owner, LoanDirection::Received, $counterparty->id, '500000', PaymentMethod::Card);

    $ledger->repay($loan, $owner, '200000', PaymentMethod::Card);
    $ledger->repay($loan, $owner, '50000.50', PaymentMethod::Card);

    $loan = $loan->fresh();
    expect($loan->repaid())->toBe('250000.50')
        ->and($loan->balance())->toBe('249999.50')
        ->and($loan->cashOperations->pluck('type')->all())->toBe([
            CashOperationType::LoanReceived, CashOperationType::RepaymentMade, CashOperationType::RepaymentMade,
        ])
        ->and(app(CashRegister::class)->balances($owner->team)['card'])->toBe('249999.50');
});

test('a repayment of a loan given brings the money back', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $register = app(CashRegister::class);
    $register->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '300000');
    $ledger = app(LoanLedger::class);
    $loan = $ledger->open($owner, LoanDirection::Given, Counterparty::factory()->for($owner->team)->create()->id, '300000', PaymentMethod::Cash);

    $operation = $ledger->repay($loan, $owner, '300000', PaymentMethod::BankTransfer);

    expect($operation->type)->toBe(CashOperationType::RepaymentReceived)
        ->and($loan->fresh()->balance())->toBe('0.00')
        ->and($register->balances($owner->team))->toBe(['cash' => '0.00', 'card' => '0.00', 'bank_transfer' => '300000.00']);
});

test('a repayment above the loan balance is refused and changes nothing', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $ledger = app(LoanLedger::class);
    $loan = $ledger->open($owner, LoanDirection::Received, Counterparty::factory()->for($owner->team)->create()->id, '1250000', PaymentMethod::Cash);
    $ledger->repay($loan, $owner, '250000', PaymentMethod::Cash);

    expect(fn () => $ledger->repay($loan, $owner, '1000000.01', PaymentMethod::Cash))
        ->toThrow(ValidationException::class, "Summa qoldiqdan oshmasligi kerak (qoldiq: 1 000 000 so'm)");

    expect($loan->fresh()->balance())->toBe('1000000.00')
        ->and($loan->cashOperations()->count())->toBe(2);
});

test('interest on a loan received is a Qarz foizi expense, on a loan given an Olingan foiz income, and leaves the balance alone', function (LoanDirection $direction, CashOperationType $type, string $categoryCode, string $cashAfter) {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $register = app(CashRegister::class);
    $register->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '1000000');
    $ledger = app(LoanLedger::class);
    $loan = $ledger->open($owner, $direction, Counterparty::factory()->for($owner->team)->create()->id, '500000', PaymentMethod::Cash);

    $operation = $ledger->recordInterest($loan, $owner, '20000', PaymentMethod::Cash, 'Oktabr');

    expect($operation)
        ->type->toBe($type)
        ->loan_id->toBe($loan->id)
        ->counterparty_id->toBe($loan->counterparty_id)
        ->note->toBe('Oktabr')
        ->and($operation->expenseCategory->code)->toBe($categoryCode)
        ->and($loan->fresh()->balance())->toBe('500000.00')
        ->and($register->balances($owner->team)['cash'])->toBe($cashAfter);
})->with([
    'received' => [LoanDirection::Received, CashOperationType::Expense, 'loan-interest', '1480000.00'],
    'given' => [LoanDirection::Given, CashOperationType::Income, 'interest-received', '520000.00'],
]);
