<?php

use App\Models\Harvest;
use App\Models\Tree;
use App\Models\User;
use App\Support\Provenance;
use Illuminate\Support\Facades\Context;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;

beforeEach(function () {
    $this->user = User::factory()->owner()->create();
});

test('guests are redirected to login', function () {
    $this->get(route('assistant.index'))->assertRedirect(route('login'));
});

test('the owner sees the mcp url and whether claude can reach it', function () {
    config(['app.url' => 'http://localhost']);

    $this->actingAs($this->user)
        ->get(route('assistant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('assistant/index')
            ->where('setup.mcpUrl', 'http://localhost/mcp/farm')
            ->where('setup.isPubliclyReachable', false)
            ->where('myConnections', [])
            ->where('recentAiRecords', []));

    config(['app.url' => 'https://farm.example.com']);

    $this->actingAs($this->user)
        ->get(route('assistant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('setup.mcpUrl', 'https://farm.example.com/mcp/farm')
            ->where('setup.isPubliclyReachable', true));
});

test('page lists each connected app once with when it was first connected', function () {
    $claude = Client::factory()->create(['name' => 'Claude']);
    issueToken($this->user, $claude, ['created_at' => now()->subDays(5)]);
    issueToken($this->user, $claude, ['created_at' => now()->subDay()]);

    $this->actingAs($this->user)
        ->get(route('assistant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('myConnections', 1)
            ->where('myConnections.0.clientId', $claude->id)
            ->where('myConnections.0.name', 'Claude')
            ->where('myConnections.0.connectedOn', now()->subDays(5)->toDateString()));
});

test('revoked, fully expired and other users tokens are not listed', function () {
    issueToken($this->user, Client::factory()->create(), ['revoked' => true]);
    issueToken($this->user, Client::factory()->create(), ['expires_at' => now()->subDay()], refreshExpiresAt: '-1 day');
    issueToken(User::factory()->create(), Client::factory()->create());

    $stillRefreshable = Client::factory()->create(['name' => 'Claude']);
    issueToken($this->user, $stillRefreshable, ['expires_at' => now()->subDay()]);

    $this->actingAs($this->user)
        ->get(route('assistant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('myConnections', 1)
            ->where('myConnections.0.clientId', $stillRefreshable->id));
});

test('page lists the latest records written through mcp', function () {
    $tree = Tree::factory()->create(['code' => 'A-03']);
    Harvest::factory()->create(['tree_id' => $tree->id, 'weight_kg' => 12.5, 'harvested_on' => now()->subDay()]);

    $this->actingAs($this->user);
    Context::add(Provenance::VIA_KEY, Provenance::VIA_MCP);
    Harvest::factory()->create(['tree_id' => $tree->id, 'weight_kg' => 20, 'harvested_on' => now()->toDateString()]);
    Context::forget(Provenance::VIA_KEY);

    $this->actingAs($this->user)
        ->get(route('assistant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentAiRecords', 1)
            ->where('recentAiRecords.0.title', 'ผลผลิต 20 กก.')
            ->where('recentAiRecords.0.subject', 'ต้น A-03')
            ->where('recentAiRecords.0.by', $this->user->name)
            ->where('recentAiRecords.0.href', route('trees.show', $tree, false)));
});

test('disconnecting revokes only that app for the current user', function () {
    $claude = Client::factory()->create();
    $token = issueToken($this->user, $claude);
    $otherUsersToken = issueToken(User::factory()->create(), $claude);

    $this->actingAs($this->user)
        ->delete(route('assistant.connections.destroy', [$this->user, $claude->id]))
        ->assertRedirect(route('assistant.index'));

    expect($token->fresh()->revoked)->toBeTrue()
        ->and(RefreshToken::where('access_token_id', $token->id)->value('revoked'))->toBeTrue()
        ->and($otherUsersToken->fresh()->revoked)->toBeFalse();
});

test('members see their own connection but not the setup or other people', function () {
    $member = User::factory()->create();
    issueToken($member, Client::factory()->create(['name' => 'Claude']));
    issueToken($this->user, Client::factory()->create());

    $this->actingAs($member)->get(route('assistant.index'))
        ->assertInertia(fn (Assert $page) => $page->component('assistant/index')
            ->has('myConnections', 1)->where('setup', null)->where('members', null));
});

test('the owner sees every active member and their connections', function () {
    $member = User::factory()->create(['name' => 'ก']);
    User::factory()->deactivated()->create();
    issueToken($member, Client::factory()->create(['name' => 'Claude']));

    $this->actingAs($this->user)->get(route('assistant.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('members', 2)
            ->where('members.0.userId', $this->user->id)
            ->where('members.1.userId', $member->id)
            ->has('members.1.connections', 1)
            ->where('members.1.connections.0.name', 'Claude'));
});

test('the owner can revoke a member connection', function () {
    $member = User::factory()->create();
    $client = Client::factory()->create();
    $token = issueToken($member, $client);

    $this->actingAs($this->user)
        ->delete(route('assistant.connections.destroy', [$member, $client->id]))
        ->assertRedirect(route('assistant.index'));

    expect($token->fresh()->revoked)->toBeTrue();
});

test('a member cannot revoke someone else\'s connection', function () {
    $other = User::factory()->create();
    $client = Client::factory()->create();
    $token = issueToken($other, $client);

    $this->actingAs(User::factory()->create())
        ->delete(route('assistant.connections.destroy', [$other, $client->id]))
        ->assertForbidden();

    expect($token->fresh()->revoked)->toBeFalse();
});

test('a member can revoke their own connection', function () {
    $member = User::factory()->create();
    $client = Client::factory()->create();
    $token = issueToken($member, $client);

    $this->actingAs($member)
        ->delete(route('assistant.connections.destroy', [$member, $client->id]))
        ->assertRedirect(route('assistant.index'));

    expect($token->fresh()->revoked)->toBeTrue();
});

test('the old settings address redirects to the assistant page', function () {
    $this->actingAs($this->user)->get('/settings/ai')->assertRedirect('/assistant');
});
