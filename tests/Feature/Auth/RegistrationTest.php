<?php

use App\Enums\StoreRole;
use App\Models\Location;
use App\Models\User;

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('a visitor signs up as the owner of a new store with a main warehouse', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Ali Valiyev',
        'phone' => '+998 90 123 45 67',
        'store_name' => 'Ichimliklar dunyosi',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $user = User::where('phone', '+998901234567')->firstOrFail();
    expect($user->name)->toBe('Ali Valiyev')
        ->and($user->team->name)->toBe('Ichimliklar dunyosi')
        ->and($user->storeRole())->toBe(StoreRole::Owner)
        ->and(Location::where('team_id', $user->team_id)->pluck('name')->all())->toBe(['Asosiy ombor']);
});

test('sign-up refuses a phone that is already registered', function () {
    User::factory()->create(['phone' => '+998901234567']);

    $response = $this->post(route('register.store'), [
        'name' => 'Ali Valiyev',
        'phone' => '90 123 45 67',
        'store_name' => 'Ichimliklar dunyosi',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['phone' => "Bu telefon raqami allaqachon ro'yxatdan o'tgan."]);
    $this->assertGuest();
    expect(User::count())->toBe(1);
});

test('sign-up refuses a phone that is not an Uzbek number', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Ali Valiyev',
        'phone' => '12345',
        'store_name' => 'Ichimliklar dunyosi',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors(['phone' => "Telefon raqami +998 XX XXX XX XX ko'rinishida bo'lishi kerak."]);
    $this->assertGuest();
});
