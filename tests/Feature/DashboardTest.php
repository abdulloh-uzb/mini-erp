<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
});

test('store users can visit the dashboard', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();

    $this->actingAs($owner)->get(route('dashboard'))->assertOk();
    $this->actingAs($employee)->get(route('dashboard'))->assertOk();
});
