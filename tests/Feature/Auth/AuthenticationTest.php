<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate with their phone in any common spelling', function () {
    $user = User::factory()->create(['phone' => '+998901234567']);

    $response = $this->post(route('login.store'), [
        'phone' => '+998 90 123 45 67',
        'password' => 'password',
    ]);

    $this->assertAuthenticatedAs($user);
    $response->assertRedirect(route('dashboard'));
});

test('passkey login response redirects to the dashboard', function () {
    $user = User::factory()->create();

    $request = Request::create(route('login', absolute: false), 'GET', server: [
        'HTTP_ACCEPT' => 'application/json',
    ]);
    $request->setLaravelSession($this->app['session.store']);
    $request->setUserResolver(fn () => $user);

    $jsonResponse = app(PasskeyLoginResponse::class)->toResponse($request);

    expect($jsonResponse->getData()->redirect)->toBe(route('dashboard'));
});

test('deactivated users and users of a blocked store can not log in with a passkey', function () {
    $owner = User::factory()->create();
    $deactivated = User::factory()->employeeOf($owner->team)->inactive()->create();
    $blocked = User::factory()->for(Team::factory()->blocked())->create();

    $passkeyOf = fn (User $user) => (new Passkey)->setRelation('user', $user);

    expect(Passkeys::allowsLogin(request(), $passkeyOf($owner)))->toBeTrue()
        ->and(Passkeys::allowsLogin(request(), $passkeyOf($deactivated)))->toBeFalse()
        ->and(Passkeys::allowsLogin(request(), $passkeyOf($blocked)))->toBeFalse();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'phone' => $user->phone,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrors(['phone' => "Telefon raqami yoki parol noto'g'ri."]);
    $this->assertGuest();
});

test('deactivated users can not log in', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->inactive()->create();

    $response = $this->post(route('login.store'), [
        'phone' => $employee->phone,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['phone' => 'Hisobingiz faol emas.']);
    $this->assertGuest();
});

test('users of a blocked store can not log in', function () {
    $user = User::factory()->for(Team::factory()->blocked())->create();

    $response = $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'password',
    ]);

    $response->assertSessionHasErrors(['phone' => "Do'kon bloklangan."]);
    $this->assertGuest();
});

test('users of a blocked store are logged out on their next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $user->team->update(['blocked_at' => now()]);

    $response = $this->get(route('dashboard'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors(['phone' => "Do'kon bloklangan."]);
    $this->assertGuest();
});

test('deactivated users are logged out on their next request', function () {
    $owner = User::factory()->create();
    $employee = User::factory()->employeeOf($owner->team)->create();
    $this->actingAs($employee);

    $employee->update(['is_active' => false]);

    $this->get(route('dashboard'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $this->assertGuest();
    $response->assertRedirect(route('home'));
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->phone, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'phone' => $user->phone,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
