<?php

use App\Actions\Cash\CashRegister;
use App\Enums\CashDirection;
use App\Enums\CashOperationType;
use App\Enums\PaymentMethod;
use App\Models\CashOperation;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Domain tests on the cash register module (spec: Seam 2), called directly without HTTP.
 */
test('the balance is kept per payment method from the operations recorded', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $register = app(CashRegister::class);

    $register->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '1000000');
    $register->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Card, '250000.50');
    $register->record($owner, CashOperationType::Expense, PaymentMethod::Cash, '120000');

    expect($register->balances($owner->team))->toBe([
        'cash' => '880000.00',
        'card' => '250000.50',
        'bank_transfer' => '0.00',
    ]);
});

test('each operation records its type, direction, method, amount, links and author', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);

    $operation = app(CashRegister::class)->record($owner, CashOperationType::Income, PaymentMethod::BankTransfer, '5000', note: 'Bonus');

    expect($operation->fresh())
        ->team_id->toBe($owner->team_id)
        ->type->toBe(CashOperationType::Income)
        ->direction->toBe(CashDirection::In)
        ->payment_method->toBe(PaymentMethod::BankTransfer)
        ->amount->toBe('5000.00')
        ->note->toBe('Bonus')
        ->author_id->toBe($owner->id);
});

test('a cash-out above the method\'s balance is refused and changes nothing', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $register = app(CashRegister::class);
    $register->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '100000');
    $register->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Card, '900000');

    expect(fn () => $register->record($owner, CashOperationType::Expense, PaymentMethod::Cash, '100000.01'))
        ->toThrow(ValidationException::class, 'Kassada naqd yetarli emas');

    expect($register->balances($owner->team)['cash'])->toBe('100000.00')
        ->and(CashOperation::query()->count())->toBe(2);
});

test('a cash-out of exactly the balance empties the method', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $register = app(CashRegister::class);
    $register->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Card, '70000');

    $register->record($owner, CashOperationType::Expense, PaymentMethod::Card, '70000');

    expect($register->balances($owner->team)['card'])->toBe('0.00');
});

test('the refusal names the method that lacks the money', function (PaymentMethod $method, string $message) {
    $owner = User::factory()->create();
    $this->actingAs($owner);

    expect(fn () => app(CashRegister::class)->record($owner, CashOperationType::Expense, $method, '1'))
        ->toThrow(ValidationException::class, $message);
})->with([
    [PaymentMethod::Card, "Kassada karta bo'yicha mablag' yetarli emas"],
    [PaymentMethod::BankTransfer, "Kassada bank o'tkazmasi bo'yicha mablag' yetarli emas"],
]);

test('a second cash-out sees the first one\'s result', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $register = app(CashRegister::class);
    $register->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '50000');
    $register->record($owner, CashOperationType::Expense, PaymentMethod::Cash, '30000');

    expect(fn () => $register->record($owner, CashOperationType::Expense, PaymentMethod::Cash, '30000'))
        ->toThrow(ValidationException::class, 'Kassada naqd yetarli emas');
});

test('balances belong to one store', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $register = app(CashRegister::class);
    $register->record($other, CashOperationType::OpeningBalance, PaymentMethod::Cash, '500000');

    expect($register->balances($owner->team)['cash'])->toBe('0.00')
        ->and(fn () => $register->record($owner, CashOperationType::Expense, PaymentMethod::Cash, '1'))
        ->toThrow(ValidationException::class, 'Kassada naqd yetarli emas');
});

test('recorded operations cannot be changed or deleted', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $operation = app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '1000');

    expect(fn () => $operation->update(['amount' => '1']))->toThrow(LogicException::class)
        ->and(fn () => $operation->delete())->toThrow(LogicException::class);
    expect($operation->fresh()->amount)->toBe('1000.00');
});
