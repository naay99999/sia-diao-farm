<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Passkeys;

test('passkey routes are registered', function () {
    foreach (['passkey.login-options', 'passkey.login', 'passkey.registration-options', 'passkey.store', 'passkey.destroy'] as $name) {
        expect(Route::has($name))->toBeTrue();
    }
});

test('users can request passkey registration options without confirming their password', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('passkey.registration-options'))
        ->assertOk()
        ->assertJsonStructure(['options']);
});

test('users cannot delete another user\'s passkey', function () {
    $passkey = createPasskey(User::factory()->create());

    $this->actingAs(User::factory()->create())
        ->deleteJson(route('passkey.destroy', $passkey))
        ->assertForbidden();
});

test('users can delete their own passkey', function () {
    $user = User::factory()->create();
    $passkey = createPasskey($user);

    $this->actingAs($user)->deleteJson(route('passkey.destroy', $passkey))->assertOk();

    expect($user->passkeys()->count())->toBe(0);
});

test('active users are allowed to log in with a passkey', function () {
    $passkey = createPasskey(User::factory()->create());

    expect(Passkeys::allowsLogin(request(), $passkey))->toBeTrue();
});

test('deactivated users cannot log in with a passkey', function () {
    $passkey = createPasskey(User::factory()->deactivated()->create());

    expect(fn () => Passkeys::allowsLogin(request(), $passkey))
        ->toThrow(ValidationException::class, 'บัญชีนี้ถูกปิดใช้งานแล้ว');
});

test('password login suggests a passkey when the user has none', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHas('inertia.flash_data.suggestPasskey', true);
});

test('no passkey suggestion when the user already has one', function () {
    $user = User::factory()->create();
    createPasskey($user);

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionMissing('inertia.flash_data.suggestPasskey');
});

test('failed login does not suggest a passkey', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionMissing('inertia.flash_data.suggestPasskey');
});
