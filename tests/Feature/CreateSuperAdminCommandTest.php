<?php

use App\Enums\StoreRole;
use App\Models\User;

test('the command creates a super-admin with no store', function () {
    $this->artisan('app:create-super-admin', [
        '--name' => 'Platforma Admin',
        '--phone' => '+998 99 000 11 22',
        '--password' => 'secret-password',
    ])->assertSuccessful();

    $superAdmin = User::where('phone', '+998990001122')->firstOrFail();
    expect($superAdmin->name)->toBe('Platforma Admin')
        ->and($superAdmin->team_id)->toBeNull()
        ->and($superAdmin->isSuperAdmin())->toBeTrue();

    $this->post(route('login.store'), ['phone' => '+998990001122', 'password' => 'secret-password'])
        ->assertRedirect(route('admin.stores.index'));
});

test('the command refuses a phone that is already registered', function () {
    User::factory()->create(['phone' => '+998990001122']);

    $this->artisan('app:create-super-admin', [
        '--name' => 'Platforma Admin',
        '--phone' => '+998990001122',
        '--password' => 'secret-password',
    ])->assertFailed();

    expect(User::role(StoreRole::SuperAdmin->value)->count())->toBe(0);
});
