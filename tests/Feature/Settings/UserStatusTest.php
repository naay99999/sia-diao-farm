<?php

use App\Models\User;
use App\Support\AiConnections;
use Laravel\Passport\Client;
use Laravel\Passport\Passport;
use Laravel\Passport\RefreshToken;

test('users are active until deactivated', function () {
    expect(User::factory()->create()->isActive())->toBeTrue()
        ->and(User::factory()->deactivated()->create()->isActive())->toBeFalse();
});

test('deactivated users cannot log in', function () {
    $user = User::factory()->deactivated()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'บัญชีนี้ถูกปิดใช้งานแล้ว']);

    $this->assertGuest();
});

test('a wrong password for a deactivated user gets the usual failure message', function () {
    $user = User::factory()->deactivated()->create();

    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong'])
        ->assertSessionHasErrors(['email' => __('auth.failed')]);
});

test('a session that outlives deactivation is logged out on the next request', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $user->deactivate();

    $this->get(route('orchards.index'))->assertRedirect(route('login'));
    $this->assertGuest();
});

test('deactivating revokes every ai connection token', function () {
    $user = User::factory()->create();
    $token = issueToken($user, Client::factory()->create());

    $user->deactivate();

    expect($token->fresh()->revoked)->toBeTrue()
        ->and(RefreshToken::where('access_token_id', $token->id)->value('revoked'))->toBeTrue()
        ->and(AiConnections::forUser($user->id))->toBe([]);
});

test('activating clears the deactivation', function () {
    $user = User::factory()->deactivated()->create();

    $user->activate();

    expect($user->fresh()->isActive())->toBeTrue();
});

test('the farm server refuses a deactivated user', function () {
    $user = User::factory()->deactivated()->create();
    Passport::actingAs($user, ['mcp:use']);

    $this->postJson('/mcp/farm', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'])->assertForbidden();
});
