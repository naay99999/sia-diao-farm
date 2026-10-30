<?php

use App\Models\User;
use App\Support\Provenance;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Laravel\Passport\Passport;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('mcp endpoint rejects unauthenticated requests', function () {
    $this->postJson('/mcp/farm', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize'])
        ->assertUnauthorized()
        ->assertHeader('WWW-Authenticate');
});

test('oauth discovery routes are registered', function () {
    $this->getJson('/.well-known/oauth-protected-resource')
        ->assertOk()
        ->assertJsonPath('scopes_supported', ['mcp:use']);

    $this->getJson('/.well-known/oauth-authorization-server')
        ->assertOk()
        ->assertJsonPath('authorization_endpoint', route('passport.authorizations.authorize'))
        ->assertJsonPath('token_endpoint', route('passport.token'));

    $this->postJson('/oauth/register', [])->assertClientError();
});

test('authenticated mcp requests are marked with mcp provenance', function () {
    Passport::actingAs($this->user, ['mcp:use']);

    $this->postJson('/mcp/farm', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ],
    ])
        ->assertOk()
        ->assertJsonPath('result.serverInfo.name', 'Sia Diao Farm');

    expect(Context::get(Provenance::VIA_KEY))->toBe(Provenance::VIA_MCP);
});

test('server instructions cover the farm safety rules', function () {
    Passport::actingAs($this->user, ['mcp:use']);

    $instructions = $this->postJson('/mcp/farm', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'initialize',
        'params' => [
            'protocolVersion' => '2025-06-18',
            'capabilities' => (object) [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0'],
        ],
    ])->json('result.instructions');

    expect($instructions)
        ->toContain('get_farm_overview')
        ->toContain('Thai')
        ->toContain('explicit confirmation')
        ->toContain('pre-harvest interval')
        ->toContain('tree code');
});

test('oauth authorize screen is rendered in thai', function () {
    $this->withoutVite();

    $client = (object) ['id' => 'client-id', 'name' => 'Claude', 'redirect_uris' => ['https://claude.ai/api/mcp/auth_callback']];

    $this->view('mcp.authorize', [
        'client' => $client,
        'user' => $this->user,
        'scopes' => [(object) ['description' => 'Use MCP server']],
        'authToken' => 'token',
        'request' => Request::create('/oauth/authorize', 'GET', ['redirect_uri' => 'https://claude.ai/api/mcp/auth_callback']),
    ])
        ->assertSee('อนุญาต Claude')
        ->assertSee('สิทธิ์จะถูกส่งไปที่:')
        ->assertSee('claude.ai')
        ->assertSee('ใช้งานเซิร์ฟเวอร์ MCP ของสวน')
        ->assertSee('ยกเลิก')
        ->assertDontSee('Authorize');
});

test('oauth clients may only redirect to claude or localhost', function (string $redirectUri, int $status) {
    $this->postJson('/oauth/register', [
        'client_name' => 'Claude',
        'redirect_uris' => [$redirectUri],
    ])->assertStatus($status);
})->with([
    'claude.ai' => ['https://claude.ai/api/mcp/auth_callback', 201],
    'claude.com' => ['https://claude.com/api/mcp/auth_callback', 201],
    'claude code on localhost' => ['http://localhost:54321/callback', 201],
    'a stranger posing as claude' => ['https://attacker.example/callback', 400],
    'a look-alike subdomain' => ['https://claude.ai.attacker.example/callback', 400],
]);

test('access tokens last an hour and refresh tokens thirty days', function () {
    expect(Passport::tokensExpireIn()->totalHours)->toBe(1.0)
        ->and(Passport::refreshTokensExpireIn()->totalDays)->toBe(30.0);
});
