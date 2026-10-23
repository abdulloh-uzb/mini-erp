<?php

use App\Enums\StoreRole;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function employeeInput(array $overrides = []): array
{
    return [
        'name' => 'Vali Karimov',
        'phone' => '+998 93 555 44 33',
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
        ...$overrides,
    ];
}

test('the owner sees the employees of their own store', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create(['name' => 'Vali Karimov']);
    User::factory()->employeeOf(User::factory()->create()->team)->create();

    $response = $this->actingAs($owner)->get(route('employees.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('employees/index')
        ->has('employees', 1)
        ->where('employees.0.id', $employee->id)
        ->where('employees.0.name', 'Vali Karimov')
        ->where('employees.0.phone', $employee->phone)
        ->where('employees.0.is_active', true),
    );
});

test('the owner creates an employee who can then log in', function () {
    $owner = User::factory()->create();

    $response = $this->actingAs($owner)->post(route('employees.store'), employeeInput());

    $response->assertRedirect(route('employees.index'));

    $employee = User::where('phone', '+998935554433')->firstOrFail();
    expect($employee->team_id)->toBe($owner->team_id)
        ->and($employee->storeRole())->toBe(StoreRole::Employee)
        ->and($employee->is_active)->toBeTrue();

    $this->post(route('logout'));
    $this->post(route('login.store'), ['phone' => '93 555 44 33', 'password' => 'secret-password']);
    $this->assertAuthenticatedAs($employee);
});

test('an employee cannot be created with a phone that is already registered', function () {
    $owner = User::factory()->create();
    User::factory()->create(['phone' => '+998935554433']);

    $response = $this->actingAs($owner)->post(route('employees.store'), employeeInput());

    $response->assertSessionHasErrors(['phone' => "Bu telefon raqami allaqachon ro'yxatdan o'tgan."]);
});

test('the owner resets an employee password', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();

    $response = $this->actingAs($owner)->put(route('employees.password.update', $employee), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('employees.index'));
    expect(Hash::check('new-password', $employee->fresh()->password))->toBeTrue();
});

test('the owner deactivates and reactivates an employee', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();

    $this->actingAs($owner)->post(route('employees.deactivate', $employee))->assertRedirect(route('employees.index'));
    expect($employee->fresh()->is_active)->toBeFalse();

    $this->actingAs($owner)->post(route('employees.activate', $employee))->assertRedirect(route('employees.index'));
    expect($employee->fresh()->is_active)->toBeTrue();
});

test('the owner cannot manage another store\'s employee', function () {
    $owner = User::factory()->create();
    $foreignEmployee = User::factory()->employeeOf(User::factory()->create()->team)->create();

    $this->actingAs($owner)->post(route('employees.deactivate', $foreignEmployee))->assertNotFound();
    $this->actingAs($owner)->put(route('employees.password.update', $foreignEmployee), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertNotFound();

    expect($foreignEmployee->fresh()->is_active)->toBeTrue()
        ->and(Hash::check('password', $foreignEmployee->fresh()->password))->toBeTrue();
});

test('the owner cannot deactivate themselves', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->post(route('employees.deactivate', $owner))->assertForbidden();

    expect($owner->fresh()->is_active)->toBeTrue();
});

test('employees cannot manage employees', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $colleague = User::factory()->employeeOf($owner->team)->create();

    $this->actingAs($employee)->get(route('employees.index'))->assertForbidden();
    $this->actingAs($employee)->post(route('employees.store'), employeeInput())->assertForbidden();
    $this->actingAs($employee)->post(route('employees.deactivate', $colleague))->assertForbidden();
});
