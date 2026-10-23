<?php

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\DebtLedger;
use App\Enums\CashOperationType;
use App\Enums\DebtEntryType;
use App\Enums\DebtSide;
use App\Enums\PaymentMethod;
use App\Models\CashOperation;
use App\Models\Counterparty;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function owingCustomer(User $owner, string $name, string $debt): Counterparty
{
    $customer = Counterparty::factory()->for($owner->team)->create(['name' => $name]);
    app(DebtLedger::class)->add($owner, $customer->id, DebtSide::Customer, DebtEntryType::OpeningBalance, $debt);

    return $customer;
}

test('users see every customer who owes the store, with the total', function (string $role) {
    $owner = User::factory()->create();
    $user = $role === 'owner' ? $owner : User::factory()->employeeOf($owner->team)->create();
    $this->actingAs($owner);
    owingCustomer($owner, 'Do\'kon Baraka', '480000');
    $settled = owingCustomer($owner, 'Do\'kon Nur', '50000');
    app(DebtLedger::class)->pay($owner, $settled, DebtSide::Customer, '50000', PaymentMethod::Cash);
    Counterparty::factory()->for($owner->team)->create();

    $this->actingAs($user)->get(route('customer-debts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customer-debts/index')
            ->has('debts', 1)
            ->where('debts.0.name', 'Do\'kon Baraka')
            ->where('debts.0.balance', '480000.00')
            ->where('total', '480000.00'),
        );
})->with(['owner', 'employee']);

test('the customer card shows the balance, the sales oldest first and the history newest first', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    $customer = Counterparty::factory()->for($owner->team)->create();
    $ledger = app(DebtLedger::class);
    $ledger->add($owner, $customer->id, DebtSide::Customer, DebtEntryType::OpeningBalance, '100000');
    $ledger->add($owner, $customer->id, DebtSide::Customer, DebtEntryType::Sale, '60000');
    $ledger->pay($owner, $customer, DebtSide::Customer, '130000', PaymentMethod::Card, 'Naqd emas');
    $ledger->writeOff($owner, $customer, '10000', 'Qaytarmaydi');

    $this->get(route('customer-debts.show', $customer))
        ->assertInertia(fn (Assert $page) => $page
            ->component('customer-debts/show')
            ->where('balance', '20000.00')
            ->has('sales', 2)
            ->where('sales.0.amount', '60000.00')
            ->where('sales.0.debt', '20000.00')
            ->where('sales.1.paid', '100000.00')
            ->has('history', 4)
            ->where('history.0.type', 'Hisobdan chiqarildi (Umidsiz qarz)')
            ->where('history.0.amount', '-10000.00')
            ->where('history.1.payment_method', 'Karta')
            ->where('history.1.note', 'Naqd emas')
            ->where('history.3.type', "Boshlang'ich qarz"),
        );
});

test('a customer of another store has no card', function () {
    $owner = User::factory()->create();
    $foreign = Counterparty::factory()->create();

    $this->actingAs($owner)->get(route('customer-debts.show', $foreign))->assertNotFound();
});

test('an employee takes a debt payment up to the debt and goes back where they came from', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $customer = owingCustomer($owner, 'Do\'kon Baraka', '400000');

    $this->actingAs($employee)->from(route('customer-debts.index'))->post(route('customer-debts.payments.store'), [
        'counterparty_id' => $customer->id,
        'amount' => '150000',
        'payment_method' => 'cash',
        'note' => null,
    ])->assertRedirect(route('customer-debts.index'));

    $this->post(route('customer-debts.payments.store'), [
        'counterparty_id' => $customer->id,
        'amount' => '250000.01',
        'payment_method' => 'cash',
    ])->assertSessionHasErrors(['amount' => "Summa qarzdan oshmasligi kerak (qarz: 250 000 so'm)"]);

    expect(app(DebtLedger::class)->balance($customer, DebtSide::Customer))->toBe('250000.00')
        ->and(CashOperation::query()->sole())
        ->type->toBe(CashOperationType::CustomerDebtPayment)
        ->author_id->toBe($employee->id)
        ->and(app(CashRegister::class)->balances($owner->team)['cash'])->toBe('150000.00');
});

test('a payment needs a counterparty of the store and an amount above zero', function () {
    $owner = User::factory()->create();
    $foreign = Counterparty::factory()->create();

    $this->actingAs($owner)->post(route('customer-debts.payments.store'), [
        'counterparty_id' => $foreign->id,
        'amount' => '0',
        'payment_method' => 'cash',
    ])->assertSessionHasErrors(['counterparty_id', 'amount']);
});

test('the owner writes off part of a debt, which moves no money', function () {
    $owner = User::factory()->create();
    $customer = owingCustomer($owner, 'Do\'kon Baraka', '400000');

    $this->actingAs($owner)->from(route('customer-debts.show', $customer))->post(route('customer-debts.write-offs.store'), [
        'counterparty_id' => $customer->id,
        'amount' => '100000',
        'note' => 'Yopilgan',
    ])->assertRedirect(route('customer-debts.show', $customer));

    $this->post(route('customer-debts.write-offs.store'), [
        'counterparty_id' => $customer->id,
        'amount' => '300000.01',
    ])->assertSessionHasErrors('amount');

    expect(app(DebtLedger::class)->balance($customer, DebtSide::Customer))->toBe('300000.00')
        ->and(CashOperation::query()->count())->toBe(0);
});

test('an employee cannot write off a debt', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $customer = owingCustomer($owner, 'Do\'kon Baraka', '400000');

    $this->actingAs($employee)->post(route('customer-debts.write-offs.store'), [
        'counterparty_id' => $customer->id,
        'amount' => '1000',
    ])->assertForbidden();

    expect(app(DebtLedger::class)->balance($customer, DebtSide::Customer))->toBe('400000.00');
});

test('Kassa offers the owing customers for a debt payment to everyone who may take one', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $customer = owingCustomer($owner, 'Do\'kon Baraka', '400000');
    $expected = [['id' => $customer->id, 'name' => 'Do\'kon Baraka', 'phone' => $customer->phone, 'balance' => '400000.00']];

    foreach ([$owner, $employee] as $user) {
        $this->actingAs($user)->get(route('cash.index'))
            ->assertInertia(fn (Assert $page) => $page->where('customers', $expected));
    }
});
