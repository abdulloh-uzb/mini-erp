<?php

use App\Actions\Cash\CashRegister;
use App\Enums\CashOperationType;
use App\Enums\PaymentMethod;
use App\Models\CashOperation;
use App\Models\Counterparty;
use App\Models\ExpenseCategory;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

function category(string $code): ExpenseCategory
{
    return ExpenseCategory::query()->where('code', $code)->sole();
}

function fundRegister(User $user, PaymentMethod $method, string $amount): void
{
    app(CashRegister::class)->record($user, CashOperationType::OpeningBalance, $method, $amount);
}

describe('page', function () {
    test('the owner sees the balance per method and the total above the operations', function () {
        $owner = User::factory()->create(['name' => 'Aziz']);
        fundRegister($owner, PaymentMethod::Cash, '1250000');
        fundRegister($owner, PaymentMethod::BankTransfer, '300000.50');

        $this->actingAs($owner)->get(route('cash.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('cash/index')
                ->where('balances', ['cash' => '1250000.00', 'card' => '0.00', 'bank_transfer' => '300000.50', 'total' => '1550000.50'])
                ->has('operations.data', 2)
                ->where('operations.data.0.type', "Boshlang'ich qoldiq")
                ->where('operations.data.0.payment_method', "Bank o'tkazmasi")
                ->where('operations.data.0.amount', '300000.50')
                ->where('operations.data.0.author', 'Aziz'),
            );
    });

    test('an employee sees the operations but not the balance', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();
        fundRegister($owner, PaymentMethod::Cash, '1000');

        $this->actingAs($employee)->get(route('cash.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->missing('balances')
                ->has('operations.data', 1),
            );
    });

    test('the employee is offered only the categories they may use', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();

        $this->actingAs($employee)->get(route('cash.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.expense', fn ($categories) => collect($categories)->pluck('name')->all() === [
                    'Transport xarajati', 'Ijara', 'Ish haqi', 'Kommunal', 'Soliq', 'Boshqa',
                ])
                ->where('categories.income', []),
            );
        $this->actingAs($owner)->get(route('cash.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.expense', fn ($categories) => collect($categories)->pluck('name')->all() === [
                    'Transport xarajati', 'Ijara', 'Ish haqi', 'Kommunal', 'Soliq', 'Qarz foizi', 'Boshqa', 'Egasi olgan pul',
                ])
                ->where('categories.income', fn ($categories) => collect($categories)->pluck('name')->all() === ['Olingan foiz']),
            );
    });

    test('operations are filtered by date, type, method, author and counterparty', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();
        $shop = Counterparty::factory()->for($owner->team)->create();
        $this->travelTo('2026-10-01 10:00');
        fundRegister($owner, PaymentMethod::Cash, '500000');
        $this->travelTo('2026-10-05 10:00');
        $register = app(CashRegister::class);
        $register->record($employee, CashOperationType::Expense, PaymentMethod::Cash, '1000', expenseCategoryId: category('rent')->id);
        $register->record($employee, CashOperationType::Expense, PaymentMethod::Cash, '2000', counterpartyId: $shop->id, expenseCategoryId: category('rent')->id);
        $register->record($owner, CashOperationType::Income, PaymentMethod::Card, '3000', expenseCategoryId: category('interest-received')->id);

        $amounts = fn (array $filters) => $this->actingAs($owner)->get(route('cash.index', $filters))
            ->viewData('page')['props']['operations']['data'];

        expect(collect($amounts(['from' => '2026-10-02', 'to' => '2026-10-05']))->pluck('amount')->all())->toBe(['3000.00', '2000.00', '1000.00'])
            ->and(collect($amounts(['type' => 'expense', 'payment_method' => 'cash']))->pluck('amount')->all())->toBe(['2000.00', '1000.00'])
            ->and(collect($amounts(['author_id' => $owner->id]))->pluck('amount')->all())->toBe(['3000.00', '500000.00'])
            ->and(collect($amounts(['counterparty_id' => $shop->id]))->pluck('amount')->all())->toBe(['2000.00']);
    });

    test('each store sees only its own operations', function () {
        $owner = User::factory()->create();
        fundRegister(User::factory()->create(), PaymentMethod::Cash, '999');

        $this->actingAs($owner)->get(route('cash.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('operations.data', 0)
                ->where('balances.total', '0.00'),
            );
    });
});

