<?php

use App\Models\User;
use App\Support\AiConnections;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();
});

test('only the owner can open the members page', function () {
    $member = User::factory()->create();

    $this->actingAs($member)->get(route('members.index'))->assertForbidden();
    $this->actingAs($member)->post(route('members.store'), [])->assertForbidden();

    $this->actingAs($this->user)->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/members')
            ->has('members', 2)
            ->where('members.0.isOwner', true)
            ->where('members.1.isOwner', false)
            ->has('passwordRules'));
});

test('the owner adds a member who can then log in', function () {
    $this->actingAs($this->user)->post(route('members.store'), [
        'name' => 'แม่',
        'email' => 'mae@example.com',
        'password' => 'password',
    ])->assertRedirect(route('members.index'));

    $member = User::where('email', 'mae@example.com')->sole();
    expect($member->is_owner)->toBeFalse()
        ->and($member->email_verified_at)->not->toBeNull()
        ->and(Hash::check('password', $member->password))->toBeTrue();

    auth()->logout();
    $this->post(route('login.store'), ['email' => 'mae@example.com', 'password' => 'password'])
        ->assertRedirect();
    $this->assertAuthenticatedAs($member);
});

test('a new member needs a unique email and a password', function () {
    $this->actingAs($this->user)->post(route('members.store'), [
        'name' => 'แม่',
        'email' => $this->user->email,
    ])->assertSessionHasErrors(['email', 'password']);
});

test('the owner edits a member and resets their password', function () {
    $member = User::factory()->create();

    $this->actingAs($this->user)->patch(route('members.update', $member), [
        'name' => 'น้องชาย',
        'email' => 'nong@example.com',
    ])->assertRedirect(route('members.index'));

    expect($member->fresh())->name->toBe('น้องชาย')->email->toBe('nong@example.com');

    $this->actingAs($this->user)->put(route('members.password', $member), [
        'password' => 'new-password',
    ])->assertRedirect(route('members.index'));

    expect(Hash::check('new-password', $member->fresh()->password))->toBeTrue();
});

test('resetting a member\'s password ends their sessions and claude connections', function () {
    $member = User::factory()->create();
    $oldHash = $member->password;
    issueToken($member, Client::factory()->create());

    $this->actingAs($this->user)->put(route('members.password', $member), [
        'password' => 'new-password',
    ])->assertRedirect(route('members.index'));

    expect(AiConnections::forUser($member->id))->toBe([]);

    $this->flushSession();

    $this->actingAs($member->refresh())
        ->withSession(['password_hash_web' => $oldHash])
        ->get(route('overview'))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('the owner deactivates and reactivates a member', function () {
    $member = User::factory()->create();

    $this->actingAs($this->user)->patch(route('members.deactivate', $member))
        ->assertRedirect(route('members.index'));
    expect($member->fresh()->isActive())->toBeFalse();

    $this->actingAs($this->user)->patch(route('members.activate', $member))
        ->assertRedirect(route('members.index'));
    expect($member->fresh()->isActive())->toBeTrue();
});

test('the owner cannot deactivate the owner account', function () {
    $this->actingAs($this->user)->patch(route('members.deactivate', $this->user))->assertForbidden();

    expect($this->user->fresh()->isActive())->toBeTrue();
});

test('the page says who has claude connected', function () {
    $connected = User::factory()->create();
    User::factory()->create();
    issueToken($connected, Client::factory()->create());

    $this->actingAs($this->user)->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => collect($members)
                ->where('isConnected', true)->pluck('id')->all() === [$connected->id]));
});

test('deactivated members are marked on the page', function () {
    User::factory()->deactivated()->create();

    $this->actingAs($this->user)->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members', fn ($members) => collect($members)->where('isActive', false)->count() === 1));
});

test('members page shows who has a passkey', function () {
    $this->user->update(['name' => 'ก']);
    $member = User::factory()->create(['name' => 'ข']);
    createPasskey($member);

    $this->actingAs($this->user)->get(route('members.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('members.0.hasPasskey', false)
            ->where('members.1.hasPasskey', true));
});
