<?php

use App\Models\Harvest;
use App\Models\Tree;
use App\Models\User;
use App\Support\Provenance;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Passport\Client;
use Laravel\Passport\RefreshToken;
use Laravel\Passport\Token;

beforeEach(function () {
    $this->user = User::factory()->create();
});

/**
 * Issue an access token (and its refresh token) for the user, the way the OAuth flow would.
 *
 * @param  array<string, mixed>  $attributes
 */
function issueToken(User $user, Client $client, array $attributes = [], ?string $refreshExpiresAt = '+30 days'): Token
{
    $token = Token::forceCreate([
        'id' => Str::random(80),
        'user_id' => $user->id,
        'client_id' => $client->id,
        'scopes' => [],
        'revoked' => false,
        'expires_at' => now()->addHour(),
        ...$attributes,
    ]);

    if ($refreshExpiresAt !== null) {
        RefreshToken::forceCreate([
            'id' => Str::random(80),
            'access_token_id' => $token->id,
            'revoked' => false,
            'expires_at' => now()->modify($refreshExpiresAt),
        ]);
    }

    return $token;
}

test('guests are redirected to login', function () {
    $this->get(route('ai-connection.edit'))->assertRedirect(route('login'));
});

test('page shows the mcp url and whether claude can reach it', function () {
    config(['app.url' => 'http://localhost']);

    $this->actingAs($this->user)
        ->get(route('ai-connection.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ai-connection')
            ->where('mcpUrl', 'http://localhost/mcp/farm')
            ->where('isPubliclyReachable', false)
            ->where('connections', [])
            ->where('recentAiRecords', []));

    config(['app.url' => 'https://farm.example.com']);

    $this->actingAs($this->user)
        ->get(route('ai-connection.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('mcpUrl', 'https://farm.example.com/mcp/farm')
            ->where('isPubliclyReachable', true));
});

test('page lists each connected app once with when it was first connected', function () {
    $claude = Client::factory()->create(['name' => 'Claude']);
    issueToken($this->user, $claude, ['created_at' => now()->subDays(5)]);
    issueToken($this->user, $claude, ['created_at' => now()->subDay()]);

    $this->actingAs($this->user)
        ->get(route('ai-connection.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('connections', 1)
            ->where('connections.0.clientId', $claude->id)
            ->where('connections.0.name', 'Claude')
            ->where('connections.0.connectedOn', now()->subDays(5)->toDateString()));
});

test('revoked, fully expired and other users tokens are not listed', function () {
    issueToken($this->user, Client::factory()->create(), ['revoked' => true]);
    issueToken($this->user, Client::factory()->create(), ['expires_at' => now()->subDay()], refreshExpiresAt: '-1 day');
    issueToken(User::factory()->create(), Client::factory()->create());

    $stillRefreshable = Client::factory()->create(['name' => 'Claude']);
    issueToken($this->user, $stillRefreshable, ['expires_at' => now()->subDay()]);

    $this->actingAs($this->user)
        ->get(route('ai-connection.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('connections', 1)
            ->where('connections.0.clientId', $stillRefreshable->id));
});

test('page lists the latest records written through mcp', function () {
    $tree = Tree::factory()->create(['code' => 'A-03']);
    Harvest::factory()->create(['tree_id' => $tree->id, 'weight_kg' => 12.5, 'harvested_on' => now()->subDay()]);

    Context::add(Provenance::VIA_KEY, Provenance::VIA_MCP);
    Harvest::factory()->create(['tree_id' => $tree->id, 'weight_kg' => 20, 'harvested_on' => now()->toDateString()]);
    Context::forget(Provenance::VIA_KEY);

    $this->actingAs($this->user)
        ->get(route('ai-connection.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('recentAiRecords', 1)
            ->where('recentAiRecords.0.title', 'ผลผลิต 20 กก.')
            ->where('recentAiRecords.0.subject', 'ต้น A-03')
            ->where('recentAiRecords.0.href', route('trees.show', $tree, false)));
});

test('disconnecting revokes only that app for the current user', function () {
    $claude = Client::factory()->create();
    $token = issueToken($this->user, $claude);
    $otherUsersToken = issueToken(User::factory()->create(), $claude);

    $this->actingAs($this->user)
        ->delete(route('ai-connection.destroy', $claude->id))
        ->assertRedirect(route('ai-connection.edit'));

    expect($token->fresh()->revoked)->toBeTrue()
        ->and(RefreshToken::where('access_token_id', $token->id)->value('revoked'))->toBeTrue()
        ->and($otherUsersToken->fresh()->revoked)->toBeFalse();
});
