<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the owner receives every store permission with each page', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.role', 'owner')
            ->where('auth.store.name', $owner->team->name)
            ->where('auth.permissions', fn ($permissions) => collect($permissions)->contains('employees.manage')
                && collect($permissions)->contains('purchase.execute')
                && collect($permissions)->contains('costs.view')
                && collect($permissions)->contains('finance-reports.view')
                && collect($permissions)->contains('loans.manage')),
        );
});

test('an employee receives only the employee permissions', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();

    $this->actingAs($employee)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.role', 'employee')
            ->where('auth.permissions', function ($permissions) {
                $permissions = collect($permissions);

                return $permissions->contains('sale.execute')
                    && $permissions->contains('transfer.execute')
                    && $permissions->contains('purchase.draft')
                    && $permissions->contains('expense.create')
                    && ! $permissions->contains('purchase.execute')
                    && ! $permissions->contains('inventory-count.execute')
                    && ! $permissions->contains('sales-return.execute')
                    && ! $permissions->contains('supplier-return.manage')
                    && ! $permissions->contains('costs.view')
                    && ! $permissions->contains('finance-reports.view')
                    && ! $permissions->contains('cash-balance.view')
                    && ! $permissions->contains('loans.manage')
                    && ! $permissions->contains('debt.write-off')
                    && ! $permissions->contains('opening-balance.manage')
                    && ! $permissions->contains('employees.manage')
                    && ! $permissions->contains('products.manage');
            }),
        );
});

test('a super-admin receives no store permissions', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.role', 'super-admin')
            ->where('auth.store', null)
            ->where('auth.permissions', []),
        );
});
