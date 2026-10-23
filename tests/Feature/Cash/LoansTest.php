<?php

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\LoanLedger;
use App\Enums\CashOperationType;
use App\Enums\LoanDirection;
use App\Enums\PaymentMethod;
use App\Models\CashOperation;
use App\Models\Counterparty;
use App\Models\Loan;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function openLoan(User $owner, LoanDirection $direction, string $principal, ?string $dueDate = null): Loan
{
    return app(LoanLedger::class)->open($owner, $direction, Counterparty::factory()->for($owner->team)->create()->id, $principal, PaymentMethod::Cash, $dueDate);
}

test('the owner records a loan given, which pays the principal out and opens its card', function () {
    $owner = User::factory()->create();
    $counterparty = Counterparty::factory()->for($owner->team)->create();
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '1000000');

    $response = $this->actingAs($owner)->post(route('loans.store'), [
        'direction' => 'given',
        'counterparty_id' => $counterparty->id,
        'principal' => '600000',
        'payment_method' => 'cash',
        'due_date' => '2026-12-31',
        'note' => 'Qo\'shniga',
    ]);

    $loan = Loan::query()->sole();
    $response->assertRedirect(route('loans.show', $loan));
    expect($loan)
        ->direction->toBe(LoanDirection::Given)
        ->principal->toBe('600000.00')
        ->due_date->toDateString()->toBe('2026-12-31')
        ->and(app(CashRegister::class)->balances($owner->team)['cash'])->toBe('400000.00');
});

test('a loan given above the method\'s balance is refused', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('loans.store'), [
        'direction' => 'given',
        'counterparty_id' => Counterparty::factory()->for($owner->team)->create()->id,
        'principal' => '1',
        'payment_method' => 'card',
    ])->assertSessionHasErrors(['principal' => "Kassada karta bo'yicha mablag' yetarli emas"]);

    expect(Loan::query()->count())->toBe(0);
});

test('a loan needs a counterparty of the store, a direction and a principal above zero', function () {
    $owner = User::factory()->create();
    $foreign = Counterparty::factory()->create();

    $this->actingAs($owner)->post(route('loans.store'), [
        'direction' => 'lent',
        'counterparty_id' => $foreign->id,
        'principal' => '0',
        'payment_method' => 'cash',
    ])->assertSessionHasErrors(['direction', 'counterparty_id', 'principal']);
});

test('the list shows principal, repaid, balance and due date, with overdue loans marked', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $late = openLoan($owner, LoanDirection::Received, '500000', '2026-10-01');
    app(LoanLedger::class)->repay($late, $owner, '200000', PaymentMethod::Cash);
    $paidOff = openLoan($owner, LoanDirection::Received, '100000', '2026-10-01');
    app(LoanLedger::class)->repay($paidOff, $owner, '100000', PaymentMethod::Cash);
    openLoan($owner, LoanDirection::Received, '70000', '2026-11-30');
    $this->travelTo('2026-10-24');

    $this->get(route('loans.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('loans/index')
            ->has('loans', 3)
            ->where('loans.2.id', $late->id)
            ->where('loans.2.direction', 'Olingan')
            ->where('loans.2.principal', '500000.00')
            ->where('loans.2.repaid', '200000.00')
            ->where('loans.2.balance', '300000.00')
            ->where('loans.2.due_date', '2026-10-01')
            ->where('loans.2.overdue', true)
            ->where('loans.1.overdue', false)
            ->where('loans.0.overdue', false),
        );
});

test('the owner repays a loan from its card, up to its balance', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $loan = openLoan($owner, LoanDirection::Received, '300000');

    $this->post(route('loans.repayments.store', $loan), ['amount' => '100000', 'payment_method' => 'cash'])
        ->assertRedirect(route('loans.show', $loan));
    $this->post(route('loans.repayments.store', $loan), ['amount' => '200000.01', 'payment_method' => 'cash'])
        ->assertSessionHasErrors(['amount' => "Summa qoldiqdan oshmasligi kerak (qoldiq: 200 000 so'm)"]);

    $this->get(route('loans.show', $loan))
        ->assertInertia(fn (Assert $page) => $page
            ->component('loans/show')
            ->where('loan.balance', '200000.00')
            ->has('operations', 2)
            ->where('operations.1.type', 'Qarz qaytarib berildi'),
        );
});

test('the owner records interest from the loan card', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $loan = openLoan($owner, LoanDirection::Received, '300000');

    $this->post(route('loans.interest.store', $loan), ['amount' => '15000', 'payment_method' => 'cash', 'note' => 'Oktabr'])
        ->assertRedirect(route('loans.show', $loan));

    expect(CashOperation::query()->latest('id')->first())
        ->type->toBe(CashOperationType::Expense)
        ->loan_id->toBe($loan->id)
        ->and($loan->fresh()->balance())->toBe('300000.00');
});

test('loans are owner only', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $this->actingAs($owner);
    $loan = openLoan($owner, LoanDirection::Received, '300000');
    $this->actingAs($employee);

    $this->get(route('loans.index'))->assertForbidden();
    $this->get(route('loans.show', $loan))->assertForbidden();
    $this->post(route('loans.store'), [])->assertForbidden();
    $this->post(route('loans.repayments.store', $loan), ['amount' => '1', 'payment_method' => 'cash'])->assertForbidden();
    $this->post(route('loans.interest.store', $loan), ['amount' => '1', 'payment_method' => 'cash'])->assertForbidden();

    expect(CashOperation::query()->count())->toBe(1);
});

test('another store\'s loan is not found', function () {
    $other = User::factory()->create();
    $this->actingAs($other);
    $loan = openLoan($other, LoanDirection::Received, '300000');

    $this->actingAs(User::factory()->create())->get(route('loans.show', $loan))->assertNotFound();
});
