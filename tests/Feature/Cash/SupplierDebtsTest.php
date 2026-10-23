<?php

use App\Actions\Cash\CashRegister;
use App\Actions\Cash\DebtLedger;
use App\Enums\CashOperationType;
use App\Enums\DebtEntryType;
use App\Enums\DebtSide;
use App\Enums\PaymentMethod;
use App\Models\Counterparty;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function owedSupplier(User $owner, string $name, string $debt): Counterparty
{
    $supplier = Counterparty::factory()->for($owner->team)->create(['name' => $name]);
    app(DebtLedger::class)->add($owner, $supplier->id, DebtSide::Supplier, DebtEntryType::OpeningBalance, $debt);

    return $supplier;
}

test('the owner sees every supplier the store still owes, with the total', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    owedSupplier($owner, 'Coca-Cola Ichimliklar', '1250000');
    $paidOff = owedSupplier($owner, 'Nestle', '100000');
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '100000');
    app(DebtLedger::class)->pay($owner, $paidOff, DebtSide::Supplier, '100000', PaymentMethod::Cash);
    Counterparty::factory()->for($owner->team)->create();

    $this->get(route('supplier-debts.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('supplier-debts/index')
            ->has('debts', 1)
            ->where('debts.0.name', 'Coca-Cola Ichimliklar')
            ->where('debts.0.balance', '1250000.00')
            ->where('total', '1250000.00'),
        );
});

test('employees cannot open supplier debts or pay suppliers', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $supplier = owedSupplier($owner, 'Nestle', '100000');

    $this->actingAs($employee)->get(route('supplier-debts.index'))->assertForbidden();
    $this->actingAs($employee)->post(route('supplier-debts.payments.store'), [
        'counterparty_id' => $supplier->id,
        'amount' => '1000',
        'payment_method' => 'cash',
    ])->assertForbidden();
});

test('the owner pays a supplier up to the debt and goes back where they came from', function () {
    $owner = User::factory()->create();
    $this->actingAs($owner);
    app(CashRegister::class)->record($owner, CashOperationType::OpeningBalance, PaymentMethod::Cash, '900000');
    $supplier = owedSupplier($owner, 'Nestle', '400000');

    $this->from(route('cash.index'))->post(route('supplier-debts.payments.store'), [
        'counterparty_id' => $supplier->id,
        'amount' => '150000',
        'payment_method' => 'cash',
        'note' => null,
    ])->assertRedirect(route('cash.index'));

    $this->post(route('supplier-debts.payments.store'), [
        'counterparty_id' => $supplier->id,
        'amount' => '250000.01',
        'payment_method' => 'cash',
    ])->assertSessionHasErrors(['amount' => "Summa qarzdan oshmasligi kerak (qarz: 250 000 so'm)"]);

    expect(app(DebtLedger::class)->balance($supplier, DebtSide::Supplier))->toBe('250000.00')
        ->and(app(CashRegister::class)->balances($owner->team)['cash'])->toBe('750000.00');
});

test('a payment needs a counterparty of the store and an amount above zero', function () {
    $owner = User::factory()->create();
    $foreign = Counterparty::factory()->create();

    $this->actingAs($owner)->post(route('supplier-debts.payments.store'), [
        'counterparty_id' => $foreign->id,
        'amount' => '0',
        'payment_method' => 'cash',
    ])->assertSessionHasErrors(['counterparty_id', 'amount']);
});

test('Kassa offers the owed suppliers for a supplier payment to the owner only', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $this->actingAs($owner);
    $supplier = owedSupplier($owner, 'Nestle', '400000');

    $this->get(route('cash.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('suppliers', [['id' => $supplier->id, 'name' => 'Nestle', 'phone' => $supplier->phone, 'balance' => '400000.00']]),
        );
    $this->actingAs($employee)->get(route('cash.index'))
        ->assertInertia(fn (Assert $page) => $page->missing('suppliers'));
});
