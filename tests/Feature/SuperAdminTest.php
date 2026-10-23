<?php

use App\Models\Location;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('a super-admin logs in to the store list', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $response = $this->post(route('login.store'), [
        'phone' => $superAdmin->phone,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('admin.stores.index'));
});

test('a super-admin lists stores with their owner and status', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $owner = User::factory()
        ->for(Team::factory()->blocked()->state(['name' => 'Ichimliklar dunyosi']))
        ->create(['name' => 'Ali Valiyev', 'phone' => '+998901234567']);
    User::factory()->employeeOf($owner->team)->create();

    $response = $this->actingAs($superAdmin)->get(route('admin.stores.index'));

    $response->assertInertia(fn (Assert $page) => $page
        ->component('admin/stores/index')
        ->has('stores', 1)
        ->where('stores.0.id', $owner->team_id)
        ->where('stores.0.name', 'Ichimliklar dunyosi')
        ->where('stores.0.owner_name', 'Ali Valiyev')
        ->where('stores.0.owner_phone', '+998901234567')
        ->where('stores.0.is_blocked', true),
    );
});

test('a super-admin blocks and unblocks a store without touching its data', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $owner = User::factory()->create();
    $location = Location::factory()->for($owner->team)->create();

    $this->actingAs($superAdmin)->post(route('admin.stores.block', $owner->team))
        ->assertRedirect(route('admin.stores.index'));
    expect($owner->team->fresh()->isBlocked())->toBeTrue();

    $this->actingAs($superAdmin)->post(route('admin.stores.unblock', $owner->team))
        ->assertRedirect(route('admin.stores.index'));
    expect($owner->team->fresh()->isBlocked())->toBeFalse();

    $this->post(route('logout'));
    $this->post(route('login.store'), ['phone' => $owner->phone, 'password' => 'password']);
    $this->assertAuthenticatedAs($owner);
    expect(Location::pluck('id')->all())->toContain($location->id);
});

test('a super-admin resets a store owner password', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    $owner = User::factory()->create();

    $response = $this->actingAs($superAdmin)->put(route('admin.stores.owner-password.update', $owner->team), [
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $response->assertRedirect(route('admin.stores.index'));
    expect(Hash::check('new-password', $owner->fresh()->password))->toBeTrue();
});

test('a super-admin is refused store business routes', function () {
    $superAdmin = User::factory()->superAdmin()->create();

    $this->actingAs($superAdmin)->get(route('dashboard'))->assertForbidden();
    $this->actingAs($superAdmin)->get(route('employees.index'))->assertForbidden();
});

test('a store owner is refused the super-admin area', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)->get(route('admin.stores.index'))->assertForbidden();
    $this->actingAs($owner)->post(route('admin.stores.block', $owner->team))->assertForbidden();

    expect($owner->team->fresh()->isBlocked())->toBeFalse();
});

test('an employee is refused the super-admin area', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();

    $this->actingAs($employee)->get(route('admin.stores.index'))->assertForbidden();
    $this->actingAs($employee)->post(route('admin.stores.block', $owner->team))->assertForbidden();

    expect($owner->team->fresh()->isBlocked())->toBeFalse();
});
