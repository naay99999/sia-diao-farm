<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Passkeys\Passkey;
use Laravel\Passkeys\Passkeys;

function createPasskey(User $user, string $name = 'iPhone'): Passkey
{
    return $user->passkeys()->create([
        'name' => $name,
        'credential_id' => Str::random(32),
        'credential' => [],
    ]);
}

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
