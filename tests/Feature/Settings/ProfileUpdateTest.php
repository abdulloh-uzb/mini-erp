<?php

use App\Models\User;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated with a normalised phone', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'phone' => '+998 97 765 43 21',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->phone)->toBe('+998977654321');
});

test('profile phone cannot be changed to another user\'s phone', function () {
    $user = User::factory()->create();
    User::factory()->create(['phone' => '+998977654321']);

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'phone' => '+998977654321',
        ]);

    $response->assertSessionHasErrors(['phone' => "Bu telefon raqami allaqachon ro'yxatdan o'tgan."]);
});