describe('expense and income', function () {
    test('a user records an expense, which leaves the register by its method', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();
        fundRegister($owner, PaymentMethod::Cash, '100000');

        $this->actingAs($employee)->post(route('cash.operations.store'), [
            'expense_category_id' => category('transport')->id,
            'amount' => '35000',
            'payment_method' => 'cash',
            'note' => 'Yuk tashish',
        ])->assertRedirect(route('cash.index'));

        expect(CashOperation::query()->latest('id')->first())
            ->type->toBe(CashOperationType::Expense)
            ->amount->toBe('35000.00')
            ->note->toBe('Yuk tashish')
            ->author_id->toBe($employee->id)
            ->and(app(CashRegister::class)->balances($owner->team)['cash'])->toBe('65000.00');
    });

    test('the owner records an Egasi olgan pul withdrawal and an Olingan foiz income', function () {
        $owner = User::factory()->create();
        fundRegister($owner, PaymentMethod::Card, '100000');

        $this->actingAs($owner)->post(route('cash.operations.store'), [
            'expense_category_id' => category('owner-withdrawal')->id, 'amount' => '40000', 'payment_method' => 'card',
        ])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post(route('cash.operations.store'), [
            'expense_category_id' => category('interest-received')->id, 'amount' => '5000', 'payment_method' => 'card',
        ])->assertSessionHasNoErrors();

        expect(CashOperation::query()->orderBy('id')->pluck('type')->all())
            ->toBe([CashOperationType::OpeningBalance, CashOperationType::Expense, CashOperationType::Income])
            ->and(app(CashRegister::class)->balances($owner->team)['card'])->toBe('65000.00');
    });

    test('an employee cannot use the owner-only categories', function (string $code) {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();
        fundRegister($owner, PaymentMethod::Cash, '100000');

        $this->actingAs($employee)->post(route('cash.operations.store'), [
            'expense_category_id' => category($code)->id, 'amount' => '1000', 'payment_method' => 'cash',
        ])->assertSessionHasErrors(['expense_category_id' => 'Tanlangan xarajat turi topilmadi.']);

        expect(CashOperation::query()->count())->toBe(1);
    })->with(['owner-withdrawal', 'loan-interest', 'bad-debt', 'interest-received']);

    test('nobody records Umidsiz qarz from the cash register', function () {
        $owner = User::factory()->create();
        fundRegister($owner, PaymentMethod::Cash, '100000');

        $this->actingAs($owner)->post(route('cash.operations.store'), [
            'expense_category_id' => category('bad-debt')->id, 'amount' => '1000', 'payment_method' => 'cash',
        ])->assertSessionHasErrors('expense_category_id');
    });

    test('Boshqa needs a note, and the amount must be above zero', function () {
        $owner = User::factory()->create();

        $this->actingAs($owner)->post(route('cash.operations.store'), [
            'expense_category_id' => category('other')->id, 'amount' => '0', 'payment_method' => 'cash', 'note' => '',
        ])->assertSessionHasErrors(['note', 'amount']);

        expect(CashOperation::query()->count())->toBe(0);
    });

    test('an expense above the method\'s balance is refused', function () {
        $owner = User::factory()->create();
        fundRegister($owner, PaymentMethod::Cash, '10000');
        fundRegister($owner, PaymentMethod::Card, '90000');

        $this->actingAs($owner)->post(route('cash.operations.store'), [
            'expense_category_id' => category('rent')->id, 'amount' => '10000.01', 'payment_method' => 'cash',
        ])->assertSessionHasErrors(['amount' => 'Kassada naqd yetarli emas']);

        expect(CashOperation::query()->count())->toBe(2);
    });
});

describe('correction', function () {
    test('the owner corrects an expense with its opposite operation', function () {
        $owner = User::factory()->create();
        fundRegister($owner, PaymentMethod::Cash, '50000');
        $expense = app(CashRegister::class)->record($owner, CashOperationType::Expense, PaymentMethod::Cash, '20000', expenseCategoryId: category('rent')->id);

        $this->actingAs($owner)->post(route('cash.operations.reverse', $expense))->assertRedirect(route('cash.index'));

        expect($expense->reversal)->not->toBeNull()
            ->and(app(CashRegister::class)->balances($owner->team)['cash'])->toBe('50000.00');
        $this->actingAs($owner)->post(route('cash.operations.reverse', $expense))
            ->assertSessionHasErrors(['operation' => 'Bu operatsiya allaqachon teskarisi bilan tuzatilgan.']);
    });

    test('only expenses and incomes are corrected from the cash register', function () {
        $owner = User::factory()->create();
        fundRegister($owner, PaymentMethod::Cash, '50000');

        $this->actingAs($owner)->post(route('cash.operations.reverse', CashOperation::query()->sole()))
            ->assertForbidden();
    });

    test('an employee cannot correct an operation, nor reach another store\'s', function () {
        $owner = User::factory()->create();
        $employee = User::factory()->employeeOf($owner->team)->create();
        fundRegister($owner, PaymentMethod::Cash, '50000');
        $expense = app(CashRegister::class)->record($owner, CashOperationType::Expense, PaymentMethod::Cash, '20000', expenseCategoryId: category('rent')->id);

        $this->actingAs($employee)->post(route('cash.operations.reverse', $expense))->assertForbidden();
        $this->actingAs(User::factory()->create())->post(route('cash.operations.reverse', $expense))->assertNotFound();

        expect($expense->reversal)->toBeNull();
    });
});
